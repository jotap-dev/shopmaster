<?php

declare(strict_types=1);

namespace Identity\Domain;

use RuntimeException;

final class InvalidPersonName extends RuntimeException
{
    public static function empty(): self
    {
        return new self('O nome nao pode ser vazio.');
    }

    public static function tooLong(int $max): self
    {
        return new self("O nome excede o limite de {$max} caracteres.");
    }
}
