<?php

declare(strict_types=1);

namespace Identity\Application;

use DateTimeImmutable;

/**
 * A revogação de refresh tokens já usados (RF-004).
 *
 * Só o refresh é revogável. O access é stateless por projeto: conferir uma
 * lista a cada requisição desfaria a razão de ele ser stateless. É por isso
 * que o TTL do access é curto.
 */
interface RefreshTokenBlacklist
{
    /**
     * A entrada expira junto com o token: depois do prazo dele, guardar a
     * revogação não protege mais nada — só ocuparia memória para sempre.
     */
    public function revoke(string $tokenId, DateTimeImmutable $expiresAt): void;

    public function isRevoked(string $tokenId): bool;
}
