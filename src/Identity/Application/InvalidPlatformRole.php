<?php

declare(strict_types=1);

namespace Identity\Application;

use RuntimeException;

final class InvalidPlatformRole extends RuntimeException
{
    public static function unknown(string $role): self
    {
        return new self("O papel '{$role}' nao existe.");
    }

    public static function empty(): self
    {
        return new self('A conta precisa ter ao menos um papel de plataforma.');
    }
}
