<?php

declare(strict_types=1);

namespace Customer\Application;

use RuntimeException;

final class AddressNotFound extends RuntimeException
{
    public static function create(): self
    {
        return new self('Endereco nao encontrado.');
    }
}
