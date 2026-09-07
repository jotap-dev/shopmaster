---
name: criar-feature
description: >-
  Ciclo de TDD para criar ou estender uma feature no shopmaster — bounded
  context, portas, use case, migration, adapter, rota e documentação. Use ao
  implementar qualquer endpoint novo, ao adicionar um caso de uso a um
  contexto existente, ou ao criar um contexto do zero. Garante a ordem
  Red → Green → Refactor e o fechamento obrigatório: Swagger, /docs e README
  atualizados quando a suíte fica verde.
---

# Criar feature no shopmaster

Fluxo obrigatório para qualquer feature nova. O objetivo é consistência: toda feature nasce do mesmo jeito, com teste antes do código e documentação fechada na mesma entrega.

## Antes de escrever qualquer linha

Quase toda decisão de arquitetura já está tomada e escrita:

| Pergunta | Documento |
|---|---|
| A que bounded context isso pertence? Preciso criar um novo? | [docs/domains.md](../../../docs/domains.md) |
| Como as camadas se organizam e o que pode importar o quê | [docs/architecture/architecture.md](../../../docs/architecture/architecture.md) |
| Como uma compra flui ponta a ponta | [docs/architecture/data-flow.md](../../../docs/architecture/data-flow.md) |
| O que é Unit, o que é Feature, e como fazer fake de porta | [docs/testing.md](../../../docs/testing.md) |
| Em que fase isso entra e o que já existe | [docs/plano-de-desenvolvimento.md](../../../docs/plano-de-desenvolvimento.md) |

## O ciclo

### 1. Contexto e fronteira

Decida a qual contexto a feature pertence. Se ela precisa de dados de outro contexto, **não** importe o domínio dele — as três travessias legítimas estão na RULE 1 do `CLAUDE.md`. `tests/Architecture/DependencyRuleTest.php` reprova quem atravessar por dentro.

Contexto novo? Então:

1. Crie a árvore de pastas igual à de `src/ExampleContext/`.
2. Adicione a entrada em `autoload.psr-4` no `composer.json` **e** na constante `CONTEXTS` de `tests/Architecture/DependencyRuleTest.php`.
3. `composer dump-autoload`.
4. Registre os bindings em `app/Providers/AppServiceProvider.php`.
5. Registre as rotas em `routes/api.php`.
6. Adicione a `tag` no `docs/openapi/openapi.yaml` e a linha em `docs/domains.md`.

### 2. RED — teste antes da classe

Declare a porta (só a `interface`, sem implementação) e escreva o Unit test do use case com um **fake** dela, escrito à mão:

```php
private function catalogoCom(array $variantes): VariantRepository
{
    return new class($variantes) implements VariantRepository
    {
        public function __construct(private array $variantes) {}

        public function findBySku(Sku $sku): ?Variant
        {
            return $this->variantes[$sku->value()] ?? null;
        }
    };
}
```

Cubra o caminho feliz, **cada exceção de negócio** e os casos de borda (quantidade zero, produto arquivado, cupom vencido, recurso de outro cliente). Rode e confirme que falha **pela razão certa** — "classe não existe", não erro de sintaxe:

```bash
php artisan test --testsuite=Unit
```

Se a porta ganhou método novo, os fakes de testes existentes quebram: adicione o método neles lançando `LogicException('not needed by this test')`.

### 3. GREEN — o mínimo para passar

Implemente `Domain/` e `Application/`. Regra de negócio mora aqui, nunca no Controller nem no Resource. Um predicado de domínio (`Order::canBeCancelled()`) é o lugar certo de uma regra como "pedido enviado não cancela".

### 4. Migration + Infrastructure

Só depois de 2–3 verdes.

- Migration com `down()` que funciona de verdade. Coluna monetária é `numeric(12,2)`; identificador público exposto na API é UUID; atributo variável é `jsonb`; e-mail é `citext`.
- Adapter Eloquent com **allowlist explícita de colunas** — nunca `SELECT *`.
- Bind porta → adapter em `app/Providers/AppServiceProvider.php`, com o porquê quando não for óbvio.
- Feature test do repositório contra o Postgres de teste, com fixture vinda de factory.

### 5. Interface/Http

Controller + Resource + Form Request + rota. O Controller traduz HTTP ↔ domínio e mapeia exceção de negócio → status:

```php
try {
    $pedido = $realizarPedido->handle($clienteId, $carrinhoId);
} catch (CartIsEmpty $e) {
    return response()->json(ErrorResource::of('cart_is_empty', $e->getMessage()), 422);
} catch (OutOfStock $e) {
    return response()->json(ErrorResource::of('out_of_stock', $e->getMessage()), 409);
}
```

Rotas agrupadas por prefixo em `routes/api.php`, com o middleware aninhado dentro do grupo — não repita `prefix()`.

Feature test batendo na rota real, incluindo **o caso sem token** e **o caso com token de outro cliente** (que deve devolver 404, não 403 — não se revela a existência do recurso alheio).

### 6. Verificação fora da suíte

Feature test roda com variáveis próprias (`phpunit.xml`) e **pode mascarar bug de ambiente**. Bata no endpoint de verdade:

```bash
curl -s -H "Authorization: Bearer $TOKEN" http://127.0.0.1:8000/v1/orders | python3 -m json.tool
```

## Fechamento obrigatório — quando a suíte fica verde

Nada disso é opcional. Uma feature sem estes itens não está pronta:

- [ ] `composer test` verde (Unit, Feature **e** Architecture) e `./vendor/bin/pint` limpo
- [ ] Migration com `down()` testado — `php artisan migrate:fresh` roda do zero sem erro
- [ ] **Swagger atualizado**: path, schemas, exemplos e todos os códigos de erro em `docs/openapi/openapi.yaml`. Validar com `npx @redocly/cli lint docs/openapi/openapi.yaml` — zero erros
- [ ] **`/docs` atualizado** no que mudou: `domains.md` (status do contexto, composição), `auth.md`, `testing.md`, `docker.md`
- [ ] **`deploy.md` atualizado** se a feature precisa de algo no servidor — dizendo **o que quebra sem aquilo**, e se a ausência só desliga a funcionalidade em vez de derrubar a API
- [ ] **`README.md`** se mudou algo estrutural: tabela de endpoints, contextos, estrutura de pastas, stack
- [ ] `.env.example` se surgiu variável nova

## Erros a não repetir

- **Regra de negócio no Controller.** A regra vai no domínio; o Controller só orquestra.
- **Somar dinheiro fora do Pricing.** `Cart` e `Ordering` chamam `QuoteCart`; não recalculam.
- **Converter dinheiro fora do `Money`.** `(float)` sobre um `numeric` do banco perde centavo. Use `Money::fromDecimalString()` / `toDecimalString()`.
- **Importar o domínio do `Store` para checar permissão.** Chame `ResolveStoreRole`, ou use o middleware `store.role`.
- **Devolver 403 para recurso de outra loja.** É 404 — 403 confirma que a loja existe.
- **Expor campo que ninguém pediu.** RULE 4 do `CLAUDE.md`.
- **Deixar o Swagger para depois.** Ele desatualiza no primeiro endpoint esquecido e deixa de ser confiável.
- **Confiar só na suíte.** Ver passo 6.
- **Repetir `prefix()` em vez de aninhar** grupos de rota.
- **Evento disparado dentro de transação que faz rollback.** O evento sai depois do commit — senão o cliente recebe e-mail de um pedido que não existe.
