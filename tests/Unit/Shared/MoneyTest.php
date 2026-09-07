<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Shared\Money;

final class MoneyTest extends TestCase
{
    public function test_soma_valores_da_mesma_moeda(): void
    {
        $total = Money::fromCents(1990)->plus(Money::fromCents(1005));

        $this->assertSame(2995, $total->cents());
    }

    public function test_multiplica_pela_quantidade_da_linha(): void
    {
        $linha = Money::fromCents(2550)->times(3);

        $this->assertSame(7650, $linha->cents());
    }

    public function test_recusa_operar_moedas_diferentes(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromCents(100, 'BRL')->plus(Money::fromCents(100, 'USD'));
    }

    public function test_recusa_codigo_de_moeda_invalido(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromCents(100, 'REAL');
    }

    public function test_recusa_multiplicar_por_quantidade_negativa(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromCents(100)->times(-1);
    }

    public function test_e_imutavel(): void
    {
        $original = Money::fromCents(1000);
        $original->plus(Money::fromCents(500));

        $this->assertSame(1000, $original->cents());
    }

    public function test_serializa_como_centavos_e_moeda(): void
    {
        $this->assertSame(
            ['amount' => 1990, 'currency' => 'BRL'],
            Money::fromCents(1990)->toArray(),
        );
    }

    public function test_le_um_numeric_do_postgres_sem_passar_por_float(): void
    {
        // (int) (19.99 * 100) devolve 1998 em ponto flutuante. Aqui tem de dar 1999.
        $this->assertSame(1999, Money::fromDecimalString('19.99')->cents());
        $this->assertSame(19990, Money::fromDecimalString('199.90')->cents());
        $this->assertSame(100, Money::fromDecimalString('1.00')->cents());
        $this->assertSame(5, Money::fromDecimalString('0.05')->cents());
    }

    public function test_aceita_decimal_sem_casas_ou_com_uma_so(): void
    {
        $this->assertSame(2500, Money::fromDecimalString('25')->cents());
        $this->assertSame(2550, Money::fromDecimalString('25.5')->cents());
    }

    public function test_le_valor_negativo(): void
    {
        $this->assertSame(-1990, Money::fromDecimalString('-19.90')->cents());
    }

    public function test_recusa_decimal_malformado(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromDecimalString('19,90');
    }

    public function test_recusa_mais_de_duas_casas_decimais(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromDecimalString('19.999');
    }

    public function test_escreve_para_numeric_com_duas_casas(): void
    {
        $this->assertSame('199.90', Money::fromCents(19990)->toDecimalString());
        $this->assertSame('0.05', Money::fromCents(5)->toDecimalString());
        $this->assertSame('25.00', Money::fromCents(2500)->toDecimalString());
        $this->assertSame('-19.90', Money::fromCents(-1990)->toDecimalString());
    }

    public function test_ida_e_volta_pelo_banco_preserva_o_valor(): void
    {
        foreach (['0.00', '0.01', '19.99', '199.90', '99999999.99', '-4.20'] as $valor) {
            $this->assertSame($valor, Money::fromDecimalString($valor)->toDecimalString());
        }
    }
}
