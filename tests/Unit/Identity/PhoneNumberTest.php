<?php

declare(strict_types=1);

namespace Tests\Unit\Identity;

use Identity\Domain\InvalidPhoneNumber;
use Identity\Domain\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PhoneNumberTest extends TestCase
{
    /** @return list<array{string, string}> */
    public static function telefonesValidos(): array
    {
        return [
            ['(11) 98888-7777', '11988887777'],
            ['11988887777', '11988887777'],
            ['+55 11 98888-7777', '11988887777'],
            ['(21) 2222-3333', '2122223333'],
            ['85 3232-1010', '8532321010'],
        ];
    }

    #[DataProvider('telefonesValidos')]
    public function test_aceita_e_guarda_so_os_digitos(string $entrada, string $esperado): void
    {
        // Formatação é da tela. Guardar a máscara faz o mesmo telefone virar
        // várias linhas diferentes e inviabiliza qualquer busca por ele.
        $this->assertSame($esperado, PhoneNumber::fromString($entrada)->value());
    }

    public function test_descarta_o_codigo_do_pais_brasileiro(): void
    {
        $this->assertSame('11988887777', PhoneNumber::fromString('+5511988887777')->value());
    }

    /** @return list<array{string}> */
    public static function dddsInexistentes(): array
    {
        // 20, 23, 26, 29, 30, 36, 39, 50 e 52 não existem no plano nacional.
        return [['20988887777'], ['23988887777'], ['36988887777'], ['39988887777'], ['5098888777']];
    }

    #[DataProvider('dddsInexistentes')]
    public function test_recusa_ddd_inexistente(string $telefone): void
    {
        $this->expectException(InvalidPhoneNumber::class);

        PhoneNumber::fromString($telefone);
    }

    public function test_recusa_celular_que_nao_comeca_com_nove(): void
    {
        // Celular no Brasil tem 9 dígitos e o primeiro é 9. Um número de 11
        // dígitos que comece com outra coisa é digitação errada.
        $this->expectException(InvalidPhoneNumber::class);

        PhoneNumber::fromString('11888887777');
    }

    public function test_recusa_numero_curto_demais(): void
    {
        $this->expectException(InvalidPhoneNumber::class);

        PhoneNumber::fromString('1198888');
    }

    public function test_recusa_numero_longo_demais(): void
    {
        $this->expectException(InvalidPhoneNumber::class);

        PhoneNumber::fromString('119888877771234');
    }

    public function test_recusa_texto(): void
    {
        $this->expectException(InvalidPhoneNumber::class);

        PhoneNumber::fromString('meu telefone');
    }

    public function test_formata_para_exibicao(): void
    {
        $this->assertSame('(11) 98888-7777', PhoneNumber::fromString('11988887777')->formatted());
        $this->assertSame('(21) 2222-3333', PhoneNumber::fromString('2122223333')->formatted());
    }
}
