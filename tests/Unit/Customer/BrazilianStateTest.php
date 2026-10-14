<?php

declare(strict_types=1);

namespace Tests\Unit\Customer;

use Customer\Domain\BrazilianState;
use Customer\Domain\InvalidBrazilianState;
use PHPUnit\Framework\TestCase;

final class BrazilianStateTest extends TestCase
{
    public function test_aceita_uf_em_minusculas(): void
    {
        $this->assertSame(BrazilianState::SP, BrazilianState::fromString('sp'));
    }

    public function test_recusa_uf_inventada(): void
    {
        $this->expectException(InvalidBrazilianState::class);

        BrazilianState::fromString('XX');
    }
}
