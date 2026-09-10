<?php

declare(strict_types=1);

namespace Identity\Infrastructure\Cache;

use DateTimeImmutable;
use Identity\Application\RefreshTokenBlacklist;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * A revogação de refresh tokens, no Redis.
 *
 * Usa um **store dedicado** (`auth`, com DB próprio — ver config/cache.php).
 * No store padrão, um `php artisan cache:clear` de rotina ressuscitaria todo
 * refresh token já queimado, e ninguém ligaria os dois fatos.
 *
 * A entrada expira junto com o token: depois do prazo dele, a revogação não
 * protege mais nada, e guardá-la para sempre só encheria a memória.
 */
final readonly class RedisRefreshTokenBlacklist implements RefreshTokenBlacklist
{
    private const PREFIXO = 'refresh-revogado:';

    public function __construct(private Cache $cache) {}

    public function revoke(string $tokenId, DateTimeImmutable $expiresAt): void
    {
        $this->cache->put(self::PREFIXO.$tokenId, true, $expiresAt);
    }

    public function isRevoked(string $tokenId): bool
    {
        return $this->cache->has(self::PREFIXO.$tokenId);
    }
}
