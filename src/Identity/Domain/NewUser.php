<?php

declare(strict_types=1);

namespace Identity\Domain;

/**
 * Um usuário pronto para ser gravado, mas que ainda não tem id.
 *
 * É aqui que mora a regra "todo mundo nasce comprador" (RF-001): não no
 * Controller, não numa coluna com valor padrão no banco, não num seeder.
 * O dia em que o cadastro puder nascer com outro papel, muda este arquivo.
 */
final readonly class NewUser
{
    /** @param list<PlatformRole> $roles */
    private function __construct(
        public PersonName $name,
        public EmailAddress $email,
        public HashedPassword $password,
        public array $roles,
    ) {}

    public static function register(PersonName $name, EmailAddress $email, HashedPassword $password): self
    {
        return new self($name, $email, $password, [PlatformRole::Buyer]);
    }
}
