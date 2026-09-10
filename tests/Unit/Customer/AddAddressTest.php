<?php

declare(strict_types=1);

namespace Tests\Unit\Customer;

use Customer\Application\AddAddress;
use Customer\Domain\InvalidAddress;
use Customer\Domain\InvalidPostalCode;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Customer\Support\FakeAddressRepository;

final class AddAddressTest extends TestCase
{
    public function test_o_primeiro_endereco_vira_padrao(): void
    {
        $repo = new FakeAddressRepository;

        $endereco = (new AddAddress($repo))->handle(...$this->payload('user-1'));

        $this->assertTrue($endereco->isDefault);
        $this->assertSame('01310100', $endereco->postalCode->value());
    }

    public function test_o_segundo_nao_vira_padrao_sozinho(): void
    {
        $repo = new FakeAddressRepository;
        $add = new AddAddress($repo);

        $primeiro = $add->handle(...$this->payload('user-1', label: 'Casa'));
        $segundo = $add->handle(...$this->payload('user-1', label: 'Trabalho', recipient: 'Outro'));

        $this->assertTrue($primeiro->isDefault);
        $this->assertFalse($segundo->isDefault);
    }

    public function test_pedir_padrao_troca_o_anterior(): void
    {
        $repo = new FakeAddressRepository;
        $add = new AddAddress($repo);

        $primeiro = $add->handle(...$this->payload('user-1', label: 'Casa'));
        $segundo = $add->handle(...$this->payload('user-1', label: 'Trabalho', makeDefault: true));

        $this->assertFalse($repo->findOwnedBy($primeiro->id, 'user-1')?->isDefault);
        $this->assertTrue($segundo->isDefault);
    }

    public function test_recusa_cep_invalido(): void
    {
        $this->expectException(InvalidPostalCode::class);

        (new AddAddress(new FakeAddressRepository))->handle(
            ...$this->payload('user-1', postalCode: '123'),
        );
    }

    public function test_recusa_campo_obrigatorio_vazio(): void
    {
        $this->expectException(InvalidAddress::class);

        (new AddAddress(new FakeAddressRepository))->handle(
            ...$this->payload('user-1', street: '  '),
        );
    }

    /**
     * @return array{
     *   0: string, 1: string, 2: string, 3: string, 4: string, 5: string,
     *   6: string, 7: string, 8?: string|null, 9?: string|null, 10?: bool
     * }
     */
    private function payload(
        string $userId,
        string $recipient = 'Joao Pedro',
        string $street = 'Av Paulista',
        string $number = '1000',
        string $neighborhood = 'Bela Vista',
        string $city = 'Sao Paulo',
        string $state = 'SP',
        string $postalCode = '01310-100',
        ?string $label = null,
        ?string $complement = null,
        bool $makeDefault = false,
    ): array {
        return [
            $userId,
            $recipient,
            $street,
            $number,
            $neighborhood,
            $city,
            $state,
            $postalCode,
            $label,
            $complement,
            $makeDefault,
        ];
    }
}
