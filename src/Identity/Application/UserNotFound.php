<?php

declare(strict_types=1);

namespace Identity\Application;

use RuntimeException;

final class UserNotFound extends RuntimeException
{
    public static function create(): self
    {
        return new self('Usuario nao encontrado.');
    }
}
