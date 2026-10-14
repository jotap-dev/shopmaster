<?php

declare(strict_types=1);

namespace Customer\Application;

use Customer\Domain\Address;
use Customer\Domain\AddressChanges;
use Customer\Domain\NewAddress;

interface AddressRepository
{
    /** @return list<Address> */
    public function listByUserId(string $userId): array;

    public function findOwnedBy(string $addressId, string $userId): ?Address;

    public function countByUserId(string $userId): int;

    /**
     * Grava o endereço. Se `$address->isDefault` for true, desmarca o padrão
     * anterior do mesmo usuário na mesma transação.
     */
    public function add(NewAddress $address): Address;

    /**
     * Aplica só o que veio no PATCH. Se `makesDefault`, desmarca o padrão
     * anterior. Devolve o endereço atualizado.
     *
     * **Contrato:** lança `AddressNotFound` se a linha sumiu entre a leitura
     * e a escrita (conta apagada, ou id de outra pessoa — o use case já
     * filtrou por dono; o adapter só precisa do id).
     */
    public function update(string $addressId, string $userId, AddressChanges $changes): Address;

    /**
     * Remove o endereço. Se era o padrão e ainda restam outros, promove o
     * mais antigo a padrão — a agenda nunca fica sem padrão enquanto houver
     * endereço (RF-006).
     */
    public function remove(string $addressId, string $userId): void;
}
