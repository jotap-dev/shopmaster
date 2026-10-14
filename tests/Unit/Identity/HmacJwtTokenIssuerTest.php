<?php

declare(strict_types=1);

namespace Tests\Unit\Identity;

use DateTimeImmutable;
use Identity\Application\ExpiredToken;
use Identity\Application\InvalidToken;
use Identity\Domain\EmailAddress;
use Identity\Domain\PersonName;
use Identity\Domain\PlatformRole;
use Identity\Domain\TokenType;
use Identity\Domain\UserIdentity;
use Identity\Infrastructure\Jwt\HmacJwtTokenIssuer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Clock;

/**
 * Mora em `Infrastructure/` mas é **puro** — hash e string, zero I/O —,
 * então é Unit test direto, sem banco e sem HTTP. Ver docs/testing.md.
 *
 * O tempo é controlado por um `Clock` fake: testar expiração esperando de
 * verdade tornaria a suíte inútil.
 */
final class HmacJwtTokenIssuerTest extends TestCase
{
    private const SEGREDO = 'segredo-de-teste-nao-use-em-producao';

    private const AGORA = '2026-09-09 12:00:00';

    public function test_emite_um_token_que_ele_mesmo_abre(): void
    {
        $par = $this->emissor()->issue($this->usuario());

        $claims = $this->emissor()->parse($par->accessToken);

        $this->assertSame('user-1', $claims->subject);
        $this->assertSame('joao@shopmaster.test', $claims->email->value());
        $this->assertSame('shopmaster', $claims->issuer);
        $this->assertSame(TokenType::Access, $claims->type);
    }

    public function test_preserva_os_papeis_de_plataforma(): void
    {
        $usuario = $this->usuario([PlatformRole::Buyer, PlatformRole::Seller]);

        $claims = $this->emissor()->parse($this->emissor()->issue($usuario)->accessToken);

        $this->assertSame([PlatformRole::Buyer, PlatformRole::Seller], $claims->roles);
    }

    public function test_marca_o_refresh_com_o_tipo_dele(): void
    {
        $par = $this->emissor()->issue($this->usuario());

        $this->assertSame(TokenType::Refresh, $this->emissor()->parse($par->refreshToken)->type);
    }

    public function test_o_access_expira_antes_do_refresh(): void
    {
        $par = $this->emissor()->issue($this->usuario());

        $access = $this->emissor()->parse($par->accessToken);
        $refresh = $this->emissor()->parse($par->refreshToken);

        $this->assertSame('2026-09-09 13:00:00', $access->expiresAt->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-09 12:00:00', $refresh->expiresAt->format('Y-m-d H:i:s'));
        $this->assertSame(3600, $par->expiresIn);
    }

    public function test_cada_token_tem_um_identificador_proprio(): void
    {
        // O jti é o que a revogação usa. Se access e refresh compartilhassem
        // um, revogar o refresh derrubaria o access junto — ou pior, revogar
        // um refresh não revogaria nada identificável.
        $par = $this->emissor()->issue($this->usuario());

        $this->assertNotSame(
            $this->emissor()->parse($par->accessToken)->id,
            $this->emissor()->parse($par->refreshToken)->id,
        );
    }

    public function test_dois_logins_geram_tokens_diferentes(): void
    {
        $emissor = $this->emissor();

        $this->assertNotSame(
            $emissor->issue($this->usuario())->refreshToken,
            $emissor->issue($this->usuario())->refreshToken,
        );
    }

    public function test_recusa_token_expirado(): void
    {
        $par = $this->emissor()->issue($this->usuario());

        $this->expectException(ExpiredToken::class);

        $this->emissor(agora: '2026-09-09 13:00:01')->parse($par->accessToken);
    }

    public function test_o_token_vale_ate_o_instante_anterior_ao_vencimento(): void
    {
        $par = $this->emissor()->issue($this->usuario());

        $claims = $this->emissor(agora: '2026-09-09 12:59:59')->parse($par->accessToken);

        $this->assertSame('user-1', $claims->subject);
    }

    public function test_recusa_assinatura_adulterada(): void
    {
        $par = $this->emissor()->issue($this->usuario());

        $this->expectException(InvalidToken::class);

        $this->emissor()->parse(substr($par->accessToken, 0, -4).'AAAA');
    }

    public function test_recusa_payload_adulterado(): void
    {
        // O ataque óbvio: trocar o `sub` por outro usuário. A assinatura
        // cobre o payload, então a troca invalida o token.
        [$cabecalho, $payload, $assinatura] = explode('.', $this->emissor()->issue($this->usuario())->accessToken);

        $adulterado = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
        $adulterado['sub'] = 'user-2';
        $novoPayload = rtrim(strtr(base64_encode((string) json_encode($adulterado)), '+/', '-_'), '=');

        $this->expectException(InvalidToken::class);

        $this->emissor()->parse("{$cabecalho}.{$novoPayload}.{$assinatura}");
    }

    public function test_recusa_token_assinado_com_outro_segredo(): void
    {
        $outro = new HmacJwtTokenIssuer('outro-segredo-qualquer', 'shopmaster', 3600, 2592000, $this->relogio(self::AGORA));

        $this->expectException(InvalidToken::class);

        $this->emissor()->parse($outro->issue($this->usuario())->accessToken);
    }

    public function test_recusa_token_de_outro_emissor(): void
    {
        $outro = new HmacJwtTokenIssuer(self::SEGREDO, 'outra-api', 3600, 2592000, $this->relogio(self::AGORA));

        $this->expectException(InvalidToken::class);

        $this->emissor()->parse($outro->issue($this->usuario())->accessToken);
    }

    public function test_recusa_o_ataque_do_alg_none(): void
    {
        // O clássico: trocar o algoritmo por "none" e mandar assinatura vazia,
        // na esperança de que o verificador acredite no cabeçalho.
        $cabecalho = rtrim(strtr(base64_encode((string) json_encode(['typ' => 'JWT', 'alg' => 'none'])), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode((string) json_encode([
            'iss' => 'shopmaster', 'sub' => 'user-1', 'exp' => PHP_INT_MAX,
        ])), '+/', '-_'), '=');

        $this->expectException(InvalidToken::class);

        $this->emissor()->parse("{$cabecalho}.{$payload}.");
    }

    /** @return list<array{string}> */
    public static function tokensMalformados(): array
    {
        return [['lixo'], ['a.b'], ['a.b.c.d'], [''], ['...'], ['a.!!!.c']];
    }

    #[DataProvider('tokensMalformados')]
    public function test_recusa_token_malformado(string $token): void
    {
        $this->expectException(InvalidToken::class);

        $this->emissor()->parse($token);
    }

    private function emissor(string $agora = self::AGORA): HmacJwtTokenIssuer
    {
        return new HmacJwtTokenIssuer(self::SEGREDO, 'shopmaster', 3600, 2592000, $this->relogio($agora));
    }

    private function relogio(string $agora): Clock
    {
        return new class($agora) implements Clock
        {
            public function __construct(private string $agora) {}

            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable($this->agora);
            }
        };
    }

    /** @param list<PlatformRole> $roles */
    private function usuario(array $roles = [PlatformRole::Buyer]): UserIdentity
    {
        return new UserIdentity(
            id: 'user-1',
            name: PersonName::fromString('Joao Pedro'),
            email: EmailAddress::fromString('joao@shopmaster.test'),
            roles: $roles,
        );
    }
}
