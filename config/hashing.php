<?php

declare(strict_types=1);

return [

    'driver' => 'bcrypt',

    /*
    |--------------------------------------------------------------------------
    | Custo do bcrypt
    |--------------------------------------------------------------------------
    |
    | O custo é a defesa: cada round dobra o trabalho de quem tentar quebrar
    | o hash offline. 12 é o padrão razoável hoje; a suíte usa 4 (ver
    | phpunit.xml), senão cada teste que cadastra alguém pagaria ~100ms.
    |
    | Sem este arquivo, o BCRYPT_ROUNDS do ambiente seria ignorado e a suíte
    | rodaria com custo de produção.
    |
    */

    'bcrypt' => [
        'rounds' => (int) env('BCRYPT_ROUNDS', 12),
        'verify' => true,
    ],

];
