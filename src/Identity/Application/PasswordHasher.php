<?php

declare(strict_types=1);

namespace Identity\Application;

use Identity\Domain\HashedPassword;
use Identity\Domain\PlainPassword;

interface PasswordHasher
{
    public function hash(PlainPassword $plain): HashedPassword;

    /**
     * Confere a senha contra o hash.
     *
     * **`$hashed` é anulável de propósito.** Quando o e-mail não existe, o
     * use case chama isto com `null` em vez de retornar cedo, e o adapter
     * queima o mesmo tempo antes de devolver `false`. Sem isso, "e-mail
     * inexistente" responde em microssegundos e "senha errada" em ~100ms —
     * e essa diferença é uma lista de e-mails cadastrados para quem medir,
     * o que anularia justamente o que o RF-008 protege.
     */
    public function verify(PlainPassword $plain, ?HashedPassword $hashed): bool;
}
