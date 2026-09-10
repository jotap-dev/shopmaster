<?php

declare(strict_types=1);

namespace Tests\Unit\Identity;

use Identity\Application\CannotRevokeOwnAdmin;
use Identity\Application\InvalidPlatformRole;
use Identity\Application\SetPlatformRoles;
use Identity\Application\UserNotFound;
use Identity\Domain\PlatformRole;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Identity\Support\FakeUserRepository;

final class SetPlatformRolesTest extends TestCase
{
    public function test_substitui_o_conjunto_de_papeis(): void
    {
        $repo = FakeUserRepository::com(roles: [PlatformRole::Buyer]);

        $usuario = (new SetPlatformRoles($repo))->handle(
            'admin-1',
            'user-1',
            ['buyer', 'platform_admin'],
        );

        $this->assertTrue($usuario->hasRole(PlatformRole::Buyer));
        $this->assertTrue($usuario->hasRole(PlatformRole::PlatformAdmin));
    }

    public function test_recusa_lista_vazia(): void
    {
        $this->expectException(InvalidPlatformRole::class);

        (new SetPlatformRoles(FakeUserRepository::com()))->handle('admin-1', 'user-1', []);
    }

    public function test_recusa_papel_desconhecido(): void
    {
        $this->expectException(InvalidPlatformRole::class);

        (new SetPlatformRoles(FakeUserRepository::com()))->handle('admin-1', 'user-1', ['buyer', 'superuser']);
    }

    public function test_usuario_inexistente(): void
    {
        $this->expectException(UserNotFound::class);

        (new SetPlatformRoles(FakeUserRepository::vazio()))->handle('admin-1', 'user-1', ['buyer']);
    }

    public function test_admin_nao_revoga_a_si_mesmo(): void
    {
        $repo = FakeUserRepository::com(
            id: 'admin-1',
            roles: [PlatformRole::Buyer, PlatformRole::PlatformAdmin],
        );

        $this->expectException(CannotRevokeOwnAdmin::class);

        (new SetPlatformRoles($repo))->handle('admin-1', 'admin-1', ['buyer']);
    }

    public function test_admin_pode_manter_o_proprio_papel(): void
    {
        $repo = FakeUserRepository::com(
            id: 'admin-1',
            roles: [PlatformRole::Buyer, PlatformRole::PlatformAdmin],
        );

        $usuario = (new SetPlatformRoles($repo))->handle(
            'admin-1',
            'admin-1',
            ['buyer', 'seller', 'platform_admin'],
        );

        $this->assertTrue($usuario->hasRole(PlatformRole::Seller));
        $this->assertTrue($usuario->hasRole(PlatformRole::PlatformAdmin));
    }
}
