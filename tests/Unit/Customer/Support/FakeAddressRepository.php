<?php

declare(strict_types=1);

namespace Tests\Unit\Customer\Support;

use Customer\Application\AddressNotFound;
use Customer\Application\AddressRepository;
use Customer\Domain\Address;
use Customer\Domain\AddressChanges;
use Customer\Domain\NewAddress;

final class FakeAddressRepository implements AddressRepository
{
    private int $seq = 0;

    /** @var array<string, Address> */
    private array $items = [];

    /** @return list<Address> */
    public function listByUserId(string $userId): array
    {
        $lista = array_values(array_filter(
            $this->items,
            static fn (Address $a): bool => $a->userId === $userId,
        ));

        usort($lista, static function (Address $a, Address $b): int {
            if ($a->isDefault !== $b->isDefault) {
                return $a->isDefault ? -1 : 1;
            }

            return strcmp($a->id, $b->id);
        });

        return $lista;
    }

    public function findOwnedBy(string $addressId, string $userId): ?Address
    {
        $endereco = $this->items[$addressId] ?? null;

        return ($endereco !== null && $endereco->userId === $userId) ? $endereco : null;
    }

    public function countByUserId(string $userId): int
    {
        return count(array_filter(
            $this->items,
            static fn (Address $a): bool => $a->userId === $userId,
        ));
    }

    public function add(NewAddress $address): Address
    {
        if ($address->isDefault) {
            $this->desmarcarPadrao($address->userId);
        }

        $salvo = new Address(
            id: 'addr-'.(++$this->seq),
            userId: $address->userId,
            label: $address->label,
            recipientName: $address->recipientName,
            street: $address->street,
            number: $address->number,
            complement: $address->complement,
            neighborhood: $address->neighborhood,
            city: $address->city,
            state: $address->state,
            postalCode: $address->postalCode,
            isDefault: $address->isDefault,
        );

        $this->items[$salvo->id] = $salvo;

        return $salvo;
    }

    public function update(string $addressId, string $userId, AddressChanges $changes): Address
    {
        $atual = $this->findOwnedBy($addressId, $userId) ?? throw AddressNotFound::create();

        if ($changes->makesDefault) {
            $this->desmarcarPadrao($userId);
        }

        $atualizado = new Address(
            id: $atual->id,
            userId: $atual->userId,
            label: $changes->clearsLabel ? null : ($changes->label ?? $atual->label),
            recipientName: $changes->recipientName ?? $atual->recipientName,
            street: $changes->street ?? $atual->street,
            number: $changes->number ?? $atual->number,
            complement: $changes->clearsComplement ? null : ($changes->complement ?? $atual->complement),
            neighborhood: $changes->neighborhood ?? $atual->neighborhood,
            city: $changes->city ?? $atual->city,
            state: $changes->state ?? $atual->state,
            postalCode: $changes->postalCode ?? $atual->postalCode,
            isDefault: $changes->makesDefault ? true : $atual->isDefault,
        );

        $this->items[$addressId] = $atualizado;

        return $atualizado;
    }

    public function remove(string $addressId, string $userId): void
    {
        $atual = $this->findOwnedBy($addressId, $userId) ?? throw AddressNotFound::create();
        unset($this->items[$addressId]);

        if (! $atual->isDefault) {
            return;
        }

        $restantes = $this->listByUserId($userId);

        if ($restantes === []) {
            return;
        }

        $promovido = $restantes[0];
        $this->items[$promovido->id] = new Address(
            id: $promovido->id,
            userId: $promovido->userId,
            label: $promovido->label,
            recipientName: $promovido->recipientName,
            street: $promovido->street,
            number: $promovido->number,
            complement: $promovido->complement,
            neighborhood: $promovido->neighborhood,
            city: $promovido->city,
            state: $promovido->state,
            postalCode: $promovido->postalCode,
            isDefault: true,
        );
    }

    private function desmarcarPadrao(string $userId): void
    {
        foreach ($this->items as $id => $endereco) {
            if ($endereco->userId === $userId && $endereco->isDefault) {
                $this->items[$id] = new Address(
                    id: $endereco->id,
                    userId: $endereco->userId,
                    label: $endereco->label,
                    recipientName: $endereco->recipientName,
                    street: $endereco->street,
                    number: $endereco->number,
                    complement: $endereco->complement,
                    neighborhood: $endereco->neighborhood,
                    city: $endereco->city,
                    state: $endereco->state,
                    postalCode: $endereco->postalCode,
                    isDefault: false,
                );
            }
        }
    }
}
