<?php

declare(strict_types=1);

namespace Identity\Interface\Http\Controllers;

use Identity\Domain\AuthenticatedUser;
use Identity\Interface\Http\Resources\AuthenticatedUserResource;
use Illuminate\Http\JsonResponse;

/**
 * Quem sou eu, segundo este token.
 *
 * Serve ao cliente que quer saber se o token ainda vale sem provocar efeito
 * colateral, e é a rota que prova o middleware `auth.token` de ponta a ponta.
 *
 * O `AuthenticatedUser` chega por injeção porque o middleware o registrou no
 * container da requisição — nenhum controller precisa reabrir o token.
 */
final class SessionController
{
    public function show(AuthenticatedUser $user): JsonResponse
    {
        return response()->json(AuthenticatedUserResource::from($user));
    }
}
