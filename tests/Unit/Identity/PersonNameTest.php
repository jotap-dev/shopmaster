<?php

declare(strict_types=1);

namespace Tests\Unit\Identity;

use Identity\Domain\InvalidPersonName;
use Identity\Domain\PersonName;
use PHPUnit\Framework\TestCase;

final class PersonNameTest extends TestCase
{
    public function test_remove_espacos_nas_pontas(): void
    {
        $this->assertSame('Joao Pedro', PersonName::fromString('  Joao Pedro  ')->value());
    }

    public function test_colapsa_espacos_internos(): void
    {
        $this->assertSame('Joao Pedro', PersonName::fromString("Joao \t  Pedro")->value());
    }

    public function test_recusa_nome_vazio(): void
    {
        $this->expectException(InvalidPersonName::class);

        PersonName::fromString('   ');
    }

    public function test_recusa_nome_maior_que_a_coluna(): void
    {
        $this->expectException(InvalidPersonName::class);

        PersonName::fromString(str_repeat('a', 121));
    }

    public function test_aceita_acento_no_limite_de_caracteres(): void
    {
        // 120 caracteres acentuados sao 240 bytes. Contar bytes aqui recusaria
        // um nome perfeitamente valido — a coluna e varchar(120), que conta
        // caracteres.
        $this->assertSame(120, mb_strlen(PersonName::fromString(str_repeat('á', 120))->value()));
    }
}
