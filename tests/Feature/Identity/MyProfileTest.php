<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** RF-005 — GET e PATCH de "minha conta". */
final class MyProfileTest extends TestCase
{
    use RefreshDatabase;

    private const CPF = '529.982.247-25';

    private const OUTRO_CPF = '111.444.777-35';

    public function test_devolve_a_propria_conta(): void
    {
        $token = $this->contaLogada();

        $this->getJson('/v1/me', $this->comToken($token))
            ->assertOk()
            ->assertJsonPath('email', 'joao@shopmaster.test')
            ->assertJsonPath('name', 'Joao Pedro')
            ->assertJsonPath('phone', null)
            ->assertJsonPath('document', null);
    }

    public function test_a_conta_nao_expoe_credencial_alguma(): void
    {
        $token = $this->contaLogada();

        $resposta = $this->getJson('/v1/me', $this->comToken($token));

        $this->assertSame(
            ['id', 'name', 'email', 'phone', 'phone_formatted', 'document'],
            array_keys($resposta->json()),
        );
        $this->assertStringNotContainsString('$2y$', $resposta->getContent());
        $this->assertStringNotContainsString('password', $resposta->getContent());
    }

    public function test_o_perfil_nao_promete_permissao_que_o_token_nao_da(): void
    {
        // Papel é autorização, não perfil. O banco pode já ter um papel que o
        // token ainda não tem — ele só entra na próxima renovação. Um perfil
        // que devolvesse `roles` prometeria uma permissão que o middleware,
        // lendo o token, vai recusar. Quem responde isso é /v1/auth/session.
        $token = $this->contaLogada();

        $perfil = $this->getJson('/v1/me', $this->comToken($token));
        $sessao = $this->getJson('/v1/auth/session', $this->comToken($token));

        $this->assertArrayNotHasKey('roles', $perfil->json());
        $this->assertSame(['buyer'], $sessao->json('roles'));
    }

    public function test_exige_token(): void
    {
        $this->getJson('/v1/me')->assertUnauthorized();
        $this->patchJson('/v1/me', ['name' => 'X'])->assertUnauthorized();
    }

    public function test_altera_nome_e_telefone(): void
    {
        $token = $this->contaLogada();

        $this->patchJson('/v1/me', ['name' => 'Joao  Pedro  Rodrigues', 'phone' => '(11) 98888-7777'], $this->comToken($token))
            ->assertOk()
            ->assertJsonPath('name', 'Joao Pedro Rodrigues')
            ->assertJsonPath('phone', '11988887777')
            ->assertJsonPath('phone_formatted', '(11) 98888-7777');

        $this->assertDatabaseHas('users', ['phone' => '11988887777', 'name' => 'Joao Pedro Rodrigues']);
    }

    public function test_define_o_documento_com_tipo(): void
    {
        $token = $this->contaLogada();

        $this->patchJson('/v1/me', ['document' => self::CPF, 'document_type' => 'cpf'], $this->comToken($token))
            ->assertOk()
            ->assertJsonPath('document.type', 'cpf')
            ->assertJsonPath('document.value', '52998224725');

        $this->assertDatabaseHas('users', ['document' => '52998224725', 'document_type' => 'cpf']);
    }

    public function test_o_patch_nao_apaga_o_que_nao_foi_enviado(): void
    {
        $token = $this->contaLogada();
        $this->patchJson('/v1/me', ['phone' => '(11) 98888-7777'], $this->comToken($token))->assertOk();

        $this->patchJson('/v1/me', ['name' => 'Outro Nome'], $this->comToken($token))
            ->assertOk()
            ->assertJsonPath('phone', '11988887777');
    }

    public function test_remove_o_telefone_com_null_explicito(): void
    {
        $token = $this->contaLogada();
        $this->patchJson('/v1/me', ['phone' => '(11) 98888-7777'], $this->comToken($token))->assertOk();

        $this->patchJson('/v1/me', ['phone' => null], $this->comToken($token))
            ->assertOk()
            ->assertJsonPath('phone', null)
            ->assertJsonPath('phone_formatted', null);

        $this->assertDatabaseHas('users', ['email' => 'joao@shopmaster.test', 'phone' => null]);
    }

    public function test_omitir_o_telefone_nao_o_remove(): void
    {
        // A diferenca que importa: ausente e "nao mexa", null e "apague".
        $token = $this->contaLogada();
        $this->patchJson('/v1/me', ['phone' => '(11) 98888-7777'], $this->comToken($token))->assertOk();

        $this->patchJson('/v1/me', ['name' => 'Outro Nome'], $this->comToken($token))
            ->assertOk()
            ->assertJsonPath('phone', '11988887777');
    }

