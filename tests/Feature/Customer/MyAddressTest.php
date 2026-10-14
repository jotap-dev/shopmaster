<?php

declare(strict_types=1);

namespace Tests\Feature\Customer;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** RF-006 — agenda de endereços com um marcado como padrão. */
final class MyAddressTest extends TestCase
{
    use RefreshDatabase;

    public function test_lista_vazia_no_comeco(): void
    {
        $token = $this->contaLogada();

        $this->getJson('/v1/me/addresses', $this->comToken($token))
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_cria_o_primeiro_como_padrao(): void
    {
        $token = $this->contaLogada();

        $resposta = $this->postJson('/v1/me/addresses', $this->endereco(['label' => 'Casa']), $this->comToken($token))
            ->assertCreated()
            ->assertJsonPath('label', 'Casa')
            ->assertJsonPath('postal_code', '01310100')
            ->assertJsonPath('postal_code_formatted', '01310-100')
            ->assertJsonPath('state', 'SP')
            ->assertJsonPath('is_default', true);

        $this->assertSame(
            [
                'id', 'label', 'recipient_name', 'street', 'number', 'complement',
                'neighborhood', 'city', 'state', 'postal_code', 'postal_code_formatted', 'is_default',
            ],
            array_keys($resposta->json()),
        );
        $this->assertArrayNotHasKey('user_id', $resposta->json());
    }

    public function test_exige_token(): void
    {
        $id = fake()->uuid();

        $this->getJson('/v1/me/addresses')->assertUnauthorized();
        $this->postJson('/v1/me/addresses', $this->endereco())->assertUnauthorized();
        $this->getJson("/v1/me/addresses/{$id}")->assertUnauthorized();
        $this->patchJson("/v1/me/addresses/{$id}", ['number' => '1'])->assertUnauthorized();
        $this->deleteJson("/v1/me/addresses/{$id}")->assertUnauthorized();
    }

    public function test_segundo_endereco_nao_vira_padrao_sozinho(): void
    {
        $token = $this->contaLogada();
        $this->postJson('/v1/me/addresses', $this->endereco(['label' => 'Casa']), $this->comToken($token))->assertCreated();

        $this->postJson('/v1/me/addresses', $this->endereco(['label' => 'Trabalho', 'number' => '200']), $this->comToken($token))
            ->assertCreated()
            ->assertJsonPath('is_default', false);

        $lista = $this->getJson('/v1/me/addresses', $this->comToken($token))->json('data');
        $this->assertTrue($lista[0]['is_default']);
        $this->assertSame('Casa', $lista[0]['label']);
    }

    public function test_pode_trocar_o_padrao(): void
    {
        $token = $this->contaLogada();
        $casa = $this->postJson('/v1/me/addresses', $this->endereco(['label' => 'Casa']), $this->comToken($token))->json('id');
        $trabalho = $this->postJson('/v1/me/addresses', $this->endereco(['label' => 'Trabalho', 'number' => '200']), $this->comToken($token))->json('id');

        $this->patchJson("/v1/me/addresses/{$trabalho}", ['is_default' => true], $this->comToken($token))
            ->assertOk()
            ->assertJsonPath('is_default', true);

        $this->getJson("/v1/me/addresses/{$casa}", $this->comToken($token))
            ->assertOk()
            ->assertJsonPath('is_default', false);
    }

    public function test_endereco_de_outro_usuario_devolve_404(): void
    {
        $joao = $this->contaLogada();
        $maria = $this->contaLogada('maria@shopmaster.test');
        $id = $this->postJson('/v1/me/addresses', $this->endereco(), $this->comToken($joao))->json('id');

        $this->getJson("/v1/me/addresses/{$id}", $this->comToken($maria))
            ->assertNotFound()
            ->assertJsonPath('error.code', 'not_found');

        $this->patchJson("/v1/me/addresses/{$id}", ['number' => '999'], $this->comToken($maria))
            ->assertNotFound();

        $this->deleteJson("/v1/me/addresses/{$id}", [], $this->comToken($maria))
            ->assertNotFound();

        $this->assertDatabaseHas('addresses', ['id' => $id, 'number' => '1000']);
    }

    public function test_remover_o_padrao_promove_o_mais_antigo(): void
    {
        $token = $this->contaLogada();
        $casa = $this->postJson('/v1/me/addresses', $this->endereco(['label' => 'Casa']), $this->comToken($token))->json('id');
        $trabalho = $this->postJson('/v1/me/addresses', $this->endereco(['label' => 'Trabalho', 'number' => '200']), $this->comToken($token))->json('id');

        $this->deleteJson("/v1/me/addresses/{$casa}", [], $this->comToken($token))->assertNoContent();

        $this->getJson("/v1/me/addresses/{$trabalho}", $this->comToken($token))
            ->assertOk()
            ->assertJsonPath('is_default', true);
    }

    public function test_recusa_cep_invalido(): void
    {
        $token = $this->contaLogada();

        $this->postJson('/v1/me/addresses', $this->endereco(['postal_code' => '123']), $this->comToken($token))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'invalid_postal_code');
    }

    public function test_recusa_uf_inventada(): void
    {
        $token = $this->contaLogada();

        $this->postJson('/v1/me/addresses', $this->endereco(['state' => 'XX']), $this->comToken($token))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');
    }

    /** @param array<string, mixed> $extra */
    private function endereco(array $extra = []): array
    {
        return array_merge([
            'recipient_name' => 'Joao Pedro',
            'street' => 'Av Paulista',
            'number' => '1000',
            'neighborhood' => 'Bela Vista',
            'city' => 'Sao Paulo',
            'state' => 'SP',
            'postal_code' => '01310-100',
        ], $extra);
    }

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
