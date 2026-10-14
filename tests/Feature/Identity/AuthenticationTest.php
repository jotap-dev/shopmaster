<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** RF-003, RF-004 e RF-008, batendo nas rotas reais. */
final class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'joao@shopmaster.test';

    private const SENHA = 'senha-forte-123';

    // -----------------------------------------------------------------
    // POST /v1/auth — login
    // -----------------------------------------------------------------

    public function test_autentica_e_devolve_o_par_de_tokens(): void
    {
        $this->cadastrar();

        $resposta = $this->logar();

        $resposta->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.email', self::EMAIL)
            ->assertJsonPath('user.roles', ['buyer']);

        $this->assertNotEmpty($resposta->json('access_token'));
        $this->assertNotEmpty($resposta->json('refresh_token'));
        $this->assertSame(3600, $resposta->json('expires_in'));
    }

    public function test_a_resposta_do_login_nao_expoe_nada_de_credencial(): void
    {
        $this->cadastrar();

        $resposta = $this->logar();

        $this->assertSame(
            ['access_token', 'refresh_token', 'token_type', 'expires_in', 'user'],
            array_keys($resposta->json()),
        );
        $this->assertSame(['id', 'name', 'email', 'roles'], array_keys($resposta->json('user')));
        $this->assertStringNotContainsString('$2y$', $resposta->getContent());
        $this->assertStringNotContainsString(self::SENHA, $resposta->getContent());
    }

    public function test_autentica_com_o_email_em_outra_caixa(): void
    {
        $this->cadastrar();

        $this->logar(email: 'JOAO@SHOPMASTER.TEST')->assertOk();
    }

    public function test_recusa_senha_errada(): void
    {
        $this->cadastrar();

        $this->logar(senha: 'senha-errada-123')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'invalid_credentials');
    }

    public function test_recusa_email_inexistente(): void
    {
        $this->logar(email: 'ninguem@shopmaster.test')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'invalid_credentials');
    }

    public function test_a_resposta_e_identica_para_email_inexistente_e_senha_errada(): void
    {
        // RF-008. Se as duas diferissem em código, mensagem ou status, esta
        // rota viraria um verificador de "esta conta existe?".
        $this->cadastrar();

        $semConta = $this->logar(email: 'ninguem@shopmaster.test');
        $senhaErrada = $this->logar(senha: 'outra-senha-123');

        $this->assertSame($semConta->getStatusCode(), $senhaErrada->getStatusCode());
        $this->assertSame($semConta->json(), $senhaErrada->json());
    }

    public function test_recusa_login_sem_campos(): void
    {
        $this->postJson('/v1/auth', [])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');
    }

    // -----------------------------------------------------------------
    // POST /v1/auth/refresh — RF-004, uso único
    // -----------------------------------------------------------------

    public function test_troca_o_refresh_por_um_par_novo(): void
    {
        $this->cadastrar();
        $refresh = $this->logar()->json('refresh_token');

        $resposta = $this->postJson('/v1/auth/refresh', ['refresh_token' => $refresh]);

        $resposta->assertOk()->assertJsonPath('user.email', self::EMAIL);
        $this->assertNotSame($refresh, $resposta->json('refresh_token'));
    }

    public function test_o_refresh_so_serve_uma_vez(): void
    {
        $this->cadastrar();
        $refresh = $this->logar()->json('refresh_token');

        $this->postJson('/v1/auth/refresh', ['refresh_token' => $refresh])->assertOk();

        $this->postJson('/v1/auth/refresh', ['refresh_token' => $refresh])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'refresh_token_already_used');
    }

    public function test_o_access_token_antigo_continua_valendo_apos_o_refresh(): void
    {
        // Só o refresh é revogável. O access é stateless por projeto, e é o
        // TTL curto dele que limita o estrago — não uma lista consultada a
        // cada requisição.
        $this->cadastrar();
        $login = $this->logar();

        $this->postJson('/v1/auth/refresh', ['refresh_token' => $login->json('refresh_token')])->assertOk();

        $this->sessao($login->json('access_token'))->assertOk();
    }

    public function test_recusa_access_token_usado_como_refresh(): void
    {
        $this->cadastrar();

        $this->postJson('/v1/auth/refresh', ['refresh_token' => $this->logar()->json('access_token')])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'invalid_token');
    }

    public function test_recusa_refresh_forjado(): void
    {
        $this->postJson('/v1/auth/refresh', ['refresh_token' => 'nao.e.um.token'])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'invalid_token');
    }

    // -----------------------------------------------------------------
    // GET /v1/auth/session — o middleware auth.token
    // -----------------------------------------------------------------

    public function test_a_rota_protegida_devolve_o_portador_do_token(): void
    {
        $this->cadastrar();

        $this->sessao($this->logar()->json('access_token'))
            ->assertOk()
            ->assertJsonPath('email', self::EMAIL)
            ->assertJsonPath('roles', ['buyer']);
    }

    public function test_a_rota_protegida_recusa_requisicao_sem_token(): void
    {
        $resposta = $this->getJson('/v1/auth/session');

        $resposta->assertUnauthorized()->assertJsonPath('error.code', 'invalid_token');
        $this->assertSame('Bearer', $resposta->headers->get('WWW-Authenticate'));
    }

    public function test_a_rota_protegida_recusa_cabecalho_sem_bearer(): void
    {
        $this->withHeader('Authorization', 'Basic am9hbzpzZW5oYQ==')
            ->getJson('/v1/auth/session')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'invalid_token');
    }

    public function test_a_rota_protegida_recusa_refresh_token(): void
    {
        // Confusão de tipo: o refresh vive 30 dias e não pode abrir rota
        // protegida no lugar do access, que vive uma hora.
        $this->cadastrar();

        $this->sessao($this->logar()->json('refresh_token'))
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'invalid_token');
    }

    public function test_a_rota_protegida_recusa_token_adulterado(): void
    {
        $this->cadastrar();
        $token = $this->logar()->json('access_token');

        $this->sessao(substr($token, 0, -4).'AAAA')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'invalid_token');
    }

    // -----------------------------------------------------------------

    private function cadastrar(): void
    {
        $this->postJson('/v1/auth/register', [
            'name' => 'Joao Pedro',
            'email' => self::EMAIL,
            'password' => self::SENHA,
        ])->assertCreated();
    }

    private function logar(string $email = self::EMAIL, string $senha = self::SENHA): TestResponse
    {
        return $this->postJson('/v1/auth', ['email' => $email, 'password' => $senha]);
    }

    private function sessao(string $token): TestResponse
    {
        return $this->withHeader('Authorization', "Bearer {$token}")->getJson('/v1/auth/session');
    }
}
