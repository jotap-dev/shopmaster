<?php

declare(strict_types=1);

namespace Identity\Domain;

/** O resultado de um login ou de um refresh: quem é, e os tokens dele. */
final readonly class AuthenticatedSession
{
    public function __construct(
        public UserIdentity $user,
        public TokenPair $tokens,
    ) {}
}
