<?php

declare(strict_types=1);

namespace Identity\Domain;

use RuntimeException;

/**
 * Uma classe, dois fatos de negócio distintos, cada um com seu construtor
 * nomeado — a mensagem precisa dizer qual regra foi violada, porque "senha
 * inválida" não ajuda ninguém a corrigir.
 */
final class InvalidPassword extends RuntimeException
{
    public static function tooShort(int $minimo): self
    {
        return new self("A senha precisa ter ao menos {$minimo} caracteres.");
    }

    public static function tooLong(int $maximoEmBytes): self
    {
        return new self("A senha excede o limite de {$maximoEmBytes} bytes.");
    }
}
