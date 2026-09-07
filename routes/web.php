<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Swagger UI servindo docs/openapi/openapi.yaml — o contrato é mantido à
// mão, não gerado por anotação no código.
//
// Sem sessão/CSRF: é leitura pura de documentação e não deveria depender
// de sessão (evita quebrar se o store de sessão não estiver disponível).
Route::get('/docs', fn () => view('docs.swagger'))
    ->name('docs.swagger')
    ->withoutMiddleware('web');

Route::get('/docs/openapi.yaml', fn () => response(
    file_get_contents(base_path('docs/openapi/openapi.yaml')),
    200,
    ['Content-Type' => 'application/yaml; charset=utf-8'],
))->name('docs.openapi')->withoutMiddleware('web');

// A raiz aponta para a documentação: não há front-end neste projeto.
Route::get('/', fn () => redirect('/docs'))->withoutMiddleware('web');
