<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Confirma, a cada teste, que a suíte não está apontada para o banco de
     * desenvolvimento.
     *
     * `RefreshDatabase` **apaga** o schema a cada execução. O banco de teste
     * vem do `phpunit.xml` (que vence o `.env`, porque o Dotenv do Laravel
     * não sobrescreve variável já presente no ambiente); esta guarda é a
     * rede embaixo disso — um `.env` mal copiado ou um `DB_DATABASE`
     * exportado no shell deixariam a suíte comendo dado de dev em silêncio.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $emUso = (string) config('database.connections.pgsql.database');
        $desenvolvimento = $this->bancoDeDesenvolvimento();

        if ($desenvolvimento !== null && $emUso === $desenvolvimento) {
            $this->fail(
                "A suite esta apontada para '{$emUso}', o mesmo banco do .env. ".
                'RefreshDatabase apagaria o banco de desenvolvimento. '.
                'Confira DB_DATABASE no phpunit.xml.'
            );
        }
    }

    /** Lê o `.env` do disco — não `env()`, que já reflete o phpunit.xml. */
    private function bancoDeDesenvolvimento(): ?string
    {
        $env = base_path('.env');

        if (! is_readable($env)) {
            return null;
        }

        if (preg_match('/^DB_DATABASE=(.*)$/m', (string) file_get_contents($env), $m) !== 1) {
            return null;
        }

        return trim($m[1], " \t\"'");
    }
}
