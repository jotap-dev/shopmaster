<?php

declare(strict_types=1);

namespace Shared\Http;

/** Formato de erro comum a qualquer contexto: {"error": {"code", "message"}}. */
final class ErrorResource
{
    /** @return array<string, array<string, string>> */
    public static function of(string $code, string $message): array
    {
        return ['error' => ['code' => $code, 'message' => $message]];
    }
}
