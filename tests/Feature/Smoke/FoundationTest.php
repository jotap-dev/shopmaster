<?php

declare(strict_types=1);

namespace Tests\Feature\Smoke;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Shared\Clock;
use Shared\EventBus;
use Shared\NullEventBus;
use Shared\SystemClock;
use Tests\TestCase;

/**
 * Prova que a fundação da Fase 0 está de pé: banco, Redis, rotas, contrato
 * e os bindings do container. É o teste que quebra quando o ambiente de
 * alguém está diferente do combinado — antes de a feature quebrar por isso.
 */
final class FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_suite_roda_contra_o_banco_de_teste_e_nao_o_de_desenvolvimento(): void
    {
        $this->assertSame(
            env('DB_TEST_DATABASE', 'shopmaster_test'),
            config('database.connections.pgsql.database'),
        );
    }

    public function test_o_banco_e_postgres_com_as_extensoes_da_fundacao(): void
    {
        $this->assertSame('pgsql', config('database.default'));

        $extensoes = collect(\DB::select('select extname from pg_extension'))
            ->pluck('extname')
            ->all();

        foreach (['pgcrypto', 'citext', 'pg_trgm'] as $extensao) {
            $this->assertContains($extensao, $extensoes, "Extensao {$extensao} ausente — rode php artisan migrate.");
        }
    }

    public function test_o_redis_responde_e_o_catalogo_tem_db_proprio(): void
    {
        Redis::connection('catalog')->set('smoke:catalog', 'ok');

        $this->assertSame('ok', Redis::connection('catalog')->get('smoke:catalog'));

        // Cada uso tem seu DB: um `cache:clear` do app não pode derrubar
        // carrinho de visitante nem soltar reserva de estoque.
        $this->assertNotSame(
            config('database.redis.catalog.database'),
            config('database.redis.cart.database'),
        );

        Redis::connection('catalog')->del('smoke:catalog');
    }

    public function test_a_raiz_da_v1_se_identifica(): void
    {
        $resposta = $this->getJson('/v1');

        $resposta->assertOk()
            ->assertJsonStructure(['name', 'version', 'docs']);

        $this->assertSame('v1', $resposta->json('version'));
    }

    public function test_erro_sob_v1_sai_em_json_no_formato_do_projeto(): void
    {
        $resposta = $this->get('/v1/rota-que-nao-existe');

        $resposta->assertNotFound()
            ->assertJsonPath('error.code', 'not_found');

        $this->assertStringContainsString('application/json', (string) $resposta->headers->get('Content-Type'));
    }

    public function test_metodo_nao_permitido_tambem_respeita_o_formato_de_erro(): void
    {
        $this->putJson('/v1')
            ->assertStatus(405)
            ->assertJsonPath('error.code', 'method_not_allowed');
    }

    public function test_o_health_check_responde(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_o_swagger_ui_e_o_contrato_estao_publicados(): void
    {
        $this->get('/docs')->assertOk()->assertSee('swagger-ui', escape: false);

        $contrato = $this->get('/docs/openapi.yaml');

        $contrato->assertOk();
        $this->assertStringContainsString('openapi: 3.1.0', $contrato->getContent());
        $this->assertStringContainsString('ShopMaster API', $contrato->getContent());
    }

    public function test_as_portas_do_shared_estao_ligadas_aos_adapters(): void
    {
        $this->assertInstanceOf(NullEventBus::class, $this->app->make(EventBus::class));
        $this->assertInstanceOf(SystemClock::class, $this->app->make(Clock::class));
    }
}
