<?php

use Identity\Interface\Http\Middleware\AuthenticateWithAccessToken;
use Identity\Interface\Http\Middleware\RequirePlatformRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Shared\Http\ErrorResource;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        // Sem prefixo automático: o versionamento é explícito no arquivo de
        // rotas (`/v1`), para a URL não virar `/api/v1`.
        apiPrefix: '',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            // Autenticação por access token. O papel de PLATAFORMA viaja
            // no token; a guarda `role` lê esses claims (RF-007).
            'auth.token' => AuthenticateWithAccessToken::class,
            'role' => RequirePlatformRole::class,

            // Fase 2 (Store) — papel de LOJA, resolvido por requisição a
            // partir do {storeId} da rota. Não está no token de propósito:
            // ver docs/auth.md.
            // 'store.role' => Store\Interface\Http\Middleware\RequireStoreRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Erro sob /v1 é sempre JSON, mesmo sem Accept: application/json —
        // sem isso um 500 devolve HTML para um cliente que só fala JSON.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('v1', 'v1/*') || $request->expectsJson(),
        );

        // RNF-061 — erro SEMPRE em {"error": {"code", "message"}}.
        //
        // Sem isto, três formatos conviveriam sob /v1: o nosso, o
        // {"message", "errors"} da validação do Laravel, e o {"message"} do
        // 404. Um cliente que trata erro por `error.code` quebraria em dois
        // deles, e o contrato do Swagger seria mentira.
        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('v1', 'v1/*')) {
                return null;
            }

            return response()->json(
                ErrorResource::withFields('validation_failed', 'Os dados enviados sao invalidos.', $e->errors()),
                422,
            );
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! $request->is('v1', 'v1/*')) {
                return null;
            }

            return response()->json(ErrorResource::of('not_found', 'Recurso nao encontrado.'), 404);
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            if (! $request->is('v1', 'v1/*')) {
                return null;
            }

            return response()->json(ErrorResource::of('method_not_allowed', 'Metodo nao permitido para este recurso.'), 405);
        });
    })->create();
