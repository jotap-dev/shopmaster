<?php

declare(strict_types=1);

use Identity\Interface\Http\Controllers\AuthController;
use Identity\Interface\Http\Controllers\MyProfileController;
use Identity\Interface\Http\Controllers\RegisterUserController;
use Identity\Interface\Http\Controllers\SessionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
|
| Uma API REST comum — não é BFF. Comprador, lojista e plataforma consomem
| os MESMOS recursos; o que os separa é autorização, não formato de resposta.
|
| Três famílias, e a proteção de cada uma:
|
|   /v1/...                        comprador   pública ou `auth.token`
|   /v1/stores/{storeId}/...       lojista     `auth.token` + `store.role:{papel}`
|   /v1/admin/...                  plataforma  `auth.token` + `role:platform_admin`
|
| O `{storeId}` na URL não é decorativo: é dele que o middleware `store.role`
| tira qual loja consultar. Papel de loja NÃO viaja no token — ver docs/auth.md.
|
| Convenções:
|   - middleware aninhado dentro do prefixo, nunca `prefix()` repetido;
|   - toda rota nova entra no docs/openapi/openapi.yaml na MESMA entrega.
|
| As rotas de cada contexto entram aqui conforme as fases do plano — ver
| docs/domains.md.
|
*/

Route::prefix('v1')->group(function (): void {

    // Ping do contrato: prova que o roteamento /v1 está de pé e que erro
    // sob /v1 sai em JSON. Substituível assim que houver rota de verdade.
    Route::get('/', fn () => response()->json([
        'name' => config('app.name'),
        'version' => 'v1',
        'docs' => url('/docs'),
    ]));

    // -----------------------------------------------------------------
    // Identity — Fase 1
    // -----------------------------------------------------------------

    // RF-001 — cadastro. Aberta de propósito (é a porta de entrada), e por
    // isso limitada por IP: sem o throttle, é onde alguém enumera e-mails
    // cadastrados a partir do 422 de duplicidade (RNF-025).
    Route::post('/auth/register', [RegisterUserController::class, 'store'])
        ->middleware('throttle:10,1');

    // RF-003 — login. Limitada por IP: é a rota onde se tenta senha em
    // massa, e o custo do bcrypt não é defesa suficiente sozinho (RNF-025).
    Route::post('/auth', [AuthController::class, 'store'])
        ->middleware('throttle:10,1');

    // RF-004 — troca do refresh, de uso único. Limite mais folgado que o do
    // login: um cliente legítimo com sessão longa passa por aqui a cada hora.
    Route::post('/auth/refresh', [AuthController::class, 'refresh'])
        ->middleware('throttle:30,1');

    // RNF-025 — rate limit por usuário nas rotas autenticadas. O throttle do
    // Laravel usa o id do usuário quando há um autenticado e cai para o IP
    // quando não há; como o `auth.token` roda antes, aqui é por usuário.
    //
    // Não é só higiene: o `PATCH /me` com `document` responde
    // "já cadastrado", e CPF é enumerável — sem limite, dá para varrer o
    // espaço de CPFs válidos e descobrir quem tem conta na plataforma.
    Route::middleware(['auth.token', 'throttle:60,1'])->group(function (): void {
        // Quem sou eu, segundo este token. Prova o middleware de ponta a
        // ponta e deixa o cliente conferir a validade sem efeito colateral.
        Route::get('/auth/session', [SessionController::class, 'show']);

        // RF-005 — minha conta. O id vem do token, nunca da rota nem do
        // corpo: não há como apontar para a conta de outra pessoa.
        Route::get('/me', [MyProfileController::class, 'show']);
        Route::patch('/me', [MyProfileController::class, 'update']);
    });

    // -----------------------------------------------------------------
    // Catálogo público — Fase 3
    // -----------------------------------------------------------------
    // Route::get('/products', [ProductCatalogController::class, 'index']);
    // Route::get('/products/{slug}', [ProductController::class, 'show']);
    // Route::get('/categories', [CategoryController::class, 'index']);
    // Route::get('/stores/{slug}', [StorefrontController::class, 'show']);

    // -----------------------------------------------------------------
    // Comprador autenticado — Fases 6 a 11
    // -----------------------------------------------------------------
    // Route::middleware('auth.token')->group(function (): void {
    //     Route::post('/checkout', [CheckoutController::class, 'store']);
    //     Route::get('/orders', [OrderController::class, 'index']);
    //     Route::get('/me/favorites', [FavoriteController::class, 'index']);
    //     Route::post('/stores', [StoreController::class, 'store']);
    // });

    // -----------------------------------------------------------------
    // Lojista — o {storeId} da rota é o que o `store.role` resolve
    // -----------------------------------------------------------------
    // Route::prefix('stores/{storeId}')->middleware('auth.token')->group(function (): void {
    //     Route::middleware('store.role:admin')->group(function (): void {
    //         Route::post('/products', [StoreProductController::class, 'store']);
    //         Route::get('/orders', [StoreOrderController::class, 'index']);
    //     });
    //
    //     Route::middleware('store.role:owner')->group(function (): void {
    //         Route::post('/members', [StoreMemberController::class, 'store']);
    //     });
    //
    //     Route::middleware('store.role:finance')->group(function (): void {
    //         Route::get('/statement', [StoreStatementController::class, 'show']);
    //     });
    // });

    // -----------------------------------------------------------------
    // Plataforma
    // -----------------------------------------------------------------
    // Route::prefix('admin')->middleware(['auth.token', 'role:platform_admin'])->group(function (): void {
    //     Route::post('/stores/{storeId}/approve', [AdminStoreController::class, 'approve']);
    //     Route::post('/categories', [AdminCategoryController::class, 'store']);
    // });
});
