<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RF-005 — telefone e documento na conta.
 *
 * Ambos nascem nulos: o cadastro (RF-001) pede só nome, e-mail e senha, e
 * estes são preenchidos depois, em "minha conta".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Só dígitos, DDD + 8 ou 9 — ver Identity\Domain\PhoneNumber.
            $table->string('phone', 11)->nullable();

            // O valor normalizado: dígitos para CPF/CNH, alfanumérico
            // maiúsculo para passaporte.
            $table->string('document', 20)->nullable();

            // O tipo decide COMO validar. Sem coluna separada, um CPF e uma
            // CNH seriam indistinguíveis — os dois têm 11 dígitos, e o mesmo
            // número pode ser válido como um e inválido como o outro.
            $table->string('document_type', 16)->nullable();
        });

        // Unicidade por (tipo, valor): decisão de projeto — um CPF pertence a
        // uma pessoa, e duas contas com o mesmo viram fazenda de contas, o que
        // importa num marketplace onde comprador vira lojista.
        //
        // Compor com o tipo evita colisão entre um CPF e um passaporte que
        // por acaso tenham o mesmo número. E, no Postgres, NULLs são
        // distintos entre si, então contas sem documento não colidem.
        Schema::table('users', fn (Blueprint $table) => $table->unique(['document_type', 'document']));

        // Os dois andam juntos ou nenhum. Um documento sem tipo é um valor que
        // ninguém sabe validar; um tipo sem valor não é nada.
        DB::statement(
            'ALTER TABLE users ADD CONSTRAINT users_document_pair_check
             CHECK ((document IS NULL) = (document_type IS NULL))'
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_document_pair_check');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['document_type', 'document']);
            $table->dropColumn(['phone', 'document', 'document_type']);
        });
    }
};
