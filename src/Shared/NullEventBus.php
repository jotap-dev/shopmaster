<?php

declare(strict_types=1);

namespace Shared;

/**
 * Implementação no-op, o bind padrão enquanto não há listener de verdade.
 *
 * Permite que o use case já dispare o evento certo desde o primeiro dia:
 * quando o listener nascer, só o bind em AppServiceProvider muda.
 */
final class NullEventBus implements EventBus
{
    public function dispatch(object $event): void
    {
        // Ninguém escutando ainda.
    }
}
