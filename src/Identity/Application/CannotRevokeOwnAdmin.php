<?php

declare(strict_types=1);

namespace Identity\Application;

use RuntimeException;

final class CannotRevokeOwnAdmin extends RuntimeException
{
    public static function create(): self
    {
        return new self('Um platform_admin nao pode revogar o proprio papel.');
    }
}
