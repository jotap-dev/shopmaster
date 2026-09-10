<?php

declare(strict_types=1);

namespace Tests\Unit\Identity\Support;

use DateTimeImmutable;
use Identity\Application\ExpiredToken;
use Identity\Application\InvalidToken;
use Identity\Application\TokenIssuer;
use Identity\Domain\TokenClaims;
use Identity\Domain\TokenPair;
use Identity\Domain\TokenType;
use Identity\Domain\UserIdentity;

/**
 * Emissor em memória: o token é só uma etiqueta, e os claims ficam num mapa.
 *
 * Isso deixa o teste controlar o que a criptografia de verdade tornaria
 * caro de montar — token expirado, token de tipo trocado, token forjado —
 * sem tocar em assinatura nenhuma. O HMAC de verdade é testado à parte,
 * em HmacJwtTokenIssuerTest.
 */
final class FakeTokenIssuer implements TokenIssuer
{
    public int $emissoes = 0;

    /** @var array<string, TokenClaims> */
    private array $claims = [];

    /** @var list<string> */
    private array $expirados = [];

    public ?\Throwable $falhaAoEmitir = null;

    public function issue(UserIdentity $user): TokenPair
    {
        if ($this->falhaAoEmitir !== null) {
            throw $this->falhaAoEmitir;
        }

        $this->emissoes++;
        $n = $this->emissoes;

        // Lista de pares, não mapa: enum não pode ser chave de array em PHP.
        foreach ([[TokenType::Access, "access-{$n}"], [TokenType::Refresh, "refresh-{$n}"]] as [$tipo, $token]) {
            $this->claims[$token] = new TokenClaims(
                id: "jti-{$tipo->value}-{$n}",
                issuer: 'shopmaster',
                subject: $user->id,
                email: $user->email,
                roles: $user->roles,
                type: $tipo,
                issuedAt: new DateTimeImmutable('2026-09-09 12:00:00'),
                expiresAt: new DateTimeImmutable('2026-09-09 13:00:00'),
            );
        }

        return new TokenPair("access-{$n}", "refresh-{$n}", 3600);
    }

    public function parse(string $token): TokenClaims
    {
        if (in_array($token, $this->expirados, strict: true)) {
            throw ExpiredToken::create();
        }

        return $this->claims[$token] ?? throw InvalidToken::malformed();
    }

    public function expirar(string $token): void
    {
        $this->expirados[] = $token;
    }
}
