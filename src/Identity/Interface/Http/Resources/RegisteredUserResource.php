<?php

declare(strict_types=1);

namespace Identity\Interface\Http\Resources;

use DateTimeInterface;
use Identity\Domain\PlatformRole;
use Identity\Domain\RegisteredUser;

/**
 * O usuário recém-cadastrado, como sai na resposta.
 *
 * Note o que não está aqui: senha, hash, e qualquer coisa que a tela de
 * cadastro não pediu (RULE 4). O `RegisteredUser` já nasce sem a senha, então
 * não há o que esquecer de remover.
 */
final class RegisteredUserResource
{
    /** @return array<string, mixed> */
    public static function from(RegisteredUser $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name->value(),
            'email' => $user->email->value(),
            'roles' => array_map(fn (PlatformRole $role): string => $role->value, $user->roles),
            'registered_at' => $user->registeredAt->format(DateTimeInterface::ATOM),
        ];
    }
}
