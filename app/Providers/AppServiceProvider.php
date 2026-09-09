<?php

declare(strict_types=1);

namespace App\Providers;

use Identity\Application\PasswordHasher;
use Identity\Application\UserRepository;
use Identity\Infrastructure\Eloquent\EloquentUserRepository;
use Identity\Infrastructure\Hashing\BcryptPasswordHasher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Hashing\BcryptHasher;
use Illuminate\Support\ServiceProvider;
use Shared\Clock;
use Shared\EventBus;
use Shared\NullEventBus;
use Shared\SystemClock;

/**
 * O único lugar onde porta vira adapter.
 *
 * Nenhuma classe de Domain/ ou Application/ conhece implementação concreta —
 * só interface. Trocar Redis por outra coisa, ou o gateway de pagamento por
 * um segundo provedor, é editar este arquivo e mais nada.
 *
 * Bindings agrupados por bounded context, cada um com o porquê da escolha
 * quando ela não for óbvia.
 */
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // -----------------------------------------------------------------
        // Shared — cross-cutting, não é domínio de negócio de ninguém
        // -----------------------------------------------------------------

        // Sem listener registrado ainda: o use case já dispara o evento certo
        // desde o primeiro dia, e quando o listener nascer só este bind muda.
        $this->app->bind(EventBus::class, NullEventBus::class);

        // O tempo como porta: reserva expirada, cupom vencido e token expirado
        // são regra de negócio, e o teste precisa controlar "agora".
        $this->app->bind(Clock::class, SystemClock::class);

        // -----------------------------------------------------------------
        // Identity — Fase 1
        // -----------------------------------------------------------------

        $this->app->bind(UserRepository::class, fn (Application $app) => new EloquentUserRepository(
            $app->make(ConnectionResolverInterface::class),
        ));

        // O hasher é construído à mão, com o custo lido da config, em vez de
        // resolvido pelo container: `new BcryptHasher` sem argumentos usa 12
        // rounds fixos e ignoraria o BCRYPT_ROUNDS do ambiente — o que faria
        // a suíte pagar ~100ms de hash por teste que cadastra alguém.
        $this->app->bind(PasswordHasher::class, fn () => new BcryptPasswordHasher(
            new BcryptHasher(['rounds' => (int) config('hashing.bcrypt.rounds', 12)]),
        ));

        // Ainda por implementar nesta fase: TokenIssuer (login/refresh),
        // RefreshTokenBlacklist e o middleware auth.token.

        // -----------------------------------------------------------------
        // Catalog — Fase 2
        // -----------------------------------------------------------------
        // $this->app->bind(ProductRepository::class, EloquentProductRepository::class);
        // Store dedicado (Redis DB 2), para `cache:clear` do app não esvaziar
        // o catálogo inteiro:
        // $this->app->bind(CatalogCache::class, fn (Application $app) => new RedisCatalogCache(
        //     $app->make('cache')->store('catalog'),
        //     (int) config('shopmaster.catalog.cache_ttl'),
        // ));

        // -----------------------------------------------------------------
        // Inventory — Fase 3 · Pricing — Fase 4 · Cart — Fase 5
        // Shipping — Fase 6 · Ordering — Fase 7 · Payment — Fase 8
        // -----------------------------------------------------------------
        // Cada porta entra aqui junto do adapter, na mesma entrega em que
        // o contexto nasce. Ver docs/domains.md.
    }

    public function boot(): void
    {
        //
    }
}
