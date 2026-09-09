<?php

declare(strict_types=1);

namespace Identity\Domain;

use JsonSerializable;

/**
 * Senha em texto puro, viva apenas entre a requisição e o hash.
 *
 * Existem duas razões para ela ser um VO em vez de uma `string`:
 *
 * 1. **A política de senha é regra de negócio** e mora no domínio, não numa
 *    regra de validação do Laravel que alguém esquece de repetir na próxima
 *    rota que aceitar senha.
 *
 * 2. **Ela não pode vazar.** `__debugInfo()` e `jsonSerialize()` a substituem
 *    por um marcador, então nem `var_dump`, nem `print_r`, nem um
 *    `json_encode` de contexto de log conseguem imprimi-la. Senha em texto
 *    puro num arquivo de log fica lá para sempre.
 */
final readonly class PlainPassword implements JsonSerializable
{
    /** Em caracteres: é o que a pessoa conta ao digitar. */
    public const MIN_LENGTH = 8;

    /**
     * Em **bytes**, não caracteres.
     *
     * O bcrypt trunca silenciosamente em 72 bytes. Sem esta guarda, duas
     * senhas diferentes que compartilhem os 72 primeiros bytes abrem a mesma
     * conta — e ninguém descobre, porque o login simplesmente funciona.
     * Um emoji ocupa 4 bytes: 40 deles já estouram o limite com apenas 40
     * caracteres digitados.
     */
    public const MAX_BYTES = 72;

    private function __construct(private string $value) {}

    public static function fromString(string $value): self
    {
        if (mb_strlen($value) < self::MIN_LENGTH) {
            throw InvalidPassword::tooShort(self::MIN_LENGTH);
        }

        if (strlen($value) > self::MAX_BYTES) {
            throw InvalidPassword::tooLong(self::MAX_BYTES);
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return ['value' => '[REDACTED]'];
    }

    public function jsonSerialize(): string
    {
        return '[REDACTED]';
    }
}
