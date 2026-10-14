<?php

declare(strict_types=1);

namespace Identity\Infrastructure\Jwt;

use DateTimeImmutable;
use Identity\Application\ExpiredToken;
use Identity\Application\InvalidToken;
use Identity\Application\TokenIssuer;
use Identity\Domain\EmailAddress;
use Identity\Domain\PlatformRole;
use Identity\Domain\TokenClaims;
use Identity\Domain\TokenPair;
use Identity\Domain\TokenType;
use Identity\Domain\UserIdentity;
use Shared\Clock;

/**
 * JWT HS256 assinado pela própria aplicação.
 *
 * Escrito à mão, sem biblioteca: são ~60 linhas de `hash_hmac` e base64url,
 * e uma dependência a menos para auditar num caminho que é a porta de
 * entrada de toda a API.
 *
 * Mora em `Infrastructure/` mas **não tem I/O** — só hash e string —, por
 * isso é coberto por Unit test direto. O único "mundo externo" é o relógio,
 * que é porta.
 */
final readonly class HmacJwtTokenIssuer implements TokenIssuer
{
    private const ALGORITMO = 'HS256';

    public function __construct(
        private string $secret,
        private string $issuer,
        private int $accessTokenTtlInSeconds,
        private int $refreshTokenTtlInSeconds,
        private Clock $clock,
    ) {}

    public function issue(UserIdentity $user): TokenPair
    {
        $agora = $this->clock->now();

        return new TokenPair(
            accessToken: $this->emitir($user, TokenType::Access, $agora, $this->accessTokenTtlInSeconds),
            refreshToken: $this->emitir($user, TokenType::Refresh, $agora, $this->refreshTokenTtlInSeconds),
            expiresIn: $this->accessTokenTtlInSeconds,
        );
    }

    public function parse(string $token): TokenClaims
    {
        $partes = explode('.', $token);

        if (count($partes) !== 3) {
            throw InvalidToken::malformed();
        }

        [$cabecalhoBruto, $payloadBruto, $assinatura] = $partes;

        $cabecalho = $this->decodificarJson($cabecalhoBruto);

        // O algoritmo declarado é conferido antes de qualquer coisa. Sem isto
        // dependeríamos de a assinatura vazia do ataque `alg: none` não bater
        // por acaso — o que é verdade, mas depender de coincidência em
        // autenticação é como não ter a guarda.
        if (($cabecalho['alg'] ?? null) !== self::ALGORITMO) {
            throw InvalidToken::badSignature();
        }

        // `hash_equals` e não `===`: comparação de string comum sai no
        // primeiro byte diferente, e o tempo até sair revela quantos bytes
        // acertaram. Com paciência, dá para descobrir a assinatura byte a
        // byte.
        if (! hash_equals($this->assinar("{$cabecalhoBruto}.{$payloadBruto}"), $assinatura)) {
            throw InvalidToken::badSignature();
        }

        $payload = $this->decodificarJson($payloadBruto);

        if (($payload['iss'] ?? null) !== $this->issuer) {
            throw InvalidToken::unknownIssuer();
        }

        // Só depois da assinatura conferida é que o conteúdo vira objeto de
        // domínio: até aqui, o payload é texto de origem desconhecida.
        $expiraEm = (new DateTimeImmutable)->setTimestamp((int) ($payload['exp'] ?? 0));

        if ($this->clock->now() >= $expiraEm) {
            throw ExpiredToken::create();
        }

        return new TokenClaims(
            id: (string) ($payload['jti'] ?? throw InvalidToken::malformed()),
            issuer: $this->issuer,
            subject: (string) ($payload['sub'] ?? throw InvalidToken::malformed()),
            email: EmailAddress::fromString((string) ($payload['email'] ?? throw InvalidToken::malformed())),
            roles: array_values(array_map(
                fn (string $papel): PlatformRole => PlatformRole::tryFrom($papel) ?? throw InvalidToken::malformed(),
                (array) ($payload['roles'] ?? []),
            )),
            type: TokenType::tryFrom((string) ($payload['typ'] ?? '')) ?? throw InvalidToken::malformed(),
            issuedAt: (new DateTimeImmutable)->setTimestamp((int) ($payload['iat'] ?? 0)),
            expiresAt: $expiraEm,
        );
    }

    private function emitir(UserIdentity $user, TokenType $tipo, DateTimeImmutable $agora, int $ttl): string
    {
        $cabecalho = $this->codificar(['typ' => 'JWT', 'alg' => self::ALGORITMO]);

        $payload = $this->codificar([
            'iss' => $this->issuer,
            'sub' => $user->id,
            'email' => $user->email->value(),
            'roles' => array_map(fn (PlatformRole $papel): string => $papel->value, $user->roles),
            'typ' => $tipo->value,

            // Identificador próprio por token: é por ele que a revogação do
            // refresh acontece, e é o que faz dois logins seguidos gerarem
            // tokens diferentes mesmo no mesmo segundo.
            'jti' => bin2hex(random_bytes(16)),

            'iat' => $agora->getTimestamp(),
            'exp' => $agora->getTimestamp() + $ttl,
        ]);

        return "{$cabecalho}.{$payload}.".$this->assinar("{$cabecalho}.{$payload}");
    }

    private function assinar(string $conteudo): string
    {
        return $this->base64UrlEncode(hash_hmac('sha256', $conteudo, $this->secret, binary: true));
    }

    /** @param array<string, mixed> $dados */
    private function codificar(array $dados): string
    {
        return $this->base64UrlEncode((string) json_encode($dados, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /** @return array<string, mixed> */
    private function decodificarJson(string $parte): array
    {
        $json = base64_decode(strtr($parte, '-_', '+/'), strict: true);

        if ($json === false) {
            throw InvalidToken::malformed();
        }

        $dados = json_decode($json, associative: true);

        if (! is_array($dados)) {
            throw InvalidToken::malformed();
        }

        return $dados;
    }

    private function base64UrlEncode(string $valor): string
    {
        return rtrim(strtr(base64_encode($valor), '+/', '-_'), '=');
    }
}
