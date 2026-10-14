<?php

declare(strict_types=1);

namespace Identity\Interface\Http\Controllers;

use Identity\Application\CannotRevokeOwnAdmin;
use Identity\Application\SetPlatformRoles;
use Identity\Application\UserNotFound;
use Identity\Domain\AuthenticatedUser;
use Identity\Interface\Http\Requests\UpdatePlatformRolesRequest;
use Identity\Interface\Http\Resources\UserIdentityResource;
use Illuminate\Http\JsonResponse;
use Shared\Http\ErrorResource;

/**
 * RF-007 — conceder e revogar papéis de plataforma.
 *
 * Protegida por `role:platform_admin`. O conjunto inteiro de papéis é
 * substituído; o efeito no token do alvo só aparece no próximo refresh.
 */
final class AdminUserRolesController
{
    public function update(
        UpdatePlatformRolesRequest $request,
        string $userId,
        AuthenticatedUser $actor,
        SetPlatformRoles $setRoles,
    ): JsonResponse {
        try {
            $usuario = $setRoles->handle(
                actorId: $actor->id,
                targetUserId: $userId,
                roles: $request->input('roles', []),
            );
        } catch (UserNotFound $e) {
            return response()->json(ErrorResource::of('not_found', $e->getMessage()), 404);
        } catch (CannotRevokeOwnAdmin $e) {
            return response()->json(ErrorResource::of('cannot_revoke_own_admin', $e->getMessage()), 409);
        }

        return response()->json(UserIdentityResource::from($usuario));
    }
}
