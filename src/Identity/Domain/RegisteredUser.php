<?php

declare(strict_types=1);

namespace Identity\Domain;

use DateTimeImmutable;

/**
 * O usuário como ele existe depois de gravado — com id e data.
 *
 * Note o que **não** está aqui: a senha, em qualquer forma. Este é o objeto
 * que atravessa a camada de interface até o Resource, e o que não existe
 * nele não tem como vazar na resposta (RULE 4).
 */
final readonly class RegisteredUser
{
    /** @param list<PlatformRole> $roles */
    public function __construct(
        public string $id,
        public PersonName $name,
        public EmailAddress $email,
        public array $roles,
        public DateTimeImmutable $registeredAt,
    ) {}

    public function hasRole(PlatformRole $role): bool
    {
        return in_array($role, $this->roles, strict: true);
    }
}
