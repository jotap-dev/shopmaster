<?php

declare(strict_types=1);

namespace Identity\Application;

use RuntimeException;

final class AuthUserNotFound extends RuntimeException
{
    public static function create(): self
    {
        return new self('A conta deste token nao existe mais.');
    }
}
