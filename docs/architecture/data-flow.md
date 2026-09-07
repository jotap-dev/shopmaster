# O caminho de uma compra

Documento vivo do fluxo que justifica o desenho todo. Atualizado conforme as fases entregam.

O caso que importa é o **carrinho com itens de duas lojas** — é ele que obriga o pedido a se dividir.

## Checkout, passo a passo

```
POST /v1/checkout  (Bearer + Idempotency-Key)
        │
        ▼
CheckoutController                              ← traduz HTTP ↔ domínio
        │
        ▼
Ordering\Application\PlaceOrder::handle()
        │
        ├─1─► Cart\GetCart                       carrinho, AGRUPADO POR LOJA
        │
        │     para cada loja do carrinho:
        ├─2─► Store\GetStoreForCheckout          está `active`? qual o % de comissão?
        ├─3─► Pricing\QuoteCart                  total, descontos, cupom
        ├─4─► Inventory\ReserveStock             reserva com TTL (Redis)
        ├─5─► Shipping\QuoteShipping             frete DAQUELA loja
        │
        ├─6─► OrderRepository::save()            1 Order + N StoreOrder,
        │                                        COM SNAPSHOT em cada linha
        │     └── passos 4 a 6 numa transação do Postgres
        │
        └─7─► EventBus::dispatch(new OrderPlaced)   DEPOIS do commit
```

Se qualquer passo falhar, as reservas já feitas são **liberadas** e a exceção de negócio propaga. O Controller mapeia:

| Exceção | Status | Código |
|---|---|---|
| `CartIsEmpty` | 422 | `cart_is_empty` |
| `OutOfStock` | 409 | `out_of_stock` |
| `StoreNotActive` | 409 | `store_not_active` |
| `CannotBuyFromOwnStore` | 422 | `cannot_buy_from_own_store` |
| `CouponExpired` | 422 | `coupon_expired` |
| `AddressNotDeliverable` | 422 | `address_not_deliverable` |

## A forma do pedido

```
Order  #A7F3                       ← o que o cliente vê e paga. UM pagamento.
├── StoreOrder  Loja Norte         ← unidade de fulfillment
│   ├── OrderLine  camiseta P      preço, desconto e COMISSÃO congelados
│   └── OrderLine  camiseta M
│   └── frete e prazo da Loja Norte, congelados
│
└── StoreOrder  Loja Sul
    └── OrderLine  caneca
    └── frete e prazo da Loja Sul, congelados
```

**O estado do `Order` é derivado** dos `StoreOrder`, nunca guardado em paralelo. A Loja Norte pode despachar hoje e a Loja Sul só na semana que vem — um estado único mentiria a partir do primeiro despacho. Dois campos que precisam concordar acabam discordando.

## Por que o snapshot

Cada `OrderLine` guarda título, SKU, preço unitário, desconto **e o percentual de comissão** copiados no momento da compra. O pedido nunca mais consulta `Catalog`, `Pricing` nem `Store`.

Sem isso, um produto renomeado ou uma comissão renegociada reescreveriam pedidos antigos — e o extrato de janeiro passaria a mostrar o percentual de março.

## Por que o evento sai depois do commit

O passo 7 fica **fora** da transação. Evento disparado dentro de uma transação que faz rollback é e-mail de confirmação na caixa do cliente para um pedido que não existe.

## Por que Idempotency-Key

`POST /v1/checkout` é a única rota onde um duplo clique custa dinheiro. A chave do header é guardada no Redis com o id do pedido criado; a segunda chamada dentro da janela devolve o **mesmo** pedido, não um segundo.

## O pagamento, e a volta

O cliente paga **uma vez**, pelo pedido inteiro, mesmo com várias lojas. O rateio é interno — split payment no gateway está fora do v1.

```
POST /v1/orders/{id}/payment  ──►  Payment\StartPayment  ──►  PaymentGateway (ACL)
                                                                    │
POST /v1/webhooks/payments/{provider}  ◄────────────────────────────┘
        │  (assinatura HMAC verificada antes de qualquer processamento)
        ▼
Payment\HandleProviderWebhook
        │
        └─► EventBus::dispatch(new PaymentApproved)
                    │
                    ├─► Ordering      TODOS os StoreOrder → `paid`
                    │                 e um StoreOrderPaid por loja
                    ├─► Inventory     CommitReservation (baixa definitiva)
                    └─► Notification  recibo ao cliente + aviso a cada loja (fila)
```

`Payment` não conhece `Ordering`. `Ordering` não conhece `Notification`. Os três se falam por evento — e é por isso que dá para trocar o gateway, ou avisar a loja por outro canal, sem abrir nenhum dos outros dois.

## As duas camadas de autorização, no caminho da requisição

```
GET /v1/stores/{storeId}/orders
        │
        ├─► middleware auth.token       token válido? injeta usuário
        │                               e os papéis de PLATAFORMA
        │
        ├─► middleware store.role:admin lê {storeId} da rota
        │        │
        │        └─► Store\ResolveStoreRole(usuário, loja)
        │                 ├── cache Redis (TTL curto)
        │                 └── miss → store_members
        │
        │            não é membro, ou papel insuficiente → 404
        │
        └─► StoreOrdersController
```

O papel de loja **não** viaja no token. O TTL do cache é, literalmente, o atraso máximo entre remover alguém da loja e ele perder o acesso — por isso é curto e configurável (`shopmaster.store.role_cache_ttl`).

Não-membro recebe **404**, não 403: um 403 confirmaria que aquela loja existe.
