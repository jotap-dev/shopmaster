# Docker

O container roda **só a aplicação**. PostgreSQL e Redis são serviços do **host**, como no participant-api — é o setup local deste projeto, e o compose já aponta para eles via `host.docker.internal`.

## Pré-requisitos

- **PostgreSQL** rodando no host, com os bancos `shopmaster` e `shopmaster_test` criados.
- **Redis** rodando no host.

```bash
createdb shopmaster && createdb shopmaster_test
```

## Subir

**Só no macOS**, é preciso subir a VM antes — o Docker Engine é nativo do Linux e no macOS roda dentro de uma VM. Em Linux e Windows (WSL2) este passo não existe:

```bash
colima start
```

Daí em diante o fluxo é igual em qualquer plataforma:

```bash
docker compose up -d --build
```

```bash
docker compose exec app php artisan migrate
```

A API responde em `http://localhost:8000`; a documentação em `http://localhost:8000/docs`.

Se a porta 8000 já estiver ocupada (outro projeto rodando, por exemplo), escolha outra — só o lado do host muda:

```bash
APP_PORT=8001 docker compose up -d
```

## O worker da fila

Só entra em cena na Fase 9 (Notification), por isso está atrás de um profile:

```bash
docker compose --profile worker up -d
```

## Armadilhas

- **`vendor/` é um volume anônimo.** O bind mount `.:/app` esconderia o `vendor/` instalado na imagem; o `- /app/vendor` do compose protege ele. Se você instalar uma dependência nova, reconstrua: `docker compose up -d --build`.
- **`host.docker.internal` no Linux.** Não resolve sozinho; o `extra_hosts` do compose resolve isso. No Colima ele aponta para o mesmo IP, então declarar é seguro nas duas plataformas.
- **Postgres precisa aceitar conexão do container.** Se o Postgres do host escuta só em `localhost`, o container não alcança. Ajuste `listen_addresses` e o `pg_hba.conf`, ou use a variante containerizada abaixo.
- **Um DB do Redis por uso.** `0` locks, `1` cache, `2` catálogo, `3` carrinho, `4` reservas, `5` fila, `6` revogação de refresh token. Não é preciosismo: apontar dois usos para o mesmo DB faz um `cache:clear` derrubar o outro.
- **Redis é falado por `predis`** (PHP puro), não pela extensão `phpredis` — por isso o Dockerfile não compila `phpredis`. `REDIS_CLIENT=predis` no `.env` é obrigatório.

## Variante: serviços em container

Se preferir não depender dos serviços do host, acrescente ao `compose.yaml`:

```yaml
  postgres:
    image: postgres:16-alpine
    environment:
      POSTGRES_DB: shopmaster
      POSTGRES_USER: shopmaster
      POSTGRES_PASSWORD: secret
    volumes:
      - pgdata:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U shopmaster"]
      interval: 5s
      retries: 10

  redis:
    image: redis:7-alpine
    command: ["redis-server", "--appendonly", "yes"]

volumes:
  pgdata:
```

E, no serviço `app`, troque as variáveis para `DB_HOST: postgres` / `REDIS_HOST: redis` e adicione `depends_on` com `condition: service_healthy` — sem isso a primeira migration corre antes de o banco aceitar conexão.

## Nativo, sem Docker

```bash
composer install && cp .env.example .env && php artisan key:generate
```

```bash
php artisan migrate && php artisan serve
```
