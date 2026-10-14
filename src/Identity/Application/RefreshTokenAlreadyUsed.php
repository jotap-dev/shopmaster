<?php

declare(strict_types=1);

namespace Identity\Application;

use RuntimeException;

final class RefreshTokenAlreadyUsed extends RuntimeException
{
    public static function create(): self
    {
        return new self('Este refresh token ja foi usado.');
    }
}
