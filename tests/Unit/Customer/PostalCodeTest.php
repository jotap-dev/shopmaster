<?php

declare(strict_types=1);

namespace Tests\Unit\Customer;

use Customer\Domain\InvalidPostalCode;
use Customer\Domain\PostalCode;
use PHPUnit\Framework\TestCase;

final class PostalCodeTest extends TestCase
{
    public function test_guarda_so_os_digitos(): void
    {
        $this->assertSame('01310100', PostalCode::fromString('01310-100')->value());
    }

    public function test_formata_com_hifen(): void
    {
        $this->assertSame('01310-100', PostalCode::fromString('01310100')->formatted());
    }

    public function test_recusa_tamanho_errado(): void
    {
        $this->expectException(InvalidPostalCode::class);

        PostalCode::fromString('01310');
    }
}
