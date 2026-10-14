<?php

declare(strict_types=1);

namespace Identity\Application;

use Identity\Domain\TokenType;
use RuntimeException;

/**
 * Um fato, várias causas — e a mensagem **não** distingue qual delas.
 *
 * Dizer "assinatura inválida" em vez de "token malformado" ensina a quem
 * está sondando exatamente onde parou. Os construtores nomeados existem para
 * o log e para o teste; o cliente recebe sempre `invalid_token`.
 */
final class InvalidToken extends RuntimeException
{
    private const MENSAGEM = 'Token invalido.';

    public static function malformed(): self
    {
        return new self(self::MENSAGEM);
    }

    public static function badSignature(): self
    {
        return new self(self::MENSAGEM);
    }

    public static function unknownIssuer(): self
    {
        return new self(self::MENSAGEM);
    }

    public static function wrongType(TokenType $esperado): self
    {
        return new self(self::MENSAGEM);
    }
}
