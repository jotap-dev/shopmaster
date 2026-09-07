# Testes e TDD

Este projeto é desenvolvido **test-first**: o teste nasce antes da classe de produção, no estado vermelho, e só então vem a implementação mínima para ficar verde.

## Por que dá pra fazer TDD aqui sem banco nem HTTP

Toda regra de negócio (`Domain/`, `Application/`) depende só de **interfaces** (portas), nunca de Eloquent, Redis ou HTTP diretamente — é o que o `AppServiceProvider` centraliza. No teste, a porta é implementada por um **fake** de poucas linhas. TDD rápido é consequência direta de portas & adapters bem aplicado: se um teste de use case está lento ou precisa de banco, alguma dependência concreta vazou para dentro do `Application/`.

## Onde mora "unitário" e onde mora "feature"

A régua não é "está em `Domain/`" — é **tem I/O real ou não**:

| Camada | Tem I/O? | Teste | O que prova |
|---|---|---|---|
| `Domain/` | Nunca | **Unit** | Invariantes, construção de VO, transições de estado |
| `Application/` | Não — só via portas | **Unit**, com fake | Orquestração: qual porta é chamada, em que ordem, qual exceção sai de qual cenário |
| `Infrastructure/` sem I/O (JWT, cálculo puro) | Não | **Unit** direto | O algoritmo em si |
| `Infrastructure/` com I/O (Eloquent, Redis) | Sim | **Feature** | Que a query/mapeamento traduz a linha real certo |
| `Interface/Http/` | Sim | **Feature** | Rota, validação, status HTTP, formato do JSON |
| Fronteira entre contextos | — | **Architecture** | Que ninguém atravessou por dentro |

## O padrão de fake

Fake é uma implementação de verdade da porta, escrita à mão, pequena — não Mockery. Isso só é barato **porque** as portas são pequenas e nomeadas como substantivo puro:

```php
private function estoqueCom(array $saldos): StockRepository
{
    return new class($saldos) implements StockRepository
    {
        public function __construct(private array $saldos) {}

        public function availableFor(Sku $sku): int
        {
            return $this->saldos[$sku->value()] ?? 0;
        }
    };
}
```

## A ordem de escrever, ao construir um contexto novo

1. **Domain** — se houver invariante pura, o teste da regra vem antes da classe. VO sem comportamento (só carrega dado) não ganha teste próprio; é exercitado por quem o consome.
2. **Application** — declara a porta (só a interface) → escreve o teste do use case com fake, cobrindo caminho feliz e **cada** exceção de negócio → implementa até passar.
3. **Infrastructure** — só depois de 1–2 verdes: migration + adapter real, testado via **Feature test** contra o Postgres de teste.
4. **Interface/Http** — por último: Controller + Resource + rota, via Feature test batendo na rota HTTP real.

Rodar a cada passo, não só no final:

```bash
php artisan test --filter NomeDoTeste
```

```bash
composer test
```

## Nomenclatura dos testes

Método = `test_` + comportamento descrito em português, no que o sistema *faz*, não em como foi implementado — `test_recusa_checkout_quando_o_carrinho_esta_vazio`, não `test_handle_retorna_false`. O teste é legível como especificação.

## Banco de dados nos testes

Feature tests rodam com `RefreshDatabase` contra **`shopmaster_test`**, um banco descartável e separado do de desenvolvimento.

O nome do banco vem do `phpunit.xml`, não do `.env`. O motivo é a ordem de precedência: variável já presente no ambiente vence o `.env` (o Dotenv do Laravel não sobrescreve o que já existe), então o `phpunit.xml` sempre ganha. Como `RefreshDatabase` **apaga** o schema a cada execução, `tests/TestCase.php` confirma a cada teste que a suíte não está apontada para o banco de desenvolvimento — um `.env` mal copiado ou um `DB_DATABASE` exportado no shell comeriam dado de dev em silêncio.

Duas regras de fixture:

- **Fixture nasce de factory**, nunca de `INSERT` cru.
- **Nunca hardcode `id`.** Use o id devolvido pela factory.

Ao contrário de um schema legado, aqui não há armadilha de engine nem de charset: Postgres é UTF-8, transacional inclusive em DDL, e o schema é desenhado por nós.

## Redis nos testes

Os stores de Redis continuam sendo Redis **de verdade** na suíte — é justamente o que se quer provar —, mas na faixa alta de DBs (12 a 15, ver `phpunit.xml`). Entrada de teste nunca serve requisição de dev, e o isolamento entre usos vale na suíte também: carrinho de teste não colide com cache de catálogo de teste.

Escrita em serviço externo (gateway de pagamento, transportadora) **nunca** acontece na suíte: a porta é substituída por uma implementação em memória com `$this->app->instance(...)`, que também permite simular falha e provar o comportamento degradado.

## Teste de arquitetura

`tests/Architecture/DependencyRuleTest.php` lê os `use` de cada arquivo em `src/` e reprova quem quebrou a regra de dependência. É a guarda que impede a arquitetura de existir só no papel.

```bash
php artisan test --testsuite=Architecture
```

## Verificação fora da suíte

Feature test roda com variáveis próprias (`phpunit.xml`) e **pode mascarar bug de ambiente**. Antes de dar uma feature por pronta, bata no endpoint de verdade com `curl`.
