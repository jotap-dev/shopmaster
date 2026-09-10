<?php

declare(strict_types=1);

namespace Customer\Application;

/** RF-006 — remover um endereço da própria agenda. */
final class RemoveAddress
{
    public function __construct(private AddressRepository $addresses) {}

    /** @throws AddressNotFound */
    public function handle(string $userId, string $addressId): void
    {
        $this->addresses->findOwnedBy($addressId, $userId)
            ?? throw AddressNotFound::create();

        $this->addresses->remove($addressId, $userId);
    }
}
