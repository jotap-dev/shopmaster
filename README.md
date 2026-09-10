# ShopMaster API

API REST de **marketplace multi-lojista** — a plataforma não vende, hospeda lojas de terceiros. Arquitetura **DDD** com bounded contexts, **PostgreSQL** como banco próprio, **Redis** para cache, carrinho, reserva de estoque e fila, e fluxo de desenvolvimento em **TDD**.

Um mesmo usuário compra, é dono de várias lojas, e participa de lojas alheias com papéis distintos — tudo na mesma conta.

Referência de padrão: o `participant-api` — a mesma arquitetura em camadas, **sem** o split mobile/web (aqui não é BFF) e com o banco sendo nosso.

## Princípios

- **Domínio no centro, framework na borda.** `Domain/` e `Application/` não dependem de Laravel, HTTP ou Eloquent. A regra é testada em `tests/Architecture/`.
- **API REST comum, não BFF.** Comprador, lojista e plataforma consomem os **mesmos** recursos. O que os separa é autorização, não formato de resposta.
- **Autorização em duas camadas.** Papel de plataforma (`buyer`, `seller`, `platform_admin`) vive no token; papel de loja (`owner`, `admin`, `finance`) é resolvido por requisição. Confundir as duas é o erro estrutural mais caro deste projeto.
- **A loja é a unidade de fulfillment.** Carrinho com itens de N lojas vira um pedido e N sub-pedidos: cada loja cota o próprio frete e tem o próprio estado de entrega.
- **Esta API é dona do banco.** `database/migrations/` é a fonte da verdade do schema.
- **Stateless.** JWT (access + refresh de uso único). Nenhum estado de fluxo em sessão de servidor.
- **Allowlist de colunas.** Os repositórios selecionam explicitamente o que expõem. O que não é buscado não vaza.
- **Dinheiro é `numeric(12,2)` no banco e `Shared\Money` no código.** Nunca `float`, nunca o tipo `money` do Postgres. A conversão acontece por string, em um único lugar.
- **Test-first.** O teste nasce vermelho antes da implementação — ver [docs/testing.md](docs/testing.md).

## Bounded contexts

| Contexto | Tipo | Responsabilidade | Status |
|---|---|---|---|
| **Shared** | Kernel | `EventBus`, `Money`, `Clock`, `ErrorResource`, `Auth` | Parcial |
| **Identity** | Genérico | Registro, login, refresh, papéis de plataforma | Esqueleto |
| **Customer** | Suporte | Endereços salvos e favoritos | Parcial (endereços) |
| **Store** | Core | Loja, membros, papéis de loja, aprovação, comissão | Esqueleto |
| **Catalog** | Core | Produto de uma loja, variação (SKU global), categoria, busca | Esqueleto |
| **Inventory** | Core | Saldo por SKU, reserva, baixa | Esqueleto |
| **Pricing** | Core | Preço, promoção, cupom, cálculo do total | Esqueleto |
| **Cart** | Suporte | Carrinho de visitante e de cliente, itens de várias lojas | Esqueleto |
| **Shipping** | Suporte | Endereço, cotação de frete por loja | Esqueleto |
| **Ordering** | Core | Pedido, sub-pedido por loja, checkout, comissão | Esqueleto |
| **Payment** | Genérico | Intenção única por pedido, confirmação, estorno, webhook | Esqueleto |
| **Notification** | Genérico | E-mail transacional por evento | Esqueleto |
| **Review** | Suporte | Avaliação de quem recebeu | Esqueleto |

