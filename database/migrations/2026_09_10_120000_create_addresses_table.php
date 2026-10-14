<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RF-006 — agenda de endereços do comprador.
 *
 * Sem tabela `customers`: a conta já vive em `users` (Identity). Customer
 * guarda só o que é de e-commerce — endereço agora, favorito depois — e
 * aponta direto para o `user_id`. Uma linha `customers` 1:1 seria JOIN
 * sem ganho.
 *
 * No máximo um padrão por usuário: índice único parcial em `is_default`.
 * Quem garante a troca atômica é o adapter (desmarca o anterior e marca o
 * novo na mesma transação); o índice é a segunda linha de defesa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');

            $table->string('label', 40)->nullable();
            $table->string('recipient_name', 120);
            $table->string('street', 200);
            $table->string('number', 20);
            $table->string('complement', 120)->nullable();
            $table->string('neighborhood', 120);
            $table->string('city', 120);
            $table->char('state', 2);
            $table->char('postal_code', 8);
            $table->boolean('is_default')->default(false);
            $table->timestampsTz();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('user_id');
        });

        // Um padrão por usuário. Contas sem padrão (agenda vazia) não colidem
        // porque a condição exclui `is_default = false`.
        DB::statement(
            'CREATE UNIQUE INDEX addresses_one_default_per_user
             ON addresses (user_id)
             WHERE is_default = true'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
