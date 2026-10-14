<?php

declare(strict_types=1);

namespace Identity\Domain;

/**
 * O portador do access token, montado **só** a partir dos claims assinados.
 *
 * Não há consulta ao banco para autenticar uma requisição: os papéis de
 * plataforma vêm do token (RULE 7). A contrapartida está documentada em
 * docs/auth.md — revogar um papel só tem efeito quando o access expira.
 *
 * Não tem nome porque o token não carrega nome: seria peso em toda
 * requisição para um dado que autorização nenhuma usa.
 */
final readonly class AuthenticatedUser
{
    /** @param list<PlatformRole> $roles */
    public function __construct(
        public string $id,
        public EmailAddress $email,
        public array $roles,
    ) {}

    public function hasRole(PlatformRole $role): bool
    {
        return in_array($role, $this->roles, strict: true);
    }
}
