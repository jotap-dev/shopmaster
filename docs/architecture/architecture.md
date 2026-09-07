# Arquitetura

O `shopmaster` é uma API REST em Laravel com o domínio de negócio modelado em **DDD**, organizado em **bounded contexts** dentro de `src/`.

**Não é um BFF.** Loja e back-office consomem os mesmos recursos; o que os separa é autorização (papel no token), não formato de resposta.

## Visão geral

```
app/                          # Laravel puro: Providers, Kernel HTTP
  Providers/
    AppServiceProvider.php     # bindings porta → adapter de cada bounded context

bootstrap/app.php              # rotas, alias de middleware, erro em JSON sob /v1

routes/
  api.php                      # /v1 público + /v1/admin
  web.php                      # /docs (Swagger UI)

database/
  migrations/                  # A FONTE DO SCHEMA — esta API é dona do banco

src/                           # domínio de negócio, fora do Laravel
  Shared/                      # kernel compartilhado (EventBus, Money, Clock, ErrorResource)
  <BoundedContext>/
    Domain/                    # Value Objects, enums de estado, eventos de domínio
    Application/               # Use Cases, portas (interfaces), exceções de negócio
    Infrastructure/
      Eloquent/                # repositórios concretos
      Cache/                   # adapters Redis
      Http/                    # clients de serviço externo (Payment, Shipping)
    Interface/Http/
      Controllers/
      Resources/
      Requests/
      Admin/                   # SÓ quando o payload de back-office divergir de verdade
```

Cada bounded context tem seu **próprio namespace PSR-4 raiz** (ex.: `Catalog\`, não `App\Catalog`), declarado em `composer.json`. `App\` fica reservado à infraestrutura genérica do framework.

## As camadas, e o que cada uma faz

### `Domain/`

Regras e conceitos que não dependem de nada — nem de banco, nem de HTTP, nem do Laravel.

- **Value Objects**: imutáveis, sem setters, construção controlada por factory estática. Ver `Shared\Money`.
- **Enums de estado** com as transições válidas explícitas (`OrderStatus`).
- **Eventos de domínio**: DTOs imutáveis que descrevem um fato consumado (`OrderPlaced`, `PaymentApproved`).

### `Application/`

Orquestra o domínio.

- **Use Cases**: uma classe por ação de negócio, um único método `handle()`, injeção via construtor, **zero conhecimento de HTTP ou Eloquent**.
- **Portas**: a interface que a Infrastructure implementa. O use case depende só dela.
- **Exceções de negócio**: uma classe por invariante violada (`OutOfStock`, `CartIsEmpty`, `OrderAlreadyPaid`), sempre `extends \RuntimeException`. Fazem o papel de um "Result" implícito — o Controller decide o status via `catch` por tipo. Sem Either/Result.

### `Infrastructure/`

Implementações concretas das portas: repositórios Eloquent (com allowlist de colunas), adapters de Redis, clients de serviço externo. É aqui que moram os **Null Objects** quando a infraestrutura real ainda não existe.

### `Interface/Http/`

Onde a requisição entra e a resposta sai. Controllers, Resources e Form Requests — **nada de regra de negócio**. O Controller valida, chama um use case, formata com um Resource e mapeia exceção de negócio → status HTTP.

## Injeção de dependência

Container nativo do Laravel. Todo bind porta → adapter em `app/Providers/AppServiceProvider.php`. Nenhuma classe de `Domain/` ou `Application/` depende de implementação concreta — a troca acontece **só** nesse arquivo.

## Eventos de domínio

Porta `Shared\EventBus` (`dispatch(object $event): void`), com `Shared\NullEventBus` como implementação padrão enquanto não há listener. Use cases publicam sem saber quem reage:

```php
$this->eventBus->dispatch(new OrderPaid($orderId));
```

**O evento sai depois do commit.** Evento disparado dentro de uma transação que faz rollback é e-mail mentiroso na caixa do cliente.

## Convenções de nomenclatura

- Use case = verbo + substantivo (`PlaceOrder`, `GetProductDetails`, `ReserveStock`).
- Porta = substantivo puro (`ProductRepository`, `PaymentGateway`, `StockLedger`).
- Exceção de negócio = fato de negócio, não erro genérico (`OrderAlreadyPaid`, não `InvalidOrderException`).
- `final class` / `final readonly class` em domínio e application — sem herança de domínio.

## A regra de dependência, testada

`tests/Architecture/DependencyRuleTest.php` falha quando:

- uma classe de `Domain/` ou `Application/` importa `Illuminate\`, `Laravel\` ou `Symfony\`;
- um contexto importa `Domain/` ou `Infrastructure/` de outro contexto;
- uma classe de domínio não é `final`;
- uma pasta de contexto existe em `src/` sem PSR-4 no `composer.json`.

Arquitetura que só existe em documento apodrece no primeiro prazo apertado.

## Testes

Regra de negócio (`Domain/`, `Application/`) é testada por **Unit test com fake das portas** — nunca precisa de banco ou HTTP. `Infrastructure/` com I/O e `Interface/Http/` são cobertas por **Feature test**. O projeto segue TDD: o teste nasce antes da implementação. Ver [testing.md](../testing.md).
