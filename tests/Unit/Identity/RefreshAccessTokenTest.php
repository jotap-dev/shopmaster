<?php

declare(strict_types=1);

namespace Tests\Unit\Identity;

use Identity\Application\AuthUserNotFound;
use Identity\Application\ExpiredToken;
use Identity\Application\InvalidToken;
use Identity\Application\RefreshAccessToken;
use Identity\Application\RefreshTokenAlreadyUsed;
use Identity\Domain\EmailAddress;
use Identity\Domain\PersonName;
use Identity\Domain\PlatformRole;
use Identity\Domain\UserIdentity;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Unit\Identity\Support\FakeRefreshTokenBlacklist;
use Tests\Unit\Identity\Support\FakeTokenIssuer;
use Tests\Unit\Identity\Support\FakeUserRepository;

/** RF-004 — o refresh é de uso único. */
final class RefreshAccessTokenTest extends TestCase
{
    public function test_troca_o_refresh_por_um_par_novo(): void
    {
        $emissor = new FakeTokenIssuer;
        $emissor->issue($this->identidade());

        $sessao = $this->useCase(emissor: $emissor)->handle('refresh-1');

        $this->assertSame('access-2', $sessao->tokens->accessToken);
        $this->assertSame('refresh-2', $sessao->tokens->refreshToken);
    }

    public function test_revoga_o_refresh_usado(): void
    {
        $emissor = new FakeTokenIssuer;
        $emissor->issue($this->identidade());
        $lista = new FakeRefreshTokenBlacklist;

        $this->useCase(emissor: $emissor, lista: $lista)->handle('refresh-1');

        $this->assertTrue($lista->isRevoked('jti-refresh-1'));
    }

    public function test_a_segunda_troca_do_mesmo_refresh_e_recusada(): void
    {
        $emissor = new FakeTokenIssuer;
        $emissor->issue($this->identidade());
        $lista = new FakeRefreshTokenBlacklist;
        $useCase = $this->useCase(emissor: $emissor, lista: $lista);

        $useCase->handle('refresh-1');

        $this->expectException(RefreshTokenAlreadyUsed::class);
        $useCase->handle('refresh-1');
    }

    public function test_a_revogacao_expira_junto_com_o_token(): void
    {
        // Guardar a revogação depois do prazo do token não protege mais nada,
        // e a lista cresceria para sempre.
        $emissor = new FakeTokenIssuer;
        $emissor->issue($this->identidade());
        $lista = new FakeRefreshTokenBlacklist;

        $this->useCase(emissor: $emissor, lista: $lista)->handle('refresh-1');

        $this->assertSame('2026-09-09 13:00:00', $lista->revogados['jti-refresh-1']->format('Y-m-d H:i:s'));
    }

    public function test_recusa_access_token_usado_como_refresh(): void
    {
        // Confusão de tipo de token. Sem esta guarda, um access token
        // interceptado renderia um refresh novo — e daí acesso indefinido.
        $emissor = new FakeTokenIssuer;
        $emissor->issue($this->identidade());

        $this->expectException(InvalidToken::class);

        $this->useCase(emissor: $emissor)->handle('access-1');
    }

    public function test_propaga_token_expirado(): void
    {
        $emissor = new FakeTokenIssuer;
        $emissor->issue($this->identidade());
        $emissor->expirar('refresh-1');

        $this->expectException(ExpiredToken::class);

        $this->useCase(emissor: $emissor)->handle('refresh-1');
    }

    public function test_recusa_token_desconhecido(): void
    {
        $this->expectException(InvalidToken::class);

        $this->useCase()->handle('token-forjado');
    }

    public function test_recusa_quando_a_conta_do_token_nao_existe_mais(): void
    {
        $emissor = new FakeTokenIssuer;
        $emissor->issue($this->identidade());

        $this->expectException(AuthUserNotFound::class);

        $this->useCase(usuarios: FakeUserRepository::vazio(), emissor: $emissor)->handle('refresh-1');
    }

    public function test_recarrega_os_papeis_do_banco_em_vez_de_confiar_no_token(): void
    {
        // É aqui que uma mudança de papel passa a valer: o access token antigo
        // carrega o papel antigo até expirar, e o refresh é o momento em que
        // o novo entra. Confiar nos claims congelaria o papel para sempre.
        $emissor = new FakeTokenIssuer;
        $emissor->issue($this->identidade());

        $usuarios = FakeUserRepository::com()->comPapeis([PlatformRole::Buyer, PlatformRole::Seller]);

        $sessao = $this->useCase(usuarios: $usuarios, emissor: $emissor)->handle('refresh-1');

        $this->assertTrue($sessao->user->hasRole(PlatformRole::Seller));
    }

    public function test_revoga_antes_de_emitir_para_falhar_fechado(): void
    {
        // Se a emissão estourar depois da revogação, o cliente perde a sessão
        // e refaz o login — chato. Na ordem inversa, um erro deixaria um
        // refresh já entregue e ainda válido, o que é pior.
        $emissor = new FakeTokenIssuer;
        $emissor->issue($this->identidade());
        $emissor->falhaAoEmitir = new RuntimeException('emissor fora do ar');
        $lista = new FakeRefreshTokenBlacklist;

        try {
            $this->useCase(emissor: $emissor, lista: $lista)->handle('refresh-1');
        } catch (RuntimeException) {
            // esperado
        }

        $this->assertTrue($lista->isRevoked('jti-refresh-1'));
    }

    private function useCase(
        ?FakeUserRepository $usuarios = null,
        ?FakeTokenIssuer $emissor = null,
        ?FakeRefreshTokenBlacklist $lista = null,
    ): RefreshAccessToken {
        return new RefreshAccessToken(
            $usuarios ?? FakeUserRepository::com(),
            $emissor ?? new FakeTokenIssuer,
            $lista ?? new FakeRefreshTokenBlacklist,
        );
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
