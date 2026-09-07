<?php

declare(strict_types=1);

namespace Shared;

/**
 * Porta de publicação de eventos de domínio.
 *
 * É por aqui que um contexto avisa que algo aconteceu sem saber — nem
 * poder saber — quem vai reagir. `Ordering` publica `OrderPaid`;
 * `Notification` e `Inventory` reagem. Nenhum dos três se importa com os
 * outros, e é isso que mantém a fronteira de pé.
 */
interface EventBus
{
    public function dispatch(object $event): void;
}
