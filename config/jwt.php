<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Segredo de assinatura
    |--------------------------------------------------------------------------
    |
    | HS256 assinado na própria aplicação — sem dependência externa, sem
    | par de chaves para rotacionar. Cai para a APP_KEY quando JWT_SECRET
    | não está definido, para a máquina de dev recém-clonada já funcionar;
    | em servidor, JWT_SECRET é obrigatório e vive em docs/deploy.md.
    |
    | Trocar o segredo invalida todos os tokens em circulação.
    |
    */

    'secret' => env('JWT_SECRET', env('APP_KEY')),

    'issuer' => env('JWT_ISSUER', 'shopmaster'),

    /*
    |--------------------------------------------------------------------------
    | Tempo de vida, em segundos
    |--------------------------------------------------------------------------
    |
    | O access token trafega em toda requisição e é stateless: não há como
    | revogá-lo antes de expirar, então o TTL é curto. O refresh é de uso
    | único — ao ser usado, é revogado, e a revogação vive no Redis.
    |
    */

    'ttl' => (int) env('JWT_TTL', 60 * 60),              // 1 hora
    'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 60 * 60 * 24 * 30), // 30 dias

];
