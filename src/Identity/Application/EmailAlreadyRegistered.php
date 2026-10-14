<?php

declare(strict_types=1);

namespace Identity\Application;

use Identity\Domain\EmailAddress;
use RuntimeException;

final class EmailAlreadyRegistered extends RuntimeException
{
    public static function for(EmailAddress $email): self
    {
        return new self("O e-mail '{$email->value()}' ja esta cadastrado.");
    }
}
