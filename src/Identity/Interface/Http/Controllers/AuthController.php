<?php

declare(strict_types=1);

namespace Identity\Interface\Http\Controllers;

use Identity\Application\Authenticate;
use Identity\Application\AuthUserNotFound;
use Identity\Application\ExpiredToken;
use Identity\Application\InvalidCredentials;
use Identity\Application\InvalidToken;
use Identity\Application\RefreshAccessToken;
use Identity\Application\RefreshTokenAlreadyUsed;
use Identity\Interface\Http\Requests\LoginRequest;
use Identity\Interface\Http\Requests\RefreshTokenRequest;
use Identity\Interface\Http\Resources\AuthSessionResource;
use Illuminate\Http\JsonResponse;
use Shared\Http\ErrorResource;

final class AuthController
{
    /** RF-003 — login. */
    public function store(LoginRequest $request, Authenticate $authenticate): JsonResponse
    {
        try {
            $sessao = $authenticate->handle(
                $request->string('email')->toString(),
                $request->string('password')->toString(),
            );
        } catch (InvalidCredentials $e) {
            return response()->json(ErrorResource::of('invalid_credentials', $e->getMessage()), 401);
        }

        return response()->json(AuthSessionResource::from($sessao));
    }

    /** RF-004 — troca do refresh, que é de uso único. */
    public function refresh(RefreshTokenRequest $request, RefreshAccessToken $refresh): JsonResponse
    {
        try {
            $sessao = $refresh->handle($request->string('refresh_token')->toString());
        } catch (RefreshTokenAlreadyUsed $e) {
            // Este é o único que se distingue, e de propósito: quem recebe
            // isto **tinha** um refresh legítimo que já foi trocado. Ou o
            // cliente repetiu a chamada, ou o token vazou e alguém chegou
            // antes — e nos dois casos a ação é a mesma, refazer o login.
            return response()->json(ErrorResource::of('refresh_token_already_used', $e->getMessage()), 401);
        } catch (ExpiredToken $e) {
            return response()->json(ErrorResource::of('expired_token', $e->getMessage()), 401);
        } catch (InvalidToken|AuthUserNotFound $e) {
            // Conta apagada sai como `invalid_token`, não como um código
            // próprio: "esta conta não existe mais" é informação sobre a base.
            return response()->json(ErrorResource::of('invalid_token', 'Token invalido.'), 401);
        }

        return response()->json(AuthSessionResource::from($sessao));
    }
}
