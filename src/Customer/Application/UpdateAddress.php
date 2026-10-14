<?php

declare(strict_types=1);

namespace Customer\Application;

use Customer\Domain\Address;
use Customer\Domain\AddressChanges;
use Customer\Domain\BrazilianState;
use Customer\Domain\InvalidAddress;
use Customer\Domain\InvalidBrazilianState;
use Customer\Domain\InvalidPostalCode;
use Customer\Domain\PostalCode;

/** RF-006 — editar um endereço da própria agenda. */
final class UpdateAddress
{
    public function __construct(private AddressRepository $addresses) {}

    /**
     * @throws AddressNotFound
     * @throws InvalidAddress|InvalidBrazilianState|InvalidPostalCode
     */
    public function handle(
        string $userId,
        string $addressId,
        ?string $label = null,
        bool $clearLabel = false,
        ?string $recipientName = null,
        ?string $street = null,
        ?string $number = null,
        ?string $complement = null,
        bool $clearComplement = false,
        ?string $neighborhood = null,
        ?string $city = null,
        ?string $state = null,
        ?string $postalCode = null,
        bool $makeDefault = false,
    ): Address {
        $atual = $this->addresses->findOwnedBy($addressId, $userId)
            ?? throw AddressNotFound::create();

        $mudancas = new AddressChanges(
            label: $clearLabel ? null : ($label === null ? null : $this->textoOpcional($label, Address::LABEL_MAX, 'label')),
            clearsLabel: $clearLabel,
            recipientName: $recipientName === null ? null : $this->textoObrigatorio($recipientName, Address::RECIPIENT_MAX, 'recipient_name'),
            street: $street === null ? null : $this->textoObrigatorio($street, Address::STREET_MAX, 'street'),
            number: $number === null ? null : $this->textoObrigatorio($number, Address::NUMBER_MAX, 'number'),
            complement: $clearComplement ? null : ($complement === null ? null : $this->textoOpcional($complement, Address::COMPLEMENT_MAX, 'complement')),
            clearsComplement: $clearComplement,
            neighborhood: $neighborhood === null ? null : $this->textoObrigatorio($neighborhood, Address::NEIGHBORHOOD_MAX, 'neighborhood'),
            city: $city === null ? null : $this->textoObrigatorio($city, Address::CITY_MAX, 'city'),
            state: $state === null ? null : BrazilianState::fromString($state),
            postalCode: $postalCode === null ? null : PostalCode::fromString($postalCode),
            makesDefault: $makeDefault,
        );

        if ($mudancas->isEmpty()) {
            return $atual;
        }

        return $this->addresses->update($addressId, $userId, $mudancas);
    }

    private function textoObrigatorio(string $value, int $max, string $field): string
    {
        $normalizado = trim((string) preg_replace('/\s+/u', ' ', $value));

        if ($normalizado === '') {
            throw InvalidAddress::emptyField($field);
        }

        if (mb_strlen($normalizado) > $max) {
            throw InvalidAddress::tooLong($field, $max);
        }

        return $normalizado;
    }

    private function textoOpcional(string $value, int $max, string $field): ?string
    {
        $normalizado = trim((string) preg_replace('/\s+/u', ' ', $value));

        if ($normalizado === '') {
            return null;
        }

        if (mb_strlen($normalizado) > $max) {
            throw InvalidAddress::tooLong($field, $max);
        }

        return $normalizado;
    }
}
