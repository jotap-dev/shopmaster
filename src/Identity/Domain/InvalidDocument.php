<?php

declare(strict_types=1);

namespace Identity\Domain;

use RuntimeException;

final class InvalidDocument extends RuntimeException
{
    public static function empty(DocumentType $tipo): self
    {
        return new self("O documento do tipo {$tipo->value} nao pode ser vazio.");
    }

    public static function wrongFormat(DocumentType $tipo): self
    {
        return new self("O valor informado nao tem o formato de um {$tipo->value}.");
    }

    /** O valor veio sem o tipo — sem ele não há como saber qual regra aplicar. */
    public static function missingType(): self
    {
        return new self('O documento precisa vir acompanhado do tipo (cpf, cnh ou cnpj).');
    }

    public static function unknownType(string $tipo): self
    {
        return new self("Tipo de documento desconhecido: '{$tipo}'.");
    }

    public static function failsCheckDigit(DocumentType $tipo): self
    {
        return new self("O digito verificador do {$tipo->value} nao confere.");
    }
}
