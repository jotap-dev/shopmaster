<?php

declare(strict_types=1);

namespace Identity\Interface\Http\Resources;

use Identity\Domain\AuthenticatedUser;
use Identity\Domain\PlatformRole;

/**
 * Quem o token diz ser.
 *
 * Não tem `name` porque o token não carrega nome — seria peso em toda
 * requisição para um dado que autorização nenhuma usa. Quem quer o perfil
 * completo busca o perfil.
 */
final class AuthenticatedUserResource
{
    /** @return array<string, mixed> */
    public static function from(AuthenticatedUser $user): array
    {
        return [
            'id' => $user->id,
            'email' => $user->email->value(),
            'roles' => array_map(fn (PlatformRole $papel): string => $papel->value, $user->roles),
        ];
    }
}