    public function test_corpo_vazio_devolve_o_perfil_inalterado(): void
    {
        // PATCH sem nada a mudar nao e erro: o cliente que reenvia o
        // formulario inteiro sem alteracao recebe o perfil de volta.
        $token = $this->contaLogada();

        $this->patchJson('/v1/me', [], $this->comToken($token))
            ->assertOk()
            ->assertJsonPath('name', 'Joao Pedro');
    }

    public function test_recusa_trocar_documento_ja_definido(): void
    {
        $token = $this->contaLogada();
        $this->patchJson('/v1/me', ['document' => self::CPF, 'document_type' => 'cpf'], $this->comToken($token))->assertOk();

        $this->patchJson('/v1/me', ['document' => self::OUTRO_CPF, 'document_type' => 'cpf'], $this->comToken($token))
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'document_already_set');
    }

    public function test_reenviar_o_mesmo_documento_e_aceito(): void
    {
        $token = $this->contaLogada();
        $this->patchJson('/v1/me', ['document' => self::CPF, 'document_type' => 'cpf'], $this->comToken($token))->assertOk();

        $this->patchJson('/v1/me', ['name' => 'Novo Nome', 'document' => self::CPF, 'document_type' => 'cpf'], $this->comToken($token))
            ->assertOk()
            ->assertJsonPath('name', 'Novo Nome');
    }

    public function test_recusa_documento_ja_usado_por_outra_conta(): void
    {
        $primeiro = $this->contaLogada();
        $this->patchJson('/v1/me', ['document' => self::CPF, 'document_type' => 'cpf'], $this->comToken($primeiro))->assertOk();

        $segundo = $this->contaLogada('maria@shopmaster.test');

        $this->patchJson('/v1/me', ['document' => self::CPF, 'document_type' => 'cpf'], $this->comToken($segundo))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'document_already_in_use');
    }

    public function test_recusa_cpf_invalido(): void
    {
        $token = $this->contaLogada();

        $this->patchJson('/v1/me', ['document' => '529.982.247-26', 'document_type' => 'cpf'], $this->comToken($token))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'invalid_document');
    }

    public function test_recusa_documento_sem_tipo(): void
    {
        $token = $this->contaLogada();

        $this->patchJson('/v1/me', ['document' => self::CPF], $this->comToken($token))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_recusa_tipo_de_documento_desconhecido(): void
    {
        $token = $this->contaLogada();

        $this->patchJson('/v1/me', ['document' => '123456789', 'document_type' => 'rg'], $this->comToken($token))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_recusa_telefone_com_ddd_inexistente(): void
    {
        $token = $this->contaLogada();

        $this->patchJson('/v1/me', ['phone' => '(20) 98888-7777'], $this->comToken($token))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'invalid_phone');
    }

    public function test_recusa_tentativa_de_trocar_o_email(): void
    {
        // RF-005 — o e-mail não se troca no v1. Recusar explicitamente é
        // melhor que ignorar em silêncio: quem tentou precisa saber que não
        // funcionou, senão acha que trocou.
        $token = $this->contaLogada();

        $this->patchJson('/v1/me', ['email' => 'outro@shopmaster.test'], $this->comToken($token))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->assertDatabaseHas('users', ['email' => 'joao@shopmaster.test']);
    }

    public function test_um_usuario_nao_altera_a_conta_de_outro(): void
    {
        // O id vem do token, nunca do corpo — não há como apontar para outra
        // conta. Este teste fixa isso: mandar `id` alheio não muda nada.
        $primeiro = $this->contaLogada();
        $segundo = $this->contaLogada('maria@shopmaster.test');
        $idDoPrimeiro = $this->getJson('/v1/me', $this->comToken($primeiro))->json('id');

        $this->patchJson('/v1/me', ['id' => $idDoPrimeiro, 'name' => 'Invadido'], $this->comToken($segundo))
            ->assertOk();

        $this->assertDatabaseHas('users', ['id' => $idDoPrimeiro, 'name' => 'Joao Pedro']);
    }

    // -----------------------------------------------------------------

    private function contaLogada(string $email = 'joao@shopmaster.test'): string
    {
        $this->postJson('/v1/auth/register', [
            'name' => 'Joao Pedro',
            'email' => $email,
            'password' => 'senha-forte-123',
        ])->assertCreated();

        return $this->postJson('/v1/auth', ['email' => $email, 'password' => 'senha-forte-123'])
            ->json('access_token');
    }

    /** @return array<string, string> */
    private function comToken(string $token): array
    {
        return ['Authorization' => "Bearer {$token}"];
    }
}
