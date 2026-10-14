<?php

declare(strict_types=1);

namespace Customer\Application;

use Customer\Domain\Address;
use Customer\Domain\InvalidAddress;
use Customer\Domain\InvalidBrazilianState;
use Customer\Domain\InvalidPostalCode;
use Customer\Domain\NewAddress;

/**
 * RF-006 — incluir um endereço na agenda.
 *
 * O primeiro endereço da conta vira padrão automaticamente. Pedir
 * `is_default: true` num endereço seguinte troca o padrão na mesma gravação.
 */
final class AddAddress
{
    public function __construct(private AddressRepository $addresses) {}

    /**
     * @throws InvalidAddress|InvalidBrazilianState|InvalidPostalCode
     */
    public function handle(
        string $userId,
        string $recipientName,
        string $street,
        string $number,
        string $neighborhood,
        string $city,
        string $state,
        string $postalCode,
        ?string $label = null,
        ?string $complement = null,
        bool $makeDefault = false,
    ): Address {
        $primeira = $this->addresses->countByUserId($userId) === 0;

        return $this->addresses->add(NewAddress::compose(
            userId: $userId,
            label: $label,
            recipientName: $recipientName,
            street: $street,
            number: $number,
            complement: $complement,
            neighborhood: $neighborhood,
            city: $city,
            state: $state,
            postalCode: $postalCode,
            isDefault: $primeira || $makeDefault,
        ));
    }
}
