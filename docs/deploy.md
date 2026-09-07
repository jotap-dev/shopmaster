# Deploy

Lista única do que precisa existir **fora do código** para a API funcionar em um servidor. Quem faz deploy lê este arquivo e mais nada — se algo não estiver aqui, vai faltar no ambiente, e a falha aparece em produção.

## Serviços externos

| Serviço | Versão mínima | O que quebra sem ele |
|---|---|---|
| PostgreSQL | 16 | **Nada funciona.** A API não sobe sem banco. As extensões `pgcrypto`, `citext` e `pg_trgm` são criadas pela primeira migration — o usuário do banco precisa de permissão para `CREATE EXTENSION`. |
| Redis | 7 | **Nada funciona.** Cache, carrinho de visitante, reserva de estoque, revogação de refresh token e fila dependem dele. |

## Variáveis de ambiente

| Variável | Obrigatória | O que quebra sem ela |
|---|---|---|
| `APP_KEY` | Sim | A aplicação não sobe. Gere com `php artisan key:generate`. |
| `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | Sim | Sem banco, nenhuma rota responde. |
| `REDIS_HOST` / `REDIS_PORT` | Sim | Sem Redis, catálogo, carrinho e checkout falham. |
| `REDIS_CLIENT` | Sim | Deve ser `predis`. Com `phpredis` (o default do Laravel) a aplicação quebra se a extensão não estiver compilada no servidor. |
| `JWT_SECRET` | **Sim em servidor** | Cai na `APP_KEY` se ausente — funciona, mas amarra o ciclo de vida dos tokens ao da chave da aplicação: girar a `APP_KEY` deslogaria todo mundo sem aviso. Defina explicitamente. |
| `APP_DEBUG` | Sim | Deve ser `false`. Com `true`, stack trace e variáveis de ambiente vazam na resposta de erro. |
| `APP_URL` | Sim | Links absolutos (inclusive o do Swagger) saem errados. |

### Degradação silenciosa — o que **não** derruba a API, só desliga uma funcionalidade

| Ausente | O que para de funcionar, em silêncio |
|---|---|
| `MAIL_*` configurado | Confirmação de pedido e recibo de pagamento não chegam. A compra funciona normalmente; ninguém percebe até o cliente reclamar. |
| Worker da fila rodando | **Idem**: os jobs entram na fila e ficam lá. `php artisan queue:work` precisa estar sob supervisão (systemd, supervisord ou container próprio). |
| `CATALOG_CACHE_TTL` | Usa o default de 300s. Sem impacto funcional; muda só a pressão no banco. |

## No deploy

```bash
composer install --no-dev --optimize-autoloader
```

```bash
php artisan migrate --force
```

```bash
php artisan config:cache && php artisan route:cache
```

**Não** rode `php artisan migrate:fresh` — apaga o banco.

Após qualquer mudança de `.env`, `php artisan config:clear` antes de recachear: a config cacheada ignora o `.env`.

## Processos em background

| Processo | Quando | O que quebra sem ele |
|---|---|---|
| `php artisan queue:work --tries=3` | A partir da Fase 9 | Nenhum e-mail transacional sai, e o webhook de pagamento não é processado assincronamente. |

## Permissão de escrita

`storage/` e `bootstrap/cache/` precisam ser graváveis pelo usuário do PHP-FPM. Sem isso, a primeira requisição devolve 500 ao tentar escrever log ou view compilada.
