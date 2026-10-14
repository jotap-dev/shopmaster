<?php

declare(strict_types=1);

namespace Customer\Domain;

use RuntimeException;

final class InvalidPostalCode extends RuntimeException
{
    public static function wrongLength(): self
    {
        return new self('O CEP precisa ter 8 digitos.');
    }
}
