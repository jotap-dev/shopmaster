# Autenticação

> **Status: implementado.** Cadastro (RF-001/002), login (RF-003), refresh de uso único (RF-004) e o middleware `auth.token` estão no ar e cobertos por testes. Falta o middleware `role` (RF-007), para papéis de plataforma em rota administrativa.

JWT HS256 assinado na própria aplicação, sem dependência externa e sem par de chaves para rotacionar. Configuração em `config/jwt.php`.

## Os dois tokens

| Token | TTL padrão | Observação |
|---|---|---|
| access | 1 hora (`JWT_TTL`) | Trafega em toda requisição. Stateless: **não é revogável** antes de expirar — por isso o TTL é curto. |
| refresh | 30 dias (`JWT_REFRESH_TTL`) | **Uso único**: é revogado ao ser usado, e a revogação vive no Redis (DB 0). |

Refresh de uso único significa que um token roubado só serve uma vez — e, se o legítimo dono usar o dele primeiro, o do atacante é recusado. É o sinal de que houve vazamento.

## Papéis — duas camadas

Esta é a decisão que mais molda o desenho, e a que mais se erra em marketplace.

### Papel de plataforma — no token

`buyer`, `seller`, `platform_admin`, **acumuláveis**: o mesmo usuário compra e vende. Viajam num claim do token porque o conjunto é pequeno e limitado. O middleware `role:platform_admin` protege `/v1/admin/*`.

A contrapartida de estar no token: revogar um papel de plataforma só tem efeito quando o access token expira (no máximo 1 hora). Aceitável para uma operação rara e feita por outro admin.

### Papel de loja — **fora** do token

`owner`, `admin`, `finance`, **um por loja**. O mesmo usuário é `owner` da Loja A, `finance` da Loja B e nada na Loja C.

Não vão no token por dois motivos:

1. **Não têm teto.** Um usuário pode ser membro de dezenas de lojas; o token cresceria sem limite.
2. **Ficariam obsoletos.** Remover alguém de uma loja precisa ter efeito agora, não em até uma hora.

São resolvidos **por requisição**, pelo middleware `store.role:{papel}`, que lê o `{storeId}` da rota e pergunta ao `Store\Application\ResolveStoreRole`. A resposta é cacheada no Redis com TTL curto (`shopmaster.store.role_cache_ttl`) — e esse TTL **é** a janela de exposição depois de uma remoção.

| Papel de loja | Pode | Não pode |
|---|---|---|
| `owner` | Tudo: produtos, estoque, pedidos, membros, financeiro, encerrar a loja | — |
| `admin` | Produtos, estoque, preços, pedidos, despacho | Gerir membros, ver faturamento, encerrar a loja |
| `finance` | Faturamento, comissões, extrato | Tocar em produto, estoque ou pedido |

Nenhum contexto consulta `store_members` por conta própria. Mudar a regra de papéis é mexer em um lugar só.

## As rotas

| Rota | Auth | O quê |
|---|---|---|
| `POST /v1/auth/register` | pública | Cadastro (RF-001) |
| `POST /v1/auth` | pública | Login (RF-003) |
| `POST /v1/auth/refresh` | pública | Troca do refresh (RF-004) |
| `GET /v1/auth/session` | Bearer | **O que este token me permite** — papéis inclusos |
| `GET /v1/me` · `PATCH /v1/me` | Bearer | Minha conta (RF-005) — nome, contato e documento, **sem papéis** |
| `GET/POST/PATCH/DELETE /v1/me/addresses[/{id}]` | Bearer | Agenda de endereços (RF-006) — Customer |
| `PATCH /v1/admin/users/{id}/roles` | `platform_admin` | Conceder/revogar papéis (RF-007) |

### Por que os papéis só aparecem no `/session`

As duas rotas responderiam a mesma pergunta com respostas diferentes, e a diferença é legítima: o `/session` lê do **token**, o perfil leria do **banco**. Conceder um papel a alguém grava no banco na hora, mas o token daquela pessoa só passa a carregá-lo na próxima renovação.

