<?php

declare(strict_types=1);

namespace Customer\Interface\Http\Resources;

use Customer\Domain\Address;

final class AddressResource
{
    /** @return array<string, mixed> */
    public static function from(Address $address): array
    {
        return [
            'id' => $address->id,
            'label' => $address->label,
            'recipient_name' => $address->recipientName,
            'street' => $address->street,
            'number' => $address->number,
            'complement' => $address->complement,
            'neighborhood' => $address->neighborhood,
            'city' => $address->city,
            'state' => $address->state->value,
            'postal_code' => $address->postalCode->value(),
            'postal_code_formatted' => $address->postalCode->formatted(),
            'is_default' => $address->isDefault,
        ];
    }

    /**
     * @param  list<Address>  $addresses
     * @return list<array<string, mixed>>
     */
    public static function collection(array $addresses): array
    {
        return array_map(self::from(...), $addresses);
    }
}
