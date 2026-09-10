<?php

declare(strict_types=1);

namespace Customer\Domain;

use RuntimeException;

final class InvalidBrazilianState extends RuntimeException
{
    public static function unknown(string $value): self
    {
        return new self("A UF '{$value}' nao existe.");
    }
}
