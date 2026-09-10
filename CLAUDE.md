# Regras do shopmaster

Regras obrigatórias deste repositório. Valem para qualquer alteração, por menor que pareça.

Para criar ou estender uma feature, siga a skill [`criar-feature`](.claude/skills/criar-feature/SKILL.md) — ela detalha o ciclo de TDD e o fechamento da entrega.

O plano completo, com o context map e as fases, está em [docs/plano-de-desenvolvimento.md](docs/plano-de-desenvolvimento.md).

---

## RULE 1 — A regra de dependência é lei, e é testada

`Domain/` e `Application/` **nunca** importam Laravel, Eloquent ou HTTP. Um contexto **nunca** importa `Domain/` ou `Infrastructure/` de outro contexto.

As três travessias legítimas de fronteira:

1. **Chamar o use case do outro contexto** (`Application/`), recebendo o VO dele.
2. **Compor na camada de interface** — o Controller chama dois use cases e junta no Resource.
3. **Reagir a um evento de domínio** publicado no `EventBus`.

Isso não é convenção de estilo: `tests/Architecture/DependencyRuleTest.php` falha se for violado. Se o teste de arquitetura ficar vermelho, o problema é o código, não o teste.

---

## RULE 2 — Documentação acompanha o código, na mesma entrega

Documentação desatualizada é pior que ausente: alguém confia nela e erra. Ao mexer em algo, pergunte o que deixou de ser verdade:

| Mudou | Atualize |
|---|---|
| Rota, request, response, código de erro | `docs/openapi/openapi.yaml` (ver RULE 3) |
| Bounded context — status, fronteira, composição entre contextos | `docs/domains.md` |
| Camadas, convenção de nomes, regra de dependência | `docs/architecture/architecture.md` |
| Caminho de uma compra ponta a ponta | `docs/architecture/data-flow.md` |
| Autenticação, token, middleware, papéis | `docs/auth.md` |
| Estratégia de teste, armadilha nova | `docs/testing.md` |
| Variável de ambiente, container | `docs/docker.md` + `.env.example` |
| Qualquer coisa que precise ser configurada **no servidor** | `docs/deploy.md` (ver RULE 5) |
| Estrutura de pastas, contextos, endpoints, stack | `README.md` |

Ao terminar, **verifique** — não presuma:

```bash
php artisan route:list --path=v1
```

---

## RULE 3 — O Swagger é contrato, e nunca fica para depois

`docs/openapi/openapi.yaml` é a fonte única do contrato HTTP, mantida à mão. É servido em `/docs`.

**Assim que a suíte ficar verde**, e antes de dar a tarefa por encerrada:

1. Atualize o path, os schemas, os exemplos e **todos** os códigos de erro que a rota pode devolver.
2. Valide, e resolva os erros:

```bash
npx @redocly/cli lint docs/openapi/openapi.yaml
```

Regras de conteúdo:

- Exemplos refletem a resposta **real** — de preferência copiada de uma chamada de verdade, não inventada.
- Rota pública leva `security: []` explícito; omitir o campo é ambíguo.
- Só documente códigos de erro que existem no código. Não invente cenários.
- Contexto novo ganha `tag` nova, com nome igual ao de `src/<Context>/`.

Um endpoint fora do Swagger é um endpoint que ninguém sabe que existe.

---

## RULE 4 — Nunca exponha dado sensível que a feature não pediu

O padrão é **negar por omissão**: um campo só aparece na resposta se a feature precisar dele. Na dúvida, não exponha.

Um e-commerce guarda o que mais dói vazar — hash de senha, CPF, endereço residencial, dado de pagamento, e-mail. **Como cumprir, na prática:**

- **Allowlist no repositório.** O `SELECT` lista as colunas explicitamente, nunca `*`. O que não é buscado não vaza — é a primeira linha de defesa, antes mesmo do Resource.
- **O domínio só carrega o que é exposto.** Se um campo não aparece na resposta, ele não deveria estar no Value Object nem no `SELECT`.
- **Nunca devolva número de cartão, nem os últimos dígitos, sem que a tela peça.** O gateway guarda; nós guardamos o token dele.
- **Escopo por usuário.** Toda consulta de dado pessoal filtra pelo usuário autenticado. Pedido de outro cliente devolve o mesmo 404 de "não existe", sem revelar que existe.
- **Prove com teste.** Feature test que afirma quais chaves a resposta tem (`array_keys`) e que os campos sensíveis **não** estão lá.

