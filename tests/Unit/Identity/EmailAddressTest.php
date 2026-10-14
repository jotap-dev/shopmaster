<?php

declare(strict_types=1);

namespace Tests\Unit\Identity;

use Identity\Domain\EmailAddress;
use Identity\Domain\InvalidEmailAddress;
use PHPUnit\Framework\TestCase;

final class EmailAddressTest extends TestCase
{
    public function test_normaliza_para_minusculas(): void
    {
        // A coluna e `citext`, entao o banco ja compara sem diferenciar caixa.
        // Normalizar aqui e a primeira linha: garante que o valor GRAVADO seja
        // sempre o mesmo, e nao dependa de como a pessoa digitou no cadastro.
        $this->assertSame('joao@shopmaster.test', EmailAddress::fromString('Joao@ShopMaster.TEST')->value());
    }

    public function test_remove_espacos_nas_pontas(): void
    {
        $this->assertSame('joao@shopmaster.test', EmailAddress::fromString('  joao@shopmaster.test  ')->value());
    }

    public function test_recusa_formato_invalido(): void
    {
        $this->expectException(InvalidEmailAddress::class);

        EmailAddress::fromString('joao-arroba-shopmaster');
    }

    public function test_recusa_string_vazia(): void
    {
        $this->expectException(InvalidEmailAddress::class);

        EmailAddress::fromString('   ');
    }

    public function test_recusa_endereco_longo_demais_para_a_coluna(): void
    {
        $this->expectException(InvalidEmailAddress::class);

        EmailAddress::fromString(str_repeat('a', 250).'@shopmaster.test');
    }

    public function test_compara_por_valor(): void
    {
        $this->assertTrue(
            EmailAddress::fromString('joao@shopmaster.test')
                ->equals(EmailAddress::fromString('JOAO@shopmaster.test'))
        );
    }
}
