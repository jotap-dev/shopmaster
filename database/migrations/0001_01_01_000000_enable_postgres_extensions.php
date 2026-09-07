<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fundação do schema: as extensões que o domínio vai exigir.
 *
 * Vem antes de qualquer tabela porque `citext` e `uuid` são usados já na
 * primeira migration de contexto (Identity), e uma extensão ausente só
 * aparece como erro no meio do `migrate` de quem clonou o projeto.
 *
 * - pgcrypto: `gen_random_uuid()` para as chaves primárias.
 * - citext:   e-mail case-insensitive sem `LOWER()` espalhado por toda query.
 * - pg_trgm:  busca por similaridade no catálogo (índice GIN), sem motor externo.
 */
return new class extends Migration
{
    private const EXTENSIONS = ['pgcrypto', 'citext', 'pg_trgm'];

    public function up(): void
    {
        foreach (self::EXTENSIONS as $extension) {
            DB::statement("CREATE EXTENSION IF NOT EXISTS {$extension}");
        }
    }

    public function down(): void
    {
        // Extensão não é derrubada no rollback: outras tabelas do banco podem
        // depender dela, e `DROP EXTENSION` em cascata apagaria índice alheio.
    }
};
