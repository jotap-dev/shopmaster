<?php

declare(strict_types=1);

namespace Identity\Infrastructure\Hashing;

use Identity\Application\PasswordHasher;
use Identity\Domain\HashedPassword;
use Identity\Domain\PlainPassword;
use Illuminate\Hashing\BcryptHasher;

/**
 * Hash adaptativo de senha (RNF-020).
 *
 * O custo é deliberado: bcrypt é lento por projeto, e o número de rounds é
 * o que mantém caro um ataque offline conforme o hardware melhora. A suíte
 * roda com `BCRYPT_ROUNDS=4` (ver phpunit.xml) — sem isso, cada teste que
 * cadastra alguém pagaria ~100ms só de hash.
 */
final readonly class BcryptPasswordHasher implements PasswordHasher
{
    public function __construct(private BcryptHasher $hasher) {}

    public function hash(PlainPassword $plain): HashedPassword
    {
        return HashedPassword::fromHash($this->hasher->make($plain->value()));
    }

    public function verify(PlainPassword $plain, ?HashedPassword $hashed): bool
    {
        if ($hashed === null) {
            // Sem usuário, ainda assim queima o tempo de um bcrypt completo.
            //
            // Sem isto, "e-mail não existe" responde em microssegundos e
            // "senha errada" em ~100ms — e um cronômetro separa os dois casos
            // que o RF-008 faz questão de não distinguir na mensagem.
            //
            // É `make()` e não um hash fixo de constante porque assim o custo
            // acompanha sozinho o BCRYPT_ROUNDS configurado; um hash fixo
            // gravado com outro custo desequilibraria os tempos de novo.
            $this->hasher->make($plain->value());

            return false;
        }

        return $this->hasher->check($plain->value(), $hashed->value());
    }
}
