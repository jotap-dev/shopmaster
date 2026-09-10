<?php

declare(strict_types=1);

namespace Tests\Feature\Customer;

use Customer\Application\AddressRepository;
use Customer\Domain\NewAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class EloquentAddressRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_grava_e_lista_pelo_dono(): void
    {
        $userId = $this->usuario();
        $repo = $this->app->make(AddressRepository::class);

        $salvo = $repo->add(NewAddress::compose(
            userId: $userId,
            label: 'Casa',
            recipientName: 'Joao',
            street: 'Rua A',
            number: '10',
            complement: null,
            neighborhood: 'Centro',
            city: 'Sao Paulo',
            state: 'SP',
            postalCode: '01001000',
            isDefault: true,
        ));

        $lista = $repo->listByUserId($userId);

        $this->assertCount(1, $lista);
        $this->assertSame($salvo->id, $lista[0]->id);
        $this->assertTrue($lista[0]->isDefault);
    }

    public function test_troca_de_padrao_e_atomica(): void
    {
        $userId = $this->usuario();
        $repo = $this->app->make(AddressRepository::class);

        $primeiro = $repo->add(NewAddress::compose(
            $userId, 'Casa', 'Joao', 'Rua A', '10', null, 'Centro', 'Sao Paulo', 'SP', '01001000', true,
        ));
        $segundo = $repo->add(NewAddress::compose(
            $userId, 'Trabalho', 'Joao', 'Rua B', '20', null, 'Centro', 'Sao Paulo', 'SP', '01001000', true,
        ));

        $this->assertFalse($repo->findOwnedBy($primeiro->id, $userId)?->isDefault);
        $this->assertTrue($repo->findOwnedBy($segundo->id, $userId)?->isDefault);
        $this->assertSame(1, DB::table('addresses')->where('user_id', $userId)->where('is_default', true)->count());
    }

    private function usuario(): string
    {
        $id = (string) Str::uuid();

        DB::table('users')->insert([
            'id' => $id,
            'name' => 'Joao',
            'email' => $id.'@shopmaster.test',
            'password_hash' => 'hash',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}
