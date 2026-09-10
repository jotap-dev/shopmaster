<?php

declare(strict_types=1);

namespace Identity\Application;

use Identity\Domain\UserProfile;

final class GetMyProfile
{
    public function __construct(private UserRepository $users) {}

    /** @throws ProfileNotFound */
    public function handle(string $userId): UserProfile
    {
        return $this->users->findProfileById($userId) ?? throw ProfileNotFound::create();
    }
}
