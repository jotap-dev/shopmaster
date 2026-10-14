<?php

declare(strict_types=1);

namespace Customer\Domain;

/**
 * CEP brasileiro: exatamente 8 dígitos.
 *
 * Guarda só os dígitos. Máscara gravada (`01310-100`) faria o mesmo CEP
 * virar várias linhas, e inviabilizaria busca e cotação de frete.
 */
final readonly class PostalCode
{
    private function __construct(private string $value) {}

    public static function fromString(string $value): self
    {
        $digitos = (string) preg_replace('/\D/', '', $value);

        if (strlen($digitos) !== 8) {
            throw InvalidPostalCode::wrongLength();
        }

        return new self($digitos);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function formatted(): string
    {
        return substr($this->value, 0, 5).'-'.substr($this->value, 5);
    }
}
