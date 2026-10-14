<?php

declare(strict_types=1);

namespace Identity\Interface\Http\Resources;

use Identity\Domain\UserIdentity;

final class UserIdentityResource
{
    /** @return array<string, mixed> */
    public static function from(UserIdentity $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name->value(),
            'email' => $user->email->value(),
            'roles' => array_map(
                static fn ($papel): string => $papel->value,
                $user->roles,
            ),
        ];
    }
}
