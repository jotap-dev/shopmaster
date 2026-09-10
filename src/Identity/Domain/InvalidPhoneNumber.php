<?php

declare(strict_types=1);

namespace Identity\Domain;

use RuntimeException;

final class InvalidPhoneNumber extends RuntimeException
{
    public static function wrongLength(): self
    {
        return new self('O telefone precisa ter DDD mais 8 ou 9 digitos.');
    }

    public static function unknownAreaCode(string $ddd): self
    {
        return new self("O DDD {$ddd} nao existe no plano nacional.");
    }

    public static function mobileMustStartWithNine(): self
    {
        return new self('Celular com 9 digitos precisa comecar com 9.');
    }
}
