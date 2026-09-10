<?php

declare(strict_types=1);

namespace Identity\Application;

use RuntimeException;

final class DocumentAlreadyInUse extends RuntimeException
{
    public static function create(): self
    {
        return new self('Este documento ja esta cadastrado em outra conta.');
    }
}
