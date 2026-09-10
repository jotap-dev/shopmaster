<?php

declare(strict_types=1);

namespace Identity\Interface\Http\Resources;

use Identity\Domain\AuthenticatedSession;
use Identity\Domain\PlatformRole;

/** O que sai de um login e de um refresh — os dois devolvem a mesma coisa. */
final class AuthSessionResource
{
    /** @return array<string, mixed> */
    public static function from(AuthenticatedSession $session): array
    {
        return [
            'access_token' => $session->tokens->accessToken,
            'refresh_token' => $session->tokens->refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => $session->tokens->expiresIn,
            'user' => [
                'id' => $session->user->id,
                'name' => $session->user->name->value(),
                'email' => $session->user->email->value(),
                'roles' => array_map(fn (PlatformRole $papel): string => $papel->value, $session->user->roles),
            ],
        ];
    }
}
