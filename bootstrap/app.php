<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

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
            // Fase 1 (Identity) — autenticação e papel de PLATAFORMA, que
            // viaja no token:
            // 'auth.token' => Shared\Auth\Interface\Http\Middleware\AuthenticateWithAccessToken::class,
            // 'role' => Shared\Auth\Interface\Http\Middleware\RequirePlatformRole::class,

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
            fn (Request $request) => $request->is('v1/*') || $request->expectsJson(),
        );
    })->create();
