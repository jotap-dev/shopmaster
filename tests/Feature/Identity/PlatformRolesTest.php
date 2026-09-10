<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use Identity\Domain\PlatformRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** RF-007 — conceder/revogar papéis + middleware `role`. */
final class PlatformRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_concede_papel_a_outro_usuario(): void
    {
        $admin = $this->adminLogado();
        $alvo = $this->contaRegistrada('maria@shopmaster.test');

        $this->patchJson("/v1/admin/users/{$alvo}/roles", [
            'roles' => ['buyer', 'seller', 'platform_admin'],
        ], $this->comToken($admin['token']))
            ->assertOk()
            ->assertJsonPath('roles', ['buyer', 'platform_admin', 'seller']);

        $this->assertDatabaseHas('user_platform_roles', [
            'user_id' => $alvo,
            'role' => PlatformRole::Seller->value,
        ]);
    }

    public function test_buyer_comum_leva_403(): void
    {
        $token = $this->contaLogada();
        $alvo = $this->contaRegistrada('maria@shopmaster.test');

        $this->patchJson("/v1/admin/users/{$alvo}/roles", [
            'roles' => ['buyer', 'platform_admin'],
        ], $this->comToken($token))
            ->assertForbidden()
            ->assertJsonPath('error.code', 'forbidden');
    }

    public function test_exige_token(): void
    {
        $this->patchJson('/v1/admin/users/'.fake()->uuid().'/roles', [
            'roles' => ['buyer'],
        ])->assertUnauthorized();
    }

    public function test_usuario_inexistente_devolve_404(): void
    {
        $admin = $this->adminLogado();

        $this->patchJson('/v1/admin/users/'.fake()->uuid().'/roles', [
            'roles' => ['buyer', 'platform_admin'],
        ], $this->comToken($admin['token']))
            ->assertNotFound()
            ->assertJsonPath('error.code', 'not_found');
    }

    public function test_admin_nao_pode_revogar_a_si_mesmo(): void
    {
        $admin = $this->adminLogado();

        $this->patchJson("/v1/admin/users/{$admin['id']}/roles", [
            'roles' => ['buyer'],
        ], $this->comToken($admin['token']))
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'cannot_revoke_own_admin');
    }

    public function test_papel_novo_so_entra_no_token_apos_refresh(): void
    {
        // RULE 7: o middleware lê o token. Conceder platform_admin no banco
        // não libera /admin até o próximo refresh (ou novo login).
        $joao = $this->contaLogadaComId('joao@shopmaster.test');
        $alvo = $this->contaRegistrada('maria@shopmaster.test');

        DB::table('user_platform_roles')->insert([
            'user_id' => $joao['id'],
            'role' => PlatformRole::PlatformAdmin->value,
            'granted_at' => now(),
        ]);

        $this->patchJson("/v1/admin/users/{$alvo}/roles", [
            'roles' => ['buyer', 'seller'],
        ], $this->comToken($joao['token']))
            ->assertForbidden();

        $novoAccess = $this->postJson('/v1/auth/refresh', [
            'refresh_token' => $joao['refresh'],
        ])->assertOk()->json('access_token');

        $this->patchJson("/v1/admin/users/{$alvo}/roles", [
            'roles' => ['buyer', 'seller'],
        ], $this->comToken($novoAccess))
            ->assertOk()
            ->assertJsonPath('roles', ['buyer', 'seller']);
    }

    public function test_nao_expoe_hash_nem_documento(): void
    {
        $admin = $this->adminLogado();
        $alvo = $this->contaRegistrada('maria@shopmaster.test');

        $resposta = $this->patchJson("/v1/admin/users/{$alvo}/roles", [
            'roles' => ['buyer', 'platform_admin'],
        ], $this->comToken($admin['token']));

        $this->assertSame(['id', 'name', 'email', 'roles'], array_keys($resposta->json()));
        $this->assertStringNotContainsString('password', $resposta->getContent());
        $this->assertStringNotContainsString('document', $resposta->getContent());
    }

    /** @return array{id: string, token: string} */
    private function adminLogado(): array
    {
        $conta = $this->contaLogadaComId('admin@shopmaster.test');

        DB::table('user_platform_roles')->insert([
            'user_id' => $conta['id'],
            'role' => PlatformRole::PlatformAdmin->value,
            'granted_at' => now(),
        ]);

        // Relogin para o access carregar o papel recém-concedido.
        $token = $this->postJson('/v1/auth', [
            'email' => 'admin@shopmaster.test',
            'password' => 'senha-forte-123',
        ])->json('access_token');

        return ['id' => $conta['id'], 'token' => $token];
    }

    private function contaRegistrada(string $email): string
    {
        return $this->postJson('/v1/auth/register', [
            'name' => 'Maria',
            'email' => $email,
            'password' => 'senha-forte-123',
        ])->assertCreated()->json('id');
    }

    private function contaLogada(string $email = 'joao@shopmaster.test'): string
    {
        return $this->contaLogadaComId($email)['token'];
    }

    /** @return array{id: string, token: string, refresh: string} */
    private function contaLogadaComId(string $email): array
    {
        $id = $this->postJson('/v1/auth/register', [
            'name' => 'Joao Pedro',
            'email' => $email,
            'password' => 'senha-forte-123',
        ])->assertCreated()->json('id');

        $sessao = $this->postJson('/v1/auth', [
            'email' => $email,
            'password' => 'senha-forte-123',
        ])->assertOk();

        return [
            'id' => $id,
            'token' => $sessao->json('access_token'),
            'refresh' => $sessao->json('refresh_token'),
        ];
    }

    /** @return array<string, string> */
    private function comToken(string $token): array
    {
        return ['Authorization' => "Bearer {$token}"];
    }
}
