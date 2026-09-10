<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use DateTimeImmutable;
use Identity\Application\RefreshTokenBlacklist;
use Identity\Infrastructure\Cache\RedisRefreshTokenBlacklist;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Contra o Redis de verdade (DB de teste, ver phpunit.xml) — é o que um fake
 * não prova: que o TTL existe e que o store é mesmo o dedicado.
 */
final class RedisRefreshTokenBlacklistTest extends TestCase
{
    private RefreshTokenBlacklist $lista;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lista = $this->app->make(RefreshTokenBlacklist::class);
        Cache::store('auth')->flush();
    }

    public function test_um_token_novo_nao_esta_revogado(): void
    {
        $this->assertFalse($this->lista->isRevoked('jti-nunca-visto'));
    }

    public function test_revoga_e_reconhece_a_revogacao(): void
    {
        $this->lista->revoke('jti-1', new DateTimeImmutable('+30 days'));

        $this->assertTrue($this->lista->isRevoked('jti-1'));
    }

    public function test_a_revogacao_nao_alcanca_outro_token(): void
    {
        $this->lista->revoke('jti-1', new DateTimeImmutable('+30 days'));

        $this->assertFalse($this->lista->isRevoked('jti-2'));
    }

    public function test_a_entrada_expira_junto_com_o_token(): void
    {
        $this->lista->revoke('jti-curto', new DateTimeImmutable('+30 days'));

        $ttl = Cache::store('auth')->getStore()->connection()->ttl(
            config('cache.prefix').'refresh-revogado:jti-curto'
        );

        $this->assertGreaterThan(0, $ttl, 'A entrada precisa ter TTL, senao a lista cresce para sempre.');
        $this->assertLessThanOrEqual(30 * 24 * 60 * 60, $ttl);
    }

    public function test_token_ja_vencido_nao_precisa_ser_guardado(): void
    {
        $this->lista->revoke('jti-vencido', new DateTimeImmutable('-1 hour'));

        // Depois do prazo do token, a revogação não protege mais nada.
        $this->assertFalse($this->lista->isRevoked('jti-vencido'));
    }

    public function test_usa_o_store_dedicado_e_nao_o_padrao(): void
    {
        // Se isto cair no store padrão, um `cache:clear` de rotina
        // ressuscita todo refresh token já queimado.
        $this->lista->revoke('jti-isolado', new DateTimeImmutable('+30 days'));

        $this->assertTrue(Cache::store('auth')->has('refresh-revogado:jti-isolado'));
        $this->assertFalse(Cache::store('array')->has('refresh-revogado:jti-isolado'));
    }

    public function test_o_adapter_esta_ligado_a_implementacao_do_redis(): void
    {
        $this->assertInstanceOf(RedisRefreshTokenBlacklist::class, $this->lista);
    }
}
