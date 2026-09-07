<?php

declare(strict_types=1);

namespace Shared;

use DateTimeImmutable;

/**
 * O tempo como porta.
 *
 * Reserva que expira, cupom vencido e token expirado são regra de negócio
 * que depende de "agora". Com o relógio injetado, o teste controla o tempo
 * em vez de dormir esperando por ele.
 */
interface Clock
{
    public function now(): DateTimeImmutable;
}
