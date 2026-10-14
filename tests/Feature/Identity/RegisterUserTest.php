<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * RF-001 — POST /v1/auth/register, batendo na rota real.
 */
final class RegisterUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_cadastra_e_devolve_201_com_o_usuario(): void
    {
        $resposta = $this->cadastrar();

        $resposta->assertCreated()
            ->assertJsonPath('name', 'Joao Pedro')
            ->assertJsonPath('email', 'joao@shopmaster.test')
            ->assertJsonPath('roles', ['buyer']);

        $this->assertNotEmpty($resposta->json('id'));
        $this->assertNotEmpty($resposta->json('registered_at'));
    }

    public function test_a_resposta_nao_expoe_nenhum_campo_sensivel(): void
    {
        // RULE 4 — negar por omissão. A resposta tem exatamente estas chaves;
        // qualquer campo novo que apareça aqui foi decisão consciente.
        $resposta = $this->cadastrar();

        $this->assertSame(
            ['id', 'name', 'email', 'roles', 'registered_at'],
            array_keys($resposta->json()),
        );

        $corpo = $resposta->getContent();
        $this->assertStringNotContainsString('senha-forte-123', $corpo);
        $this->assertStringNotContainsString('password', $corpo);
        $this->assertStringNotContainsString('$2y$', $corpo, 'O hash bcrypt vazou na resposta.');
    }

    public function test_persiste_o_usuario_com_o_papel_buyer(): void
    {
        $id = $this->cadastrar()->json('id');

        $this->assertDatabaseHas('users', ['id' => $id, 'email' => 'joao@shopmaster.test']);
        $this->assertDatabaseHas('user_platform_roles', ['user_id' => $id, 'role' => 'buyer']);
    }

    public function test_grava_a_senha_com_hash_e_nunca_em_texto_puro(): void
    {
        $id = $this->cadastrar()->json('id');

        $hash = DB::table('users')->where('id', $id)->value('password_hash');

        $this->assertNotSame('senha-forte-123', $hash);
        $this->assertTrue(password_verify('senha-forte-123', $hash));
    }

    public function test_normaliza_o_email_para_minusculas(): void
    {
        $resposta = $this->cadastrar(['email' => 'Joao@ShopMaster.TEST']);

        $resposta->assertCreated()->assertJsonPath('email', 'joao@shopmaster.test');
    }

    public function test_recusa_email_ja_cadastrado(): void
    {
        $this->cadastrar()->assertCreated();

        $this->cadastrar()
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'email_already_registered');
    }

    public function test_recusa_email_ja_cadastrado_com_caixa_diferente(): void
    {
        $this->cadastrar()->assertCreated();

        $this->cadastrar(['email' => 'JOAO@SHOPMASTER.TEST'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'email_already_registered');

        $this->assertSame(1, DB::table('users')->count());
    }

    public function test_recusa_campos_faltando_no_formato_de_erro_do_projeto(): void
    {
        // RNF-061 — erro SEMPRE em {"error": {"code", "message"}}, inclusive
        // os de validação, que o Laravel devolveria noutro formato.
        $resposta = $this->postJson('/v1/auth/register', []);

        $resposta->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        // Ordenado no teste de proposito: a ordem em que o Laravel avalia as
        // regras nao e contrato, e nao deveria quebrar este teste.
        $campos = array_keys($resposta->json('error.fields'));
        sort($campos);

        $this->assertSame(['email', 'name', 'password'], $campos);
    }

    public function test_recusa_senha_curta(): void
    {
        $this->cadastrar(['password' => 'curta12'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_recusa_senha_acima_do_limite_de_bytes_do_bcrypt(): void
    {
        // 40 emojis = 40 caracteres, 160 bytes. Uma regra `max:72` do Laravel
        // conta caracteres e deixaria passar — o bcrypt truncaria em silencio.
        $this->cadastrar(['password' => str_repeat('🔐', 40)])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->assertSame(0, DB::table('users')->count());
    }

    public function test_recusa_email_malformado(): void
    {
        $this->cadastrar(['email' => 'joao-arroba-shopmaster'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_recusa_nome_vazio(): void
    {
        $this->cadastrar(['name' => '   '])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_a_rota_e_publica_e_nao_exige_token(): void
    {
        $this->cadastrar()->assertCreated();
    }

    /** @param array<string, mixed> $sobrescreve */
    private function cadastrar(array $sobrescreve = []): TestResponse
    {
        return $this->postJson('/v1/auth/register', array_merge([
            'name' => 'Joao Pedro',
            'email' => 'joao@shopmaster.test',
            'password' => 'senha-forte-123',
        ], $sobrescreve));
    }
}
