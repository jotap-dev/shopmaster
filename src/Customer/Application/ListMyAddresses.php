<?php

declare(strict_types=1);

namespace Customer\Application;

use Customer\Domain\Address;

/** RF-006 — listar a agenda do portador do token. */
final class ListMyAddresses
{
    public function __construct(private AddressRepository $addresses) {}

    /** @return list<Address> */
    public function handle(string $userId): array
    {
        return $this->addresses->listByUserId($userId);
    }
}
