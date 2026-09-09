<?php

declare(strict_types=1);

namespace Identity\Domain;

use RuntimeException;

final class InvalidEmailAddress extends RuntimeException
{
    public static function malformed(string $value): self
    {
        return new self("Endereco de e-mail invalido: '{$value}'.");
    }

    public static function tooLong(int $max): self
    {
        return new self("Endereco de e-mail excede o limite de {$max} caracteres.");
    }
}
