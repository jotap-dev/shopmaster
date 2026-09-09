<?php

declare(strict_types=1);

namespace Tests\Unit\Identity;

use Identity\Domain\InvalidPassword;
use Identity\Domain\PlainPassword;
use PHPUnit\Framework\TestCase;

final class PlainPasswordTest extends TestCase
{
    public function test_aceita_senha_dentro_da_politica(): void
    {
        $this->assertSame('senha-forte-123', PlainPassword::fromString('senha-forte-123')->value());
    }

    public function test_recusa_senha_curta_demais(): void
    {
        $this->expectException(InvalidPassword::class);

        PlainPassword::fromString('curta12');
    }

    public function test_recusa_senha_acima_do_limite_do_bcrypt(): void
    {
        // O bcrypt TRUNCA silenciosamente em 72 bytes: sem esta guarda, duas
        // senhas diferentes que compartilham os 72 primeiros bytes abrem a
        // mesma conta, e ninguem descobre.
        $this->expectException(InvalidPassword::class);

        PlainPassword::fromString(str_repeat('a', 73));
    }

    public function test_conta_bytes_e_nao_caracteres_no_limite(): void
    {
        // 40 emojis de 4 bytes = 160 bytes, mas so 40 caracteres. Quem contar
        // caracteres deixa passar uma senha que o bcrypt vai truncar.
        $this->expectException(InvalidPassword::class);

        PlainPassword::fromString(str_repeat('🔐', 40));
    }

    public function test_nao_expoe_a_senha_ao_serializar(): void
    {
        // Senha em texto puro nunca pode escapar por var_dump, log de excecao
        // ou dd() — o rastro fica no arquivo de log para sempre.
        $senha = PlainPassword::fromString('senha-forte-123');

        $this->assertStringNotContainsString('senha-forte-123', print_r($senha, true));
        $this->assertStringNotContainsString('senha-forte-123', json_encode($senha));
    }
}
