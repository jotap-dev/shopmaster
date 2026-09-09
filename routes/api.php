<?php

declare(strict_types=1);

use Identity\Interface\Http\Controllers\RegisterUserController;
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

    // Route::post('/auth', [AuthController::class, 'store']);
    // Route::post('/auth/refresh', [AuthController::class, 'refresh']);

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
