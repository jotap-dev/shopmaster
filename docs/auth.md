# Autenticação

> **Status: parcial.** O **cadastro** (RF-001/RF-002) está implementado e coberto por testes — ver a seção "Cadastro" abaixo. Login, refresh e o middleware `auth.token` ainda são decisão de desenho, escrita antes do código; atualize com o comportamento real quando forem implementados.

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

## Cadastro — implementado

`POST /v1/auth/register`, público, limitado a 10 requisições por minuto por IP.

O usuário nasce com o papel `buyer`, e essa regra mora em `Identity\Domain\NewUser::register()`.

**Unicidade do e-mail, em duas camadas.** O VO `EmailAddress` normaliza para minúsculas na escrita; a coluna é `citext`, então o Postgres compara sem diferenciar caixa mesmo por caminhos que esqueçam de normalizar. O `existsByEmail()` do use case é cortesia — dá mensagem clara e evita gastar os ~100ms do bcrypt à toa. Quem **garante** é a constraint `UNIQUE`, e o adapter traduz a violação de volta para `EmailAlreadyRegistered`. Sem isso, dois cadastros simultâneos do mesmo e-mail passariam os dois pela verificação.

**Revelar que o e-mail já existe é deliberado no cadastro**, por exigência do RF-002 — a pessoa precisa saber que já tem conta. É o oposto do login, onde distinguir "e-mail não existe" de "senha errada" entregaria uma lista de e-mails cadastrados.

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

## Recurso de outro cliente, ou de outra loja

Devolve **404**, não 403. Um 403 confirma que o pedido — ou a loja — existe; 404 não revela nada.

Isso vale inclusive para o papel de loja: quem não é membro recebe o mesmo 404 de quem pediu uma loja inexistente. A diferença entre "não existe" e "existe mas não é sua" não é informação que o requisitante tenha direito de obter.
