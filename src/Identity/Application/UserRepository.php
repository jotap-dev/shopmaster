<?php

declare(strict_types=1);

namespace Identity\Application;

use Identity\Domain\EmailAddress;
use Identity\Domain\NewUser;
use Identity\Domain\RegisteredUser;

interface UserRepository
{
    public function existsByEmail(EmailAddress $email): bool;

    /**
     * Grava o usuário e devolve ele com id e data.
     *
     * **Contrato:** lança `EmailAlreadyRegistered` se a unicidade do e-mail
     * for violada. A verificação prévia com `existsByEmail()` é uma cortesia,
     * não uma garantia — entre a consulta e o INSERT cabe outro cadastro do
     * mesmo e-mail. Quem garante é a constraint `UNIQUE` do banco, e é
     * responsabilidade do adapter traduzir essa violação para cá.
     */
    public function add(NewUser $user): RegisteredUser;
}
