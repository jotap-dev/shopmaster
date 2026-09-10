<?php

declare(strict_types=1);

namespace Customer\Application;

use Customer\Domain\Address;

/** RF-006 — detalhe de um endereço da própria agenda. */
final class GetMyAddress
{
    public function __construct(private AddressRepository $addresses) {}

    /** @throws AddressNotFound */
    public function handle(string $userId, string $addressId): Address
    {
        return $this->addresses->findOwnedBy($addressId, $userId)
            ?? throw AddressNotFound::create();
    }
}
