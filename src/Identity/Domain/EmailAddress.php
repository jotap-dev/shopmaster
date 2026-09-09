<?php

declare(strict_types=1);

namespace Identity\Domain;

/**
 * Endereço de e-mail — a identidade de login do usuário.
 *
 * Normaliza para minúsculas na construção. A coluna é `citext`, então o
 * Postgres já compara sem diferenciar caixa; normalizar aqui garante que o
 * valor **gravado** seja sempre o mesmo, independentemente de como a pessoa
 * digitou. As duas defesas se complementam: a do domínio padroniza a escrita,
 * a do banco protege qualquer caminho que esqueça de normalizar.
 */
final readonly class EmailAddress
{
    /** Limite da coluna `users.email`. */
    public const MAX_LENGTH = 255;

    private function __construct(private string $value) {}

    public static function fromString(string $value): self
    {
        $normalizado = mb_strtolower(trim($value));

        if (mb_strlen($normalizado) > self::MAX_LENGTH) {
            throw InvalidEmailAddress::tooLong(self::MAX_LENGTH);
        }

        if (filter_var($normalizado, FILTER_VALIDATE_EMAIL) === false) {
            throw InvalidEmailAddress::malformed($value);
        }

        return new self($normalizado);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
