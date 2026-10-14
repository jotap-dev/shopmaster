<?php

declare(strict_types=1);

namespace Customer\Domain;

/**
 * Endereço da agenda do comprador (RF-006).
 *
 * Pertence a um `user_id` do Identity — Customer não tem tabela própria de
 * pessoa: a conta já vive em `users`. Só o que é de e-commerce (endereço,
 * depois favorito) mora aqui.
 */
final readonly class Address
{
    public const LABEL_MAX = 40;

    public const RECIPIENT_MAX = 120;

    public const STREET_MAX = 200;

    public const NUMBER_MAX = 20;

    public const COMPLEMENT_MAX = 120;

    public const NEIGHBORHOOD_MAX = 120;

    public const CITY_MAX = 120;

    public function __construct(
        public string $id,
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
}