---

## RULE 5 — Configuração de servidor vive em `docs/deploy.md`

`docs/deploy.md` é a lista única do que precisa existir **fora do código** para a API funcionar em um servidor. Quem faz deploy lê aquele arquivo e mais nada.

Sempre que uma alteração introduzir algo que precise ser configurado no servidor — variável de ambiente, serviço externo, credencial em disco, permissão de diretório, porta, agendamento, worker — o `deploy.md` é atualizado **na mesma entrega**. Duas exigências de conteúdo:

- **Diga o que quebra sem aquilo.** "Sem isso, nenhum login funciona" vale mais que a descrição do campo. Quem está debugando um servidor lê pelo sintoma.
- **Degradação silenciosa é obrigatória de documentar.** Se a ausência de uma configuração não derruba a aplicação — só desliga uma funcionalidade —, isso precisa estar escrito, senão ninguém percebe que ficou faltando.

---

## RULE 6 — Dinheiro é `numeric` no banco e `Money` no código

**No Postgres:** `numeric(12,2)`. Nunca `float`/`double precision` — binário não representa `0.10`, e num carrinho de dez linhas isso vira divergência entre o que a API cobra e o que o cliente vê. E nunca o tipo **`money`** do Postgres: parece a escolha óbvia pelo nome e é armadilha, porque a escala dele depende do `lc_monetary` do servidor e ele não carrega qual moeda é.

**No PHP:** `Shared\Money`, sempre. Guarda inteiro em centavos e converte por **string** na fronteira do banco — `fromDecimalString()` / `toDecimalString()`. `(int) (19.99 * 100)` devolve 1998; esse centavo perdido é o tipo de bug que só aparece no fechamento do mês. **Nenhuma outra classe converte dinheiro.**

**Na API:** inteiro em centavos, `{"amount": 19990, "currency": "BRL"}`. JSON não tem decimal.

Percentual de desconto e de comissão são decisões de negócio com regra de arredondamento própria: descontos moram em `Pricing`, comissão é congelada por linha no checkout. `Cart` e `Ordering` **não** somam preço por conta própria — os dois chamam `QuoteCart`.

---

## RULE 7 — Autorização tem duas camadas, e elas não se misturam

| Camada | Onde vive | Como se checa |
|---|---|---|
| Papel de **plataforma** (`buyer`, `seller`, `platform_admin`) | No token | middleware `role:platform_admin` |
| Papel de **loja** (`owner`, `admin`, `finance`) | **Fora** do token, resolvido por requisição | middleware `store.role:admin`, a partir do `{storeId}` da rota |

Papel de loja **nunca** entra no token: o número de lojas por usuário é ilimitado, e um token que as carregasse ficaria obsoleto no instante em que alguém fosse removido de uma loja.

Nenhum contexto consulta a tabela de membros diretamente — chama `Store\Application\ResolveStoreRole`. E recurso de loja alheia devolve **404**, nunca 403: um 403 confirmaria que a loja existe.

---

## Banco de dados

Esta API **é dona** do schema, ao contrário do participant-api (que lia uma base legada e tinha migrations proibidas).

- `database/migrations/` é a **fonte da verdade** do schema. Toda mudança estrutural nasce como migration.
- Migration precisa de `down()` que funcione de verdade — Postgres faz DDL transacional, não há desculpa.
- Testes rodam com `RefreshDatabase` contra `shopmaster_test`, um banco **descartável**. O nome vem do `phpunit.xml`, e `tests/TestCase.php` confirma a cada teste que a suíte não está apontada para o banco de desenvolvimento.
- **Fixture nasce do próprio repositório** (`$repositorio->add(...)`) ou de um helper de teste — nunca de `INSERT` cru, e nunca com id hardcoded. Não há factory do Eloquent porque não há Model do Eloquent: os adapters usam Query Builder, e o domínio não conhece ORM. `INSERT` direto só é aceitável quando o teste está justamente simulando escrita de fora da aplicação (um `psql` na mão), e isso precisa estar escrito no teste.
