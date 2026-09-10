<?php

declare(strict_types=1);

namespace Tests\Unit\Customer;

use Customer\Application\AddAddress;
use Customer\Application\AddressNotFound;
use Customer\Application\RemoveAddress;
use Customer\Application\UpdateAddress;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Customer\Support\FakeAddressRepository;

final class UpdateAndRemoveAddressTest extends TestCase
{
    public function test_altera_so_o_que_foi_pedido(): void
    {
        $repo = new FakeAddressRepository;
        $endereco = (new AddAddress($repo))->handle(
            'user-1', 'Joao', 'Rua A', '10', 'Centro', 'Sao Paulo', 'SP', '01001000', 'Casa',
        );

        $atualizado = (new UpdateAddress($repo))->handle(
            'user-1', $endereco->id, number: '20',
        );

        $this->assertSame('20', $atualizado->number);
        $this->assertSame('Rua A', $atualizado->street);
        $this->assertSame('Casa', $atualizado->label);
    }

    public function test_torna_padrao(): void
    {
        $repo = new FakeAddressRepository;
        $add = new AddAddress($repo);
        $primeiro = $add->handle('user-1', 'Joao', 'Rua A', '10', 'Centro', 'Sao Paulo', 'SP', '01001000');
        $segundo = $add->handle('user-1', 'Joao', 'Rua B', '20', 'Centro', 'Sao Paulo', 'SP', '01001000');

        (new UpdateAddress($repo))->handle('user-1', $segundo->id, makeDefault: true);

        $this->assertFalse($repo->findOwnedBy($primeiro->id, 'user-1')?->isDefault);
        $this->assertTrue($repo->findOwnedBy($segundo->id, 'user-1')?->isDefault);
    }

    public function test_endereco_de_outro_usuario_nao_existe(): void
    {
        $repo = new FakeAddressRepository;
        $endereco = (new AddAddress($repo))->handle(
            'user-1', 'Joao', 'Rua A', '10', 'Centro', 'Sao Paulo', 'SP', '01001000',
        );

        $this->expectException(AddressNotFound::class);

        (new UpdateAddress($repo))->handle('user-2', $endereco->id, number: '99');
    }

    public function test_remover_o_padrao_promove_outro(): void
    {
        $repo = new FakeAddressRepository;
        $add = new AddAddress($repo);
        $primeiro = $add->handle('user-1', 'Joao', 'Rua A', '10', 'Centro', 'Sao Paulo', 'SP', '01001000');
        $segundo = $add->handle('user-1', 'Joao', 'Rua B', '20', 'Centro', 'Sao Paulo', 'SP', '01001000');

        (new RemoveAddress($repo))->handle('user-1', $primeiro->id);

        $this->assertNull($repo->findOwnedBy($primeiro->id, 'user-1'));
        $this->assertTrue($repo->findOwnedBy($segundo->id, 'user-1')?->isDefault);
    }

    public function test_remover_endereco_alheio_e_404(): void
    {
        $repo = new FakeAddressRepository;
        $endereco = (new AddAddress($repo))->handle(
            'user-1', 'Joao', 'Rua A', '10', 'Centro', 'Sao Paulo', 'SP', '01001000',
        );

        $this->expectException(AddressNotFound::class);

        (new RemoveAddress($repo))->handle('user-2', $endereco->id);
    }
}
