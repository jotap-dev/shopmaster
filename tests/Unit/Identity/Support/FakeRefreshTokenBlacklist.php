<?php

declare(strict_types=1);

namespace Tests\Unit\Identity\Support;

use DateTimeImmutable;
use Identity\Application\RefreshTokenBlacklist;

final class FakeRefreshTokenBlacklist implements RefreshTokenBlacklist
{
    /** @var array<string, DateTimeImmutable> */
    public array $revogados = [];

    public function revoke(string $tokenId, DateTimeImmutable $expiresAt): void
    {
        $this->revogados[$tokenId] = $expiresAt;
    }

    public function isRevoked(string $tokenId): bool
    {
        return isset($this->revogados[$tokenId]);
    }
}
