<?php

declare(strict_types=1);

namespace Customer\Domain;

/**
 * Endereço ainda sem id — o que o use case monta antes do repositório gravar.
 */
final readonly class NewAddress
{
    public function __construct(
        public string $userId,
        public ?string $label,
        public string $recipientName,
        public string $street,
        public string $number,
        public ?string $complement,
        public string $neighborhood,
        public string $city,
        public BrazilianState $state,
        public PostalCode $postalCode,
        public bool $isDefault,
    ) {}

    public static function compose(
        string $userId,
        ?string $label,
        string $recipientName,
        string $street,
        string $number,
        ?string $complement,
        string $neighborhood,
        string $city,
        string $state,
        string $postalCode,
        bool $isDefault,
    ): self {
        return new self(
            userId: $userId,
            label: self::opcional($label, Address::LABEL_MAX, 'label'),
            recipientName: self::obrigatorio($recipientName, Address::RECIPIENT_MAX, 'recipient_name'),
            street: self::obrigatorio($street, Address::STREET_MAX, 'street'),
            number: self::obrigatorio($number, Address::NUMBER_MAX, 'number'),
            complement: self::opcional($complement, Address::COMPLEMENT_MAX, 'complement'),
            neighborhood: self::obrigatorio($neighborhood, Address::NEIGHBORHOOD_MAX, 'neighborhood'),
            city: self::obrigatorio($city, Address::CITY_MAX, 'city'),
            state: BrazilianState::fromString($state),
            postalCode: PostalCode::fromString($postalCode),
            isDefault: $isDefault,
        );
    }

    private static function obrigatorio(string $value, int $max, string $field): string
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

    private static function opcional(?string $value, int $max, string $field): ?string
    {
        if ($value === null) {
            return null;
        }

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
