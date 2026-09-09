<?php

declare(strict_types=1);

namespace Identity\Domain;

/**
 * Nome da pessoa, como ela quer ser chamada.
 *
 * Normaliza o espaçamento — pontas e repetições internas — porque nome
 * colado de outro campo costuma vir com sujeira, e "Joao  Pedro" e
 * "Joao Pedro" são a mesma pessoa em qualquer busca ou saudação de e-mail.
 *
 * O limite conta **caracteres**, não bytes: a coluna é `varchar(120)`, que
 * conta caracteres, e 120 letras acentuadas ocupam 240 bytes. Contar bytes
 * aqui recusaria um nome perfeitamente válido.
 */
final readonly class PersonName
{
    /** Limite da coluna `users.name`. */
    public const MAX_LENGTH = 120;

    private function __construct(private string $value) {}

    public static function fromString(string $value): self
    {
        $normalizado = trim((string) preg_replace('/\s+/u', ' ', $value));

        if ($normalizado === '') {
            throw InvalidPersonName::empty();
        }

        if (mb_strlen($normalizado) > self::MAX_LENGTH) {
            throw InvalidPersonName::tooLong(self::MAX_LENGTH);
        }

        return new self($normalizado);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
