<?php

declare(strict_types=1);

namespace Identity\Domain;

/**
 * A identidade **mais** o hash da senha — o único carrier que o toca.
 *
 * Existe apenas no caminho do login, entre o repositório e a verificação.
 * Nenhum Resource recebe isto (RULE 4), e o `HashedPassword` redige o valor
 * na serialização, de modo que nem um log de exceção o imprime.
 */
final readonly class UserCredentials
{
    public function __construct(
        public UserIdentity $user,
        public HashedPassword $password,
    ) {}
}
