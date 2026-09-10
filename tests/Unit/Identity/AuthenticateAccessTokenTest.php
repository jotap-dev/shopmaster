<?php

declare(strict_types=1);

namespace Tests\Unit\Identity;

use Identity\Application\AuthenticateAccessToken;
use Identity\Application\ExpiredToken;
use Identity\Application\InvalidToken;
use Identity\Domain\EmailAddress;
use Identity\Domain\PersonName;
use Identity\Domain\PlatformRole;
use Identity\Domain\UserIdentity;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Identity\Support\FakeTokenIssuer;

/** O que o middleware `auth.token` executa a cada requisição protegida. */
final class AuthenticateAccessTokenTest extends TestCase
{
    public function test_devolve_o_portador_a_partir_dos_claims(): void
    {
        $emissor = new FakeTokenIssuer;
        $emissor->issue($this->identidade());

        $usuario = (new AuthenticateAccessToken($emissor))->handle('access-1');

        $this->assertSame('user-1', $usuario->id);
        $this->assertSame('joao@shopmaster.test', $usuario->email->value());
        $this->assertTrue($usuario->hasRole(PlatformRole::Buyer));
    }

    public function test_recusa_refresh_token_usado_como_access(): void
    {
        // O refresh vive 30 dias; o access, uma hora. Aceitar um no lugar do
        // outro anula a vida curta do access, que é a única defesa contra um
        // token vazado — ele não é revogável.
        $emissor = new FakeTokenIssuer;
        $emissor->issue($this->identidade());

        $this->expectException(InvalidToken::class);

        (new AuthenticateAccessToken($emissor))->handle('refresh-1');
    }

    public function test_propaga_token_expirado(): void
    {
        $emissor = new FakeTokenIssuer;
        $emissor->issue($this->identidade());
        $emissor->expirar('access-1');

        $this->expectException(ExpiredToken::class);

        (new AuthenticateAccessToken($emissor))->handle('access-1');
    }

    public function test_recusa_token_forjado(): void
    {
        $this->expectException(InvalidToken::class);

        (new AuthenticateAccessToken(new FakeTokenIssuer))->handle('token-forjado');
    }

    public function test_nao_depende_de_repositorio_algum(): void
    {
        // Autenticar uma requisição não consulta o banco: os papéis vêm do
        // token (RULE 7). O construtor só aceita o emissor — se um dia
        // aparecer um repositório aqui, este teste deixa de compilar, e a
        // decisão volta a ser consciente.
        $parametros = (new \ReflectionClass(AuthenticateAccessToken::class))
            ->getConstructor()
            ->getParameters();

        $this->assertCount(1, $parametros);
        $this->assertSame('Identity\Application\TokenIssuer', (string) $parametros[0]->getType());
    }

    private function identidade(): UserIdentity
    {
        return new UserIdentity(
            id: 'user-1',
            name: PersonName::fromString('Joao Pedro'),
            email: EmailAddress::fromString('joao@shopmaster.test'),
            roles: [PlatformRole::Buyer],
        );
    }
}
