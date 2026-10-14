<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RF-001 e RF-002 — o usuário da plataforma e seus papéis globais.
 *
 * Duas tabelas, não uma coluna: o papel é acumulável (quem vende também
 * compra), e uma coluna única não comportaria isso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            // UUIDv7 gerado pela aplicação — ordenável no tempo, ao contrário
            // do v4, o que mantém os inserts agrupados no fim do índice em vez
            // de espalhados por toda a árvore.
            $table->uuid('id')->primary();

            $table->string('name', 120);
            $table->string('email', 255);
            $table->string('password_hash', 255);
            $table->timestampsTz();
        });

        // `citext` = texto case-insensitive. Sem ele, "Joao@x.com" e
        // "joao@x.com" seriam contas distintas, e a unicidade do RF-002
        // dependeria de todo caminho de escrita lembrar de normalizar.
        // O VO EmailAddress já normaliza; isto é a segunda linha de defesa,
        // e é a que continua valendo se alguém abrir o psql e inserir à mão.
        DB::statement('ALTER TABLE users ALTER COLUMN email TYPE citext');

        // O índice único vem depois do ALTER: mudar o tipo da coluna
        // reconstruiria o índice, e criá-lo aqui evita esse trabalho perdido.
        Schema::table('users', fn (Blueprint $table) => $table->unique('email'));

        Schema::create('user_platform_roles', function (Blueprint $table): void {
            $table->uuid('user_id');

            // Sem CHECK nem tipo ENUM no banco, de propósito: papel novo é
            // valor novo no enum PHP (Identity\Domain\PlatformRole), sem
            // migration. O enum é a única porta de escrita, e é ele que
            // garante o valor.
            $table->string('role', 32);

            $table->timestampTz('granted_at')->useCurrent();

            // Chave composta: o mesmo papel não se concede duas vezes.
            $table->primary(['user_id', 'role']);

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_platform_roles');
        Schema::dropIfExists('users');
    }
};
