<?php

declare(strict_types=1);

namespace Identity\Application;

use Identity\Domain\PlatformRole;
use Identity\Domain\UserIdentity;

/**
 * RF-007 — substituir o conjunto de papéis de plataforma de um usuário.
 *
 * O conjunto inteiro é enviado de uma vez: concede o que falta e revoga o
 * que sobrou. Papel que já estava no conjunto ou que já não estava é
 * idempotente — reenviar a mesma lista não é erro.
 *
 * O efeito no token só aparece no **próximo refresh**: o access atual
 * continua com os claims antigos até expirar (RULE 7 / docs/auth.md).
 */
final class SetPlatformRoles
{
    public function __construct(private UserRepository $users) {}

    /**
     * @param  list<string>  $roles
     *
     * @throws UserNotFound
     * @throws InvalidPlatformRole
     * @throws CannotRevokeOwnAdmin
     */
    public function handle(string $actorId, string $targetUserId, array $roles): UserIdentity
    {
        if ($roles === []) {
            throw InvalidPlatformRole::empty();
        }

        $desejados = [];

        foreach ($roles as $papel) {
            $desejados[] = PlatformRole::tryFrom($papel) ?? throw InvalidPlatformRole::unknown($papel);
        }

        $desejados = array_values(array_unique($desejados, SORT_REGULAR));

        $alvo = $this->users->findIdentityById($targetUserId)
            ?? throw UserNotFound::create();

        $tinhaAdmin = $alvo->hasRole(PlatformRole::PlatformAdmin);
        $ficaComAdmin = in_array(PlatformRole::PlatformAdmin, $desejados, strict: true);

        // Sem isso, o último admin se rebaixa e a plataforma fica sem quem
        // conceda o papel de novo — não há recovery pela API.
        if ($actorId === $targetUserId && $tinhaAdmin && ! $ficaComAdmin) {
            throw CannotRevokeOwnAdmin::create();
        }

        return $this->users->replacePlatformRoles($targetUserId, $desejados);
    }
}
