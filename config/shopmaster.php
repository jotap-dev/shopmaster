<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Moeda da loja
    |--------------------------------------------------------------------------
    |
    | Toda instância de Shared\Money nasce com esta moeda quando nenhuma é
    | informada. Multi-moeda não está no escopo: quando estiver, o valor
    | passa a vir do produto, não daqui.
    |
    */

    'currency' => env('SHOPMASTER_CURRENCY', 'BRL'),

    /*
    |--------------------------------------------------------------------------
    | Marketplace
    |--------------------------------------------------------------------------
    |
    | Percentual que a plataforma retém sobre cada linha vendida. É o padrão:
    | a loja pode ter um percentual próprio, negociado, que prevalece.
    |
    | O valor é **congelado por linha** no momento da compra — mudar este
    | número não altera pedido já fechado (RN-04).
    |
    */

    'commission' => [
        'default_percentage' => env('SHOPMASTER_COMMISSION_PERCENTAGE', '10.00'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Loja
    |--------------------------------------------------------------------------
    |
    | `require_approval` liga a moderação: a loja nasce em `pending_approval`
    | e só vende depois que um platform_admin a aprova. Desligar é aceitar que
    | qualquer conta anuncie sem revisão.
    |
    | `role_cache_ttl` é por quanto tempo o papel de um membro numa loja fica
    | em cache. Curto de propósito: é o atraso máximo entre remover alguém da
    | loja e ele de fato perder o acesso.
    |
    */

    'store' => [
        'max_per_user' => (int) env('STORE_MAX_PER_USER', 5),
        'require_approval' => (bool) env('STORE_REQUIRE_APPROVAL', true),
        'role_cache_ttl' => (int) env('STORE_ROLE_CACHE_TTL', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Catálogo
    |--------------------------------------------------------------------------
    |
    | TTL do store `catalog` (Redis DB 2). O cache é invalidado na publicação
    | do produto; o TTL é a rede de segurança para o caso de a invalidação
    | falhar — sem ele, um produto despublicado ficaria visível para sempre.
    |
    */

    'catalog' => [
        'cache_ttl' => (int) env('CATALOG_CACHE_TTL', 300),
        'page_size' => (int) env('CATALOG_PAGE_SIZE', 24),
        'max_page_size' => (int) env('CATALOG_MAX_PAGE_SIZE', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Carrinho
    |--------------------------------------------------------------------------
    |
    | Carrinho de visitante vive no Redis (DB 3) e expira sozinho. Carrinho
    | de cliente logado é persistido no Postgres e não tem TTL.
    |
    */

    'cart' => [
        'guest_ttl' => (int) env('CART_GUEST_TTL', 60 * 60 * 24 * 7), // 7 dias
        'max_items' => (int) env('CART_MAX_ITEMS', 50),
        'max_quantity_per_item' => (int) env('CART_MAX_QUANTITY_PER_ITEM', 99),
    ],

    /*
    |--------------------------------------------------------------------------
    | Estoque
    |--------------------------------------------------------------------------
    |
    | Janela da reserva feita no checkout (Redis DB 4). Expirada, o item
    | volta ao estoque sozinho — é isto que dispensa job de limpeza para
    | checkout abandonado.
    |
    */

    'inventory' => [
        'reservation_ttl' => (int) env('INVENTORY_RESERVATION_TTL', 900), // 15 minutos
        'lock_timeout' => (int) env('INVENTORY_LOCK_TIMEOUT', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pedido
    |--------------------------------------------------------------------------
    |
    | `idempotency_ttl` é por quanto tempo a chave do header Idempotency-Key
    | do POST /v1/checkout é lembrada no Redis. Duplo clique dentro da janela
    | devolve o pedido já criado em vez de criar um segundo.
    |
    */

    'ordering' => [
        'idempotency_ttl' => (int) env('ORDERING_IDEMPOTENCY_TTL', 60 * 60 * 24),
        'cancellation_window_hours' => (int) env('ORDERING_CANCELLATION_WINDOW_HOURS', 24),
    ],

];
