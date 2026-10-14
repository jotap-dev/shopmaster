<?php

declare(strict_types=1);

namespace Identity\Application;

use RuntimeException;

final class ProfileNotFound extends RuntimeException
{
    public static function create(): self
    {
        return new self('Conta nao encontrada.');
    }
}
