<?php

declare(strict_types=1);

namespace Tests\Unit\Identity\Support;

use Identity\Application\PasswordHasher;
use Identity\Domain\HashedPassword;
use Identity\Domain\PlainPassword;

/**
 * Hash de brincadeira, previsível: 'hash-de:' + a senha.
 *
 * `verify` conta as chamadas — inclusive as que recebem `null` —, que é como
 * o teste prova a equalização de tempo do RF-008.
 */
final class FakePasswordHasher implements PasswordHasher
{
    public int $verificacoes = 0;

    public int $verificacoesSemUsuario = 0;

    public function hash(PlainPassword $plain): HashedPassword
    {
        return HashedPassword::fromHash('hash-de:'.$plain->value());
    }

    public function verify(PlainPassword $plain, ?HashedPassword $hashed): bool
    {
        $this->verificacoes++;

        if ($hashed === null) {
            $this->verificacoesSemUsuario++;

            return false;
        }

        return $hashed->value() === 'hash-de:'.$plain->value();
    }
}