Se o perfil devolvesse `roles`, o cliente veria `seller`, habilitaria a tela de vendedor, e tomaria `403` do middleware — que decide pelo token. Um erro assim é quase indepurável do lado de fora.

Então: **uma pergunta, uma fonte.** Perfil responde "quem eu sou"; sessão responde "o que eu posso agora". Cliente que receba um `403` inesperado compara com o `/session` — papel ausente ali significa token desatualizado, e a correção é renovar.

## Cadastro

`POST /v1/auth/register`, público, limitado a 10 requisições por minuto por IP.

O usuário nasce com o papel `buyer`, e essa regra mora em `Identity\Domain\NewUser::register()`.

**Unicidade do e-mail, em duas camadas.** O VO `EmailAddress` normaliza para minúsculas na escrita; a coluna é `citext`, então o Postgres compara sem diferenciar caixa mesmo por caminhos que esqueçam de normalizar. O `existsByEmail()` do use case é cortesia — dá mensagem clara e evita gastar os ~100ms do bcrypt à toa. Quem **garante** é a constraint `UNIQUE`, e o adapter traduz a violação de volta para `EmailAlreadyRegistered`. Sem isso, dois cadastros simultâneos do mesmo e-mail passariam os dois pela verificação.

**Revelar que o e-mail já existe é deliberado no cadastro**, por exigência do RF-002 — a pessoa precisa saber que já tem conta. É o oposto do login, onde distinguir "e-mail não existe" de "senha errada" entregaria uma lista de e-mails cadastrados.

## Login — e o que ele não conta

Toda falha sai por **`401 invalid_credentials`**, com a mesma mensagem: e-mail inexistente, e-mail malformado, senha errada, ou senha que nem passaria na política de cadastro. Distinguir qualquer uma delas transformaria a rota num verificador de "esta conta existe?", e uma base inteira de e-mails sai daí.

Duas consequências práticas do desenho:

- **O `LoginRequest` é deliberadamente frouxo** — sem `email:rfc`, sem `min` na senha. Um 422 dizendo "e-mail inválido" já contaria que a entrada nem chegou a ser comparada, e ainda revelaria a política vigente.
- **O tempo também é resposta.** Mensagem igual não basta se "e-mail não existe" retorna em microssegundos e "senha errada" em ~100ms: um cronômetro separa os dois casos. Por isso `PasswordHasher::verify()` aceita `null` no lugar do hash e, sem usuário, o adapter queima um bcrypt completo antes de devolver `false`.

## Refresh de uso único

`POST /v1/auth/refresh` devolve um **par novo** e revoga o refresh enviado. A revogação vive no Redis, num **store dedicado** (`auth`, DB 6): no store padrão, um `cache:clear` de rotina ressuscitaria todo refresh já queimado, e ninguém ligaria os dois fatos. Cada entrada expira junto com o token — depois do prazo dele, guardar a revogação não protege mais nada.

Ordem importa: **revoga antes de emitir**. Se a emissão falhar depois disso, o cliente perde a sessão e refaz o login — chato, mas seguro. Na ordem inversa, uma falha deixaria vivo um refresh que já foi entregue.

Três detalhes que ganharam teste:

- **O access token anterior continua valendo** até expirar. Só o refresh é revogável; é o TTL curto do access que limita o estrago.
- **Os papéis são recarregados do banco** na troca, não reaproveitados dos claims. É o momento em que uma mudança de papel passa a valer, e em que o token de uma conta apagada para de funcionar.
- **`refresh_token_already_used` é o único erro que se distingue**, e de propósito: quem o recebe tinha um refresh legítimo que já foi trocado. Ou o cliente repetiu a chamada, ou o token vazou e alguém chegou antes.

## O token, por dentro

