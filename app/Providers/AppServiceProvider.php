<?php

declare(strict_types=1);

namespace App\Providers;

use Customer\Application\AddressRepository;
use Customer\Infrastructure\Eloquent\EloquentAddressRepository;
use Identity\Application\PasswordHasher;
use Identity\Application\RefreshTokenBlacklist;
use Identity\Application\TokenIssuer;
use Identity\Application\UserRepository;
use Identity\Infrastructure\Cache\RedisRefreshTokenBlacklist;
use Identity\Infrastructure\Eloquent\EloquentUserRepository;
use Identity\Infrastructure\Hashing\BcryptPasswordHasher;
use Identity\Infrastructure\Jwt\HmacJwtTokenIssuer;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Hashing\BcryptHasher;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;
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
            $app->make(LoggerInterface::class),
        ));

        // O hasher é construído à mão, com o custo lido da config, em vez de
        // resolvido pelo container: `new BcryptHasher` sem argumentos usa 12
        // rounds fixos e ignoraria o BCRYPT_ROUNDS do ambiente — o que faria
        // a suíte pagar ~100ms de hash por teste que cadastra alguém.
        $this->app->bind(PasswordHasher::class, fn () => new BcryptPasswordHasher(
            new BcryptHasher(['rounds' => (int) config('hashing.bcrypt.rounds', 12)]),
        ));

        $this->app->bind(TokenIssuer::class, fn (Application $app) => new HmacJwtTokenIssuer(
            secret: (string) config('jwt.secret'),
            issuer: (string) config('jwt.issuer'),
            accessTokenTtlInSeconds: (int) config('jwt.ttl'),
            refreshTokenTtlInSeconds: (int) config('jwt.refresh_ttl'),
            clock: $app->make(Clock::class),
        ));

        // Store `auth` e não o padrão: um `cache:clear` de rotina no store
        // padrão ressuscitaria todo refresh token já revogado.
        $this->app->bind(RefreshTokenBlacklist::class, fn (Application $app) => new RedisRefreshTokenBlacklist(
            $app->make('cache')->store('auth'),
        ));

        // -----------------------------------------------------------------
        // Customer — Fase 1 (endereços) / Fase 3 (favoritos)
        // -----------------------------------------------------------------

        $this->app->bind(AddressRepository::class, fn (Application $app) => new EloquentAddressRepository(
            $app->make(ConnectionResolverInterface::class),
        ));

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
