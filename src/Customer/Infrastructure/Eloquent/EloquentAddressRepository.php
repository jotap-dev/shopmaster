<?php

declare(strict_types=1);

namespace Customer\Infrastructure\Eloquent;

use Customer\Application\AddressNotFound;
use Customer\Application\AddressRepository;
use Customer\Domain\Address;
use Customer\Domain\AddressChanges;
use Customer\Domain\BrazilianState;
use Customer\Domain\NewAddress;
use Customer\Domain\PostalCode;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Support\Str;

final readonly class EloquentAddressRepository implements AddressRepository
{
    public function __construct(private ConnectionResolverInterface $connections) {}

    /** @return list<Address> */
    public function listByUserId(string $userId): array
    {
        // Allowlist (RULE 4). Padrão primeiro, depois os mais antigos —
        // checkout e tela de agenda esperam o padrão no topo.
        $linhas = $this->db()->table('addresses')
            ->select(
                'id', 'user_id', 'label', 'recipient_name', 'street', 'number',
                'complement', 'neighborhood', 'city', 'state', 'postal_code', 'is_default',
            )
            ->where('user_id', $userId)
            ->orderByDesc('is_default')
            ->orderBy('created_at')
            ->get();

        return $linhas->map(fn (object $linha): Address => $this->deLinha($linha))->all();
    }

    public function findOwnedBy(string $addressId, string $userId): ?Address
    {
        if (! self::pareceUuid($addressId)) {
            return null;
        }

        $linha = $this->db()->table('addresses')
            ->select(
                'id', 'user_id', 'label', 'recipient_name', 'street', 'number',
                'complement', 'neighborhood', 'city', 'state', 'postal_code', 'is_default',
            )
            ->where('id', $addressId)
            ->where('user_id', $userId)
            ->first();

        return $linha === null ? null : $this->deLinha($linha);
    }

    public function countByUserId(string $userId): int
    {
        return $this->db()->table('addresses')->where('user_id', $userId)->count();
    }

    public function add(NewAddress $address): Address
    {
        $id = (string) Str::uuid();
        $agora = new DateTimeImmutable;

        $this->db()->transaction(function () use ($address, $id, $agora): void {
            if ($address->isDefault) {
                $this->desmarcarPadrao($address->userId);
            }

            $this->db()->table('addresses')->insert([
                'id' => $id,
                'user_id' => $address->userId,
                'label' => $address->label,
                'recipient_name' => $address->recipientName,
                'street' => $address->street,
                'number' => $address->number,
                'complement' => $address->complement,
                'neighborhood' => $address->neighborhood,
                'city' => $address->city,
                'state' => $address->state->value,
                'postal_code' => $address->postalCode->value(),
                'is_default' => $address->isDefault,
                'created_at' => $agora,
                'updated_at' => $agora,
            ]);
        });

        return $this->findOwnedBy($id, $address->userId) ?? throw AddressNotFound::create();
    }

    public function update(string $addressId, string $userId, AddressChanges $changes): Address
    {
        $this->db()->transaction(function () use ($addressId, $userId, $changes): void {
            if ($changes->makesDefault) {
                $this->desmarcarPadrao($userId);
            }

            $colunas = [];

            if ($changes->clearsLabel) {
                $colunas['label'] = null;
            } elseif ($changes->label !== null) {
                $colunas['label'] = $changes->label;
            }

            if ($changes->recipientName !== null) {
                $colunas['recipient_name'] = $changes->recipientName;
            }

            if ($changes->street !== null) {
                $colunas['street'] = $changes->street;
            }

            if ($changes->number !== null) {
                $colunas['number'] = $changes->number;
            }

            if ($changes->clearsComplement) {
                $colunas['complement'] = null;
            } elseif ($changes->complement !== null) {
                $colunas['complement'] = $changes->complement;
            }

            if ($changes->neighborhood !== null) {
                $colunas['neighborhood'] = $changes->neighborhood;
            }

            if ($changes->city !== null) {
                $colunas['city'] = $changes->city;
            }

            if ($changes->state !== null) {
                $colunas['state'] = $changes->state->value;
            }

            if ($changes->postalCode !== null) {
                $colunas['postal_code'] = $changes->postalCode->value();
            }

            if ($changes->makesDefault) {
                $colunas['is_default'] = true;
            }

            if ($colunas === []) {
                return;
            }

            $afetadas = $this->db()->table('addresses')
                ->where('id', $addressId)
                ->where('user_id', $userId)
                ->update($colunas + ['updated_at' => new DateTimeImmutable]);

            if ($afetadas === 0) {
                throw AddressNotFound::create();
            }
        });

        return $this->findOwnedBy($addressId, $userId) ?? throw AddressNotFound::create();
    }

    public function remove(string $addressId, string $userId): void
    {
        $this->db()->transaction(function () use ($addressId, $userId): void {
            $atual = $this->findOwnedBy($addressId, $userId) ?? throw AddressNotFound::create();

            $this->db()->table('addresses')
                ->where('id', $addressId)
                ->where('user_id', $userId)
                ->delete();

            if (! $atual->isDefault) {
                return;
            }

            // Promove o mais antigo restante — a agenda nunca fica sem
            // padrão enquanto houver endereço (RF-006).
            $proximoId = $this->db()->table('addresses')
                ->where('user_id', $userId)
                ->orderBy('created_at')
                ->value('id');

            if ($proximoId !== null) {
                $this->db()->table('addresses')
                    ->where('id', $proximoId)
                    ->update(['is_default' => true, 'updated_at' => new DateTimeImmutable]);
            }
        });
    }

    private function desmarcarPadrao(string $userId): void
    {
        $this->db()->table('addresses')
            ->where('user_id', $userId)
            ->where('is_default', true)
            ->update(['is_default' => false, 'updated_at' => new DateTimeImmutable]);
    }

    private function deLinha(object $linha): Address
    {
        return new Address(
            id: $linha->id,
            userId: $linha->user_id,
            label: $linha->label,
            recipientName: $linha->recipient_name,
            street: $linha->street,
            number: $linha->number,
            complement: $linha->complement,
            neighborhood: $linha->neighborhood,
            city: $linha->city,
            state: BrazilianState::from($linha->state),
            postalCode: PostalCode::fromString($linha->postal_code),
            isDefault: (bool) $linha->is_default,
        );
    }

    private static function pareceUuid(string $id): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id) === 1;
    }

    private function db(): ConnectionInterface
    {
        return $this->connections->connection();
    }
}
