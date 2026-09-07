<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * A regra de dependência, testada.
 *
 * Arquitetura que só existe em documento apodrece no primeiro prazo apertado.
 * Estes testes falham no CI quando alguém — inclusive um agente — encosta o
 * domínio no framework ou atravessa a fronteira de um bounded context por
 * dentro. É a guarda que o participant-api não tinha.
 */
final class DependencyRuleTest extends TestCase
{
    /** Contextos com namespace PSR-4 raiz próprio. Espelha o composer.json. */
    private const CONTEXTS = [
        'Identity', 'Customer', 'Store', 'Catalog', 'Pricing', 'Inventory', 'Cart',
        'Ordering', 'Payment', 'Shipping', 'Notification', 'Review', 'ExampleContext',
    ];

    /**
     * Domain/ e Application/ são o negócio. Não sabem que Laravel existe —
     * é isso que permite testá-los sem banco, sem HTTP e em milissegundos.
     */
    public function test_domain_e_application_nao_dependem_do_framework(): void
    {
        $proibidos = [
            'Illuminate\\',
            'Laravel\\',
            'Symfony\\',
            'Eloquent',
        ];

        $violacoes = [];

        foreach ($this->arquivosPhpEm('src') as $arquivo) {
            $caminho = $this->caminhoRelativo($arquivo);

            if (! preg_match('#^src/[^/]+/(Domain|Application)/#', $caminho)
                && ! preg_match('#^src/Shared/[^/]+/(Domain|Application)/#', $caminho)) {
                continue;
            }

            foreach ($this->importesDe($arquivo) as $import) {
                foreach ($proibidos as $proibido) {
                    if (str_starts_with($import, $proibido)) {
                        $violacoes[] = "{$caminho} importa {$import}";
                    }
                }
            }
        }

        $this->assertSame([], $violacoes, "Regra de dependencia violada:\n".implode("\n", $violacoes));
    }

    /**
     * Um contexto nunca alcança o Domain/ ou a Infrastructure/ de outro.
     *
     * As três travessias legítimas são: chamar o use case do outro contexto
     * (Application/), compor na camada de interface, ou reagir a um evento
     * de domínio publicado no EventBus.
     */
    public function test_contexto_nao_importa_o_interior_de_outro_contexto(): void
    {
        $violacoes = [];

        foreach ($this->arquivosPhpEm('src') as $arquivo) {
            $caminho = $this->caminhoRelativo($arquivo);

            if (! preg_match('#^src/([^/]+)/#', $caminho, $m)) {
                continue;
            }

            $contextoDono = $m[1];

            foreach ($this->importesDe($arquivo) as $import) {
                foreach (self::CONTEXTS as $outro) {
                    if ($outro === $contextoDono) {
                        continue;
                    }

                    if (preg_match("#^{$outro}\\\\(Domain|Infrastructure)\\\\#", $import)) {
                        $violacoes[] = "{$caminho} importa {$import} — atravesse pelo use case, pela interface ou por evento";
                    }
                }
            }
        }

        $this->assertSame([], $violacoes, "Fronteira de contexto violada:\n".implode("\n", $violacoes));
    }

    /** Domínio não se estende. Comportamento novo é classe nova, não subclasse. */
    public function test_classes_de_dominio_sao_finais(): void
    {
        $violacoes = [];

        foreach ($this->arquivosPhpEm('src') as $arquivo) {
            $caminho = $this->caminhoRelativo($arquivo);

            if (! preg_match('#/(Domain|Application)/#', $caminho)) {
                continue;
            }

            $conteudo = (string) file_get_contents($arquivo->getPathname());

            // Interface, enum, trait e abstract não entram na regra.
            if (preg_match('/^\s*(interface|enum|trait|abstract class)\s/m', $conteudo)) {
                continue;
            }

            if (preg_match('/^\s*(?!final)(readonly\s+)?class\s+/m', $conteudo)) {
                $violacoes[] = "{$caminho} declara classe nao-final";
            }
        }

        $this->assertSame([], $violacoes, "Classe de dominio deve ser final:\n".implode("\n", $violacoes));
    }

    /** Toda pasta de contexto tem entrada PSR-4 própria — senão nada autoloada. */
    public function test_todo_contexto_tem_namespace_psr4_registrado(): void
    {
        $composer = json_decode((string) file_get_contents($this->raiz().'/composer.json'), true);
        $psr4 = $composer['autoload']['psr-4'];

        foreach (self::CONTEXTS as $contexto) {
            $this->assertArrayHasKey(
                "{$contexto}\\",
                $psr4,
                "Contexto {$contexto} existe em src/ mas nao tem PSR-4 no composer.json.",
            );
        }

        $this->assertArrayHasKey('Shared\\', $psr4);
    }

    /** @return list<SplFileInfo> */
    private function arquivosPhpEm(string $diretorio): array
    {
        $iterador = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->raiz().'/'.$diretorio, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $arquivos = [];

        foreach ($iterador as $arquivo) {
            if ($arquivo instanceof SplFileInfo && $arquivo->getExtension() === 'php') {
                $arquivos[] = $arquivo;
            }
        }

        return $arquivos;
    }

    /** @return list<string> */
    private function importesDe(SplFileInfo $arquivo): array
    {
        $conteudo = (string) file_get_contents($arquivo->getPathname());

        preg_match_all('/^use\s+(?:function\s+)?([^;\s]+)/m', $conteudo, $m);

        return $m[1];
    }

    private function caminhoRelativo(SplFileInfo $arquivo): string
    {
        return str_replace($this->raiz().'/', '', $arquivo->getPathname());
    }

    private function raiz(): string
    {
        return dirname(__DIR__, 2);
    }
}
