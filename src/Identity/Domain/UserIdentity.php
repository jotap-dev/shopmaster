<?php

declare(strict_types=1);

namespace Identity\Domain;

/**
 * Quem a pessoa é, sem nada de credencial.
 *
 * É o que o login devolve, o que o refresh recarrega do banco e o que entra
 * nos claims do token. Não carrega senha nem hash — nem por acidente.
 */
final readonly class UserIdentity
{
    /** @param list<PlatformRole> $roles */
    public function __construct(
        public string $id,
        public PersonName $name,
        public EmailAddress $email,
        public array $roles,
    ) {}

    public function hasRole(PlatformRole $role): bool
    {
        return in_array($role, $this->roles, strict: true);
    }
}
