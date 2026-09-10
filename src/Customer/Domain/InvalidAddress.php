<?php

declare(strict_types=1);

namespace Customer\Domain;

use RuntimeException;

final class InvalidAddress extends RuntimeException
{
    public static function emptyField(string $field): self
    {
        return new self("O campo {$field} e obrigatorio.");
    }

    public static function tooLong(string $field, int $max): self
    {
        return new self("O campo {$field} nao pode ter mais de {$max} caracteres.");
    }
}
