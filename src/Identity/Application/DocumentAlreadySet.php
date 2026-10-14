<?php

declare(strict_types=1);

namespace Identity\Application;

use RuntimeException;

final class DocumentAlreadySet extends RuntimeException
{
    public static function create(): self
    {
        return new self('O documento ja foi definido e nao pode ser trocado.');
    }
}
