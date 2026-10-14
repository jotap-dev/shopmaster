<?php

declare(strict_types=1);

namespace Shared\Http;

/**
 * Formato de erro comum a qualquer contexto: {"error": {"code", "message"}}.
 *
 * O `code` é estável e é por ele que o cliente decide o que fazer; a
 * `message` é legível e pode mudar sem aviso (RNF-061).
 */
final class ErrorResource
{
    /** @return array<string, array<string, mixed>> */
    public static function of(string $code, string $message): array
    {
        return ['error' => ['code' => $code, 'message' => $message]];
    }

    /**
     * Erro de validação: mesma casca, com o detalhe por campo.
     *
     * `fields` é acréscimo, não desvio do formato — o cliente que só conhece
     * `code` e `message` continua funcionando, e o formulário que quer marcar
     * o campo errado tem onde olhar.
     *
     * @param  array<string, list<string>>  $fields
     * @return array<string, array<string, mixed>>
     */
    public static function withFields(string $code, string $message, array $fields): array
    {
        return ['error' => ['code' => $code, 'message' => $message, 'fields' => $fields]];
    }
}
