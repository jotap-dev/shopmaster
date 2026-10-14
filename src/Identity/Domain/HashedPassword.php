<?php

declare(strict_types=1);

namespace Identity\Domain;

use JsonSerializable;
use RuntimeException;

/**
 * Senha já processada pelo algoritmo de hash, a caminho do banco.
 *
 * Redigida na serialização pelo mesmo motivo da PlainPassword: hash de senha
 * em log é material de ataque offline, e a RNF-020 diz que ele não sai do
 * repositório — nem em Value Object, nem em resposta, nem em log.
 */
final readonly class HashedPassword implements JsonSerializable
{
    private function __construct(private string $value) {}

    public static function fromHash(string $hash): self
    {
        if (trim($hash) === '') {
            throw new RuntimeException('Hash de senha vazio: o hasher falhou silenciosamente.');
        }

        return new self($hash);
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
