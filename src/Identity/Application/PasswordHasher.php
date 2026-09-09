<?php

declare(strict_types=1);

namespace Identity\Application;

use Identity\Domain\HashedPassword;
use Identity\Domain\PlainPassword;

interface PasswordHasher
{
    public function hash(PlainPassword $plain): HashedPassword;
}
