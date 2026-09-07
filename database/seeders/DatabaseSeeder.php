<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Semeadura de desenvolvimento.
 *
 * Cada contexto acrescenta o seu seeder aqui conforme nasce — catálogo de
 * demonstração, cupons, um admin e um cliente de teste (Fase 11 do plano).
 * Nenhum deles roda em produção: `--force` só é usado no deploy para as
 * migrations, nunca para seed.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // $this->call([
        //     IdentitySeeder::class,
        //     CatalogSeeder::class,
        // ]);
    }
}