O context map completo, com o padrão de cada fronteira, está em [docs/plano-de-desenvolvimento.md](docs/plano-de-desenvolvimento.md#4-context-map). O estado vivo, em [docs/domains.md](docs/domains.md).

## Estrutura de pastas

```
src/
├── Shared/                    # EventBus, Money, Clock, ErrorResource, Auth
├── Identity/  Customer/  Store/    Catalog/  Pricing/  Inventory/  Cart/
├── Ordering/  Payment/   Shipping/ Notification/ Review/
└── ExampleContext/            # esqueleto de referência para o próximo contexto

# Por contexto:
#   Domain/          → Value Objects imutáveis, enums de estado, eventos de domínio
#   Application/     → 1 classe por use case + portas (interfaces) + exceções de negócio
#   Infrastructure/  → adapters (Eloquent, Redis, clients externos)
#   Interface/Http/  → Controllers, Resources, Requests

database/migrations/           # A FONTE DO SCHEMA

tests/
├── Unit/{Contexto}/           # use cases com fake das portas — sem I/O
├── Feature/{Contexto}/        # rotas HTTP e repositórios, contra o Postgres de teste
└── Architecture/              # a regra de dependência, testada
```

**Regra de ouro:** `Domain/` e `Application/` nunca importam Laravel, Eloquent ou HTTP; e um contexto nunca importa o interior de outro. Todo bind porta → adapter fica em `app/Providers/AppServiceProvider.php`.

## Endpoints

Prefixo `/v1`. Erros no formato `{ "error": { "code", "message" } }`.

A superfície tem três famílias, separadas por **autorização**, não por formato:

| Família | Prefixo | Proteção |
|---|---|---|
| Comprador | `/v1/...` | pública ou `auth.token` |
| Lojista | `/v1/stores/{storeId}/...` | `auth.token` + `store.role:{papel}` |
| Plataforma | `/v1/admin/...` | `auth.token` + `role:platform_admin` |

Hoje, no ar:

| Rota | Auth | Contexto | O quê |
|---|---|---|---|
| `POST /v1/auth/register` | pública | Identity | Cadastro (RF-001). Nasce com o papel `buyer` |
| `POST /v1/auth` | pública | Identity | Login (RF-003) → access + refresh token |
| `POST /v1/auth/refresh` | pública | Identity | Troca do refresh, de uso único (RF-004) |
| `GET /v1/auth/session` | Bearer | Identity | Quem sou eu, segundo este token |
| `GET /v1/me` | Bearer | Identity | Minha conta |
| `PATCH /v1/me` | Bearer | Identity | Editar nome, telefone e documento (RF-005) |
| `GET/POST /v1/me/addresses` | Bearer | Customer | Agenda de endereços (RF-006) |
| `GET/PATCH/DELETE /v1/me/addresses/{id}` | Bearer | Customer | Detalhe / editar / remover endereço |
| `PATCH /v1/admin/users/{id}/roles` | `platform_admin` | Identity | Conceder/revogar papéis (RF-007) |
| `GET /v1` | pública | — | Identificação da API |
| `GET /up` | pública | — | Health check |
| `GET /docs` | pública | — | Swagger UI |

As demais rotas entram conforme as fases do plano. Contrato em [docs/openapi/openapi.yaml](docs/openapi/openapi.yaml), mantido à mão.

## Stack

| Camada | Escolha |
|---|---|
| Runtime | PHP 8.3+ |
| Framework | Laravel 13 (só na borda) |
| Banco | PostgreSQL 16+ (`pgcrypto`, `citext`, `pg_trgm`); dinheiro em `numeric(12,2)` |
| Cache / fila / lock | Redis, via `predis` (um DB por uso) |
| Auth | JWT HS256 assinado na aplicação |
| Testes | PHPUnit 12 — Unit, Feature e Architecture |
| Estilo | Laravel Pint |
| Contrato | OpenAPI 3.1 à mão + Swagger UI em `/docs` |

## Setup local

Pré-requisitos: **PostgreSQL** e **Redis** rodando no host.

```bash
createdb shopmaster && createdb shopmaster_test
```

### Via Docker

**Só no macOS**, suba a VM antes:

```bash
colima start
```

```bash
docker compose up -d --build && docker compose exec app php artisan migrate
```

A API sobe em `http://localhost:8001` e a documentação em `/docs`. A porta é 8001 e não 8000 porque esta última é disputada demais; trocar é `APP_PORT=9005 docker compose up -d`.

O Postgres e o Redis **não** são containers — o app conecta nos do host. Detalhes e a variante com serviços containerizados em [docs/docker.md](docs/docker.md).

### Nativo

```bash
composer install && cp .env.example .env && php artisan key:generate
```

```bash
php artisan migrate && composer dev
```

O `composer dev` usa a mesma porta do Docker (8001), para o `APP_URL` valer nos dois modos.

## Comandos

```bash
composer test
```

```bash
./vendor/bin/pint
```

```bash
npx @redocly/cli lint docs/openapi/openapi.yaml
```

## Documentação

| Documento | O quê |
|---|---|
| [plano-de-desenvolvimento.md](docs/plano-de-desenvolvimento.md) | O plano completo: context map, fases, decisões e riscos |
| `docs/requisitos.md` | Requisitos funcionais e não funcionais, e o escopo do v1 — **fora do versionamento**, por decisão do projeto |
| [architecture/architecture.md](docs/architecture/architecture.md) | Camadas, regra de dependência, convenções de nome |
| [architecture/data-flow.md](docs/architecture/data-flow.md) | O caminho de uma compra, ponta a ponta |
| [domains.md](docs/domains.md) | Estado vivo dos bounded contexts e das fronteiras |
| [testing.md](docs/testing.md) | O que é Unit, o que é Feature, e como fazer fake de porta |
| [auth.md](docs/auth.md) | Contrato de autenticação, tokens e códigos de erro |
| [docker.md](docs/docker.md) | Setup local, armadilhas |
| [deploy.md](docs/deploy.md) | O que precisa existir no servidor |
| [CLAUDE.md](CLAUDE.md) | As regras obrigatórias do repositório |
