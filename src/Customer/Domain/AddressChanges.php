<?php

declare(strict_types=1);

namespace Customer\Domain;

/**
 * Campos de um PATCH de endereço.
 *
 * Ausente = não mexa. `clearsLabel` / `clearsComplement` = grave null.
 * `makesDefault` = true só quando o cliente pediu explicitamente tornar
 * este o padrão; false aqui não tira o padrão de ninguém.
 */
final readonly class AddressChanges
{
    public function __construct(
        public ?string $label = null,
        public bool $clearsLabel = false,
        public ?string $recipientName = null,
        public ?string $street = null,
        public ?string $number = null,
        public ?string $complement = null,
        public bool $clearsComplement = false,
        public ?string $neighborhood = null,
        public ?string $city = null,
        public ?BrazilianState $state = null,
        public ?PostalCode $postalCode = null,
        public bool $makesDefault = false,
    ) {}

    public function isEmpty(): bool
    {
        return $this->label === null
            && ! $this->clearsLabel
            && $this->recipientName === null
            && $this->street === null
            && $this->number === null
            && $this->complement === null
            && ! $this->clearsComplement
            && $this->neighborhood === null
            && $this->city === null
            && $this->state === null
            && $this->postalCode === null
            && ! $this->makesDefault;
    }
}
