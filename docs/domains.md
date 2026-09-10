# Bounded contexts

O mapa completo, com o diagrama e o padrão de cada relação, está em [plano-de-desenvolvimento.md](plano-de-desenvolvimento.md#4-context-map). Este arquivo é o **estado vivo**: o que já existe, o que falta, e onde ficou cada fronteira.

## Status

| Contexto | Tipo | Responsabilidade | Status | Fase |
|---|---|---|---|---|
| **Shared** | Kernel | `EventBus`, `Money`, `Clock`, `ErrorResource`, `Auth` | **Parcial** — `EventBus`, `Money` e `Clock` prontos; `Auth` vem com Identity | 0 |
| **Identity** | Genérico | Registro, login, refresh, minha conta, papéis **de plataforma** | **Parcial** — cadastro, login, refresh, `auth.token` e "minha conta" prontos; falta o middleware `role` (RF-007) | 1 |
| **Customer** | Suporte | Endereços salvos e favoritos | Esqueleto | 1 e 3 |
| **Store** | **Core** | Loja, membros, **papéis de loja**, aprovação, comissão | Esqueleto | 2 |
| **Catalog** | **Core** | Produto **de uma loja**, variação (SKU global), categoria, busca | Esqueleto | 3 |
| **Inventory** | **Core** | Saldo por SKU, reserva, baixa, devolução | Esqueleto | 4 |
| **Pricing** | **Core** | Preço vigente, promoção, cupom, cálculo do total | Esqueleto | 5 |
| **Cart** | Suporte | Carrinho de visitante e de cliente, itens de várias lojas | Esqueleto | 6 |
| **Shipping** | Suporte | Endereço, cotação de frete **por loja**, rastreio | Esqueleto | 7 |
| **Ordering** | **Core** | Pedido, **sub-pedido por loja**, checkout, comissão congelada | Esqueleto | 8 |
| **Payment** | Genérico | Intenção **única por pedido**, confirmação, estorno, webhook | Esqueleto | 9 |
| **Notification** | Genérico | E-mail transacional por evento de domínio | Esqueleto | 10 |
| **Review** | Suporte | Avaliação de quem recebeu | Esqueleto | 11 |
| **ExampleContext** | — | Esqueleto de referência para o próximo contexto | Placeholder | — |

"Esqueleto" = a árvore de pastas e o namespace PSR-4 existem; ainda não há código.

### O que já existe no Identity

| Camada | Classes |
|---|---|
| `Domain/` | `PersonName`, `EmailAddress`, `PlainPassword`, `HashedPassword`, `PlatformRole`, `NewUser`, `RegisteredUser` + as exceções de invariante |
| `Application/` | `RegisterUser`; portas `UserRepository` e `PasswordHasher`; exceção `EmailAlreadyRegistered` |
| `Infrastructure/` | `EloquentUserRepository`, `BcryptPasswordHasher` |
| `Interface/Http/` | `RegisterUserController`, `RegisterUserRequest`, `RegisteredUserResource` |

Tabelas: `users` (e-mail em `citext`, único) e `user_platform_roles`.

**A regra "todo mundo nasce comprador" mora em `NewUser::register()`** — não no Controller, não num valor padrão de coluna, não num seeder. O dia em que o cadastro puder nascer com outro papel, muda um arquivo só.

## As fronteiras, e por que ficaram onde ficaram

- **Store separado de Identity.** Identity responde "quem é você na plataforma"; Store responde "o que você pode nesta loja". São perguntas com ciclos de vida diferentes: a primeira cabe no token, a segunda não — o número de lojas por usuário é ilimitado. Misturá-las seria o erro estrutural mais caro do projeto.

- **Store separado de Catalog.** O produto pertence à loja, mas quem pode cadastrá-lo é assunto do Store. `Catalog` **chama** `ResolveStoreRole`; não lê a tabela de membros.

- **Catalog x Inventory.** Separados porque mudam por motivos diferentes: o catálogo muda quando o marketing edita um produto; o estoque muda a cada venda. O que os liga é o **SKU** — `Inventory` não sabe nome nem preço, só identificador e saldo.

- **Pricing fora do Catalog.** Preço tem ciclo de vida próprio (promoção com data, cupom, regra de desconto) e é o único lugar onde total é calculado. Deixá-lo dentro do Catalog faria `Cart` e `Ordering` recalcularem por conta própria, e é assim que dois totais divergem.

- **Cart x Ordering.** O carrinho é volátil e recalcula a cada leitura; o pedido é imutável e **congela** um snapshot — título, SKU, preço unitário, desconto e comissão viram colunas de `order_lines`. Mudança de preço, ou do percentual de comissão, não pode reescrever a história de uma compra.

- **Order x StoreOrder.** O pedido é o que o cliente vê e paga; o sub-pedido é a unidade de **fulfillment**, uma por loja. Cada loja despacha do próprio endereço, cota o próprio frete e tem o próprio estado de entrega. O estado do `Order` é **derivado** dos sub-pedidos, nunca mantido em paralelo — dois campos que precisam concordar acabam discordando.

- **Favoritos no Customer, não no Catalog.** É dado do cliente que referencia produto, não dado de produto. Mora onde vive o resto do que é dele.

- **Inventory ↔ Ordering é Partnership**, a única relação bidirecional: o checkout reserva, o pagamento aprovado baixa, o cancelamento devolve. Evoluem juntos porque a invariante "não vender o que não tem" é dos dois.

- **Payment é ACL.** O gateway fala a língua dele; a porta `PaymentGateway` fala a nossa. Trocar de provedor é escrever um segundo adapter, sem tocar no domínio.

- **Notification só reage a evento.** `Ordering` publica `OrderPaid` e não sabe que existe alguém escutando. Chamada direta faria o e-mail bloquear a resposta do checkout.

## Composição entre contextos

Quando uma resposta precisa de vários contextos, a composição acontece na **camada de interface** — o Controller chama os use cases e junta no Resource.

Exemplo real: o detalhe de produto (`GET /v1/products/{slug}`) compõe **cinco** contextos — `Catalog` (o produto), `Pricing` (preço vigente), `Inventory` (disponibilidade), `Store` (nome e reputação da loja que anuncia) e `Review` (média das notas). Nenhum dos cinco importa o outro; quem os junta é o Controller.

## Autorização, e a quem ela pertence

`Store` é o dono da segunda camada de autorização. Nenhum outro contexto consulta `store_members` diretamente: todos chamam `Store\Application\ResolveStoreRole`, ou passam pelo middleware `store.role`, que é só um invólucro HTTP dele.

Isso é o que permite mudar a regra de papéis (adicionar um papel novo, mudar o que `finance` enxerga) em um lugar só.
