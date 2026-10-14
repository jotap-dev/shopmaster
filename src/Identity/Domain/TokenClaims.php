<?php

declare(strict_types=1);

namespace Identity\Domain;

use DateTimeImmutable;

/** O conteúdo verificado de um token já aberto e conferido. */
final readonly class TokenClaims
{
    /** @param list<PlatformRole> $roles */
    public function __construct(
        public string $id,
        public string $issuer,
        public string $subject,
        public EmailAddress $email,
        public array $roles,
        public TokenType $type,
        public DateTimeImmutable $issuedAt,
        public DateTimeImmutable $expiresAt,
    ) {}

    public function isOfType(TokenType $type): bool
    {
        return $this->type === $type;
    }
}