JWT HS256 escrito à mão (`HmacJwtTokenIssuer`) — ~60 linhas de `hash_hmac` e base64url, e uma dependência a menos para auditar num caminho que é a porta de entrada da API. Sem I/O, então é coberto por Unit test direto, com o relógio injetado.

Claims: `iss`, `sub`, `email`, `roles`, `typ`, `jti`, `iat`, `exp`.

Três guardas que os testes fixam:

- **`typ` no payload.** Sem ele, um refresh (30 dias) seria aceito como access (1 hora), e a vida curta do access — a única defesa contra um token vazado, já que ele não é revogável — deixaria de existir. Vale nos dois sentidos: um access também não renova nada.
- **`alg` conferido antes da assinatura.** É o ataque `alg: none`. A assinatura vazia não bateria de qualquer forma, mas depender de coincidência em autenticação é como não ter a guarda.
- **`hash_equals` na comparação.** `===` sai no primeiro byte diferente, e o tempo até sair revela quantos bytes acertaram.

O `jti` é próprio de cada token: se access e refresh compartilhassem um, revogar o refresh derrubaria o access junto.

## Senha

`bcrypt` nativo do Laravel, com o custo em `config/hashing.php` (`BCRYPT_ROUNDS`, padrão 12).

**Política, em `Identity\Domain\PlainPassword`:** mínimo de 8 caracteres, máximo de **72 bytes**.

O teto é do algoritmo, não nosso: o bcrypt trunca silenciosamente em 72 bytes. Sem essa guarda, duas senhas que compartilhem os 72 primeiros bytes abrem a mesma conta — e ninguém descobre, porque o login simplesmente funciona. O limite conta bytes, não caracteres: um emoji ocupa 4, então 40 deles já estouram com apenas 40 caracteres digitados.

Nem a senha em texto puro nem o hash saem do repositório: `PlainPassword` e `HashedPassword` redigem o valor em `__debugInfo()` e `jsonSerialize()`, então `var_dump`, `print_r` e um `json_encode` de contexto de log imprimem `[REDACTED]`. Senha em arquivo de log fica lá para sempre.

## Erros

| Situação | Status | Código |
|---|---|---|
| E-mail ou senha errados | 401 | `invalid_credentials` |
| Token ausente ou malformado | 401 | `invalid_token` |
| Token expirado | 401 | `expired_token` |
| Refresh já usado | 401 | `refresh_token_already_used` |
| Papel de plataforma insuficiente | 403 | `forbidden` |
| Não é membro da loja, ou papel de loja insuficiente | **404** | `store_not_found` |
| E-mail já cadastrado | 422 | `email_already_registered` |
| Entrada inválida (campo faltando, formato, política de senha) | 422 | `validation_failed` — traz `error.fields` |
| Invariante de domínio violada, fora da validação da requisição | 422 | `invalid_name` · `invalid_email` · `invalid_password` |

**Login errado devolve sempre `invalid_credentials`**, tanto para e-mail inexistente quanto para senha errada. Distinguir os dois entrega uma lista de e-mails cadastrados a quem tentar.

## O middleware `auth.token`

Exige `Authorization: Bearer <access token>`. Resolve o usuário **sem tocar no banco** — identidade e papéis vêm dos claims assinados — e o registra no container da requisição, de onde qualquer controller o recebe por injeção de `AuthenticatedUser`.

O 401 vai com `WWW-Authenticate: Bearer`, como a RFC 9110 exige, e separa `expired_token` de `invalid_token`: o cliente precisa saber quando basta usar o refresh e quando é caso de refazer o login. Não há vazamento nisso — o `exp` está no payload, que o portador pode ler.

## Recurso de outro cliente, ou de outra loja

Devolve **404**, não 403. Um 403 confirma que o pedido — ou a loja — existe; 404 não revela nada.

Isso vale inclusive para o papel de loja: quem não é membro recebe o mesmo 404 de quem pediu uma loja inexistente. A diferença entre "não existe" e "existe mas não é sua" não é informação que o requisitante tenha direito de obter.
