# ShopMaster — Requisitos

> **Documento de trabalho, fora do versionamento** (`.gitignore`). É a fonte de verdade do *o quê*; o *como* está em [plano-de-desenvolvimento.md](plano-de-desenvolvimento.md) e em [architecture/architecture.md](architecture/architecture.md).

## 1. Visão do produto

**Marketplace multi-lojista.** Não é uma loja própria: a plataforma hospeda lojas de terceiros, no modelo Mercado Livre / Amazon Marketplace.

Um mesmo usuário acumula papéis sem trocar de conta: compra de manhã, e à tarde gerencia as duas lojas que possui e o financeiro de uma terceira onde foi convidado.

Três verbos definem o v1: **comprar**, **vender**, **administrar a plataforma**.

## 2. Atores e papéis

A autorização tem **duas camadas independentes**. Confundir as duas é o erro estrutural mais caro deste projeto.

### 2.1 Papéis de plataforma — globais, no token

Um usuário pode acumular mais de um.

| Papel | Ganha quando | Pode |
|---|---|---|
| `buyer` | No cadastro. Todo usuário nasce assim. | Comprar, carrinho, favoritos, seus pedidos, avaliar o que comprou |
| `seller` | Ao criar a primeira loja | Tudo de `buyer` + criar e possuir lojas |
| `platform_admin` | Concedido por outro `platform_admin` | Aprovar/suspender loja, ver qualquer pedido, gerir cupons da plataforma, conceder papéis |

Extensível: novos papéis de plataforma entram como valores novos do enum, sem mudar o desenho.

### 2.2 Papéis de loja — por loja, **fora** do token

Um usuário tem um papel **por loja em que é membro**. É `owner` da Loja A, `finance` da Loja B, e nada na Loja C.

| Papel | Pode | Não pode |
|---|---|---|
| `owner` | Tudo da loja: produtos, estoque, pedidos, membros, dados bancários, encerrar a loja | — |
| `admin` | Produtos, estoque, preços, pedidos, despacho | Gerir membros, ver faturamento, encerrar a loja |
| `finance` | Faturamento, comissões, extrato, dados de repasse | Tocar em produto, estoque ou pedido |

**Por que o papel de loja não vai no token:** o número de lojas por usuário é ilimitado. Um token que carregasse todas cresceria sem limite e ficaria obsoleto no instante em que alguém fosse removido de uma loja. O papel é resolvido **por requisição**, a partir do `store_id` da rota, com cache curto no Redis.

---

## 3. Escopo do v1

O critério de corte: **o ciclo completo comprar → vender → receber precisa fechar**. Tudo que não é necessário para esse ciclo fechar fica de fora.

### 3.1 Dentro

Conta e papéis · criar e gerir loja · membros da loja · produto com SKU global · estoque · catálogo público com busca · vitrine da loja · carrinho · favoritos · checkout com múltiplas lojas · pagamento (gateway simulado) · frete por tabela · pedido e seus estados · painel do vendedor · avaliação de produto · comissão registrada · e-mail transacional.

### 3.2 Fora — e o motivo

| Fora do v1 | Por quê |
|---|---|
| **Repasse (payout) real ao vendedor** | A comissão é **calculada e registrada** por linha; o dinheiro sair da plataforma para a conta do lojista exige conta de pagamento, KYC e conciliação — um projeto inteiro. O extrato existe; a transferência, não. |
| **Gateway de pagamento real e split payment** | O adapter `FakePaymentGateway` fecha o ciclo. Trocar por um real é um segundo adapter, sem tocar no domínio — é justamente o que o ACL garante. |
| **Buy box / vários vendedores no mesmo anúncio** | Muda o modelo de catálogo inteiro. Ver decisão em aberto §8.1. |
| **Chat comprador–vendedor e perguntas no produto** | Produto próprio, com moderação e notificação. Não bloqueia a compra. |
| **Devolução e reembolso automatizados** | No v1, estorno é ação manual do `platform_admin`, registrada. |
| **Cupom da loja** | Só cupom da plataforma. Cupom por loja interage com comissão e vira cálculo de quem-paga-o-desconto. |
| **Upload de imagem** | Produto recebe **URL externa** no cadastro. Sem object storage. |
| **Múltiplas moedas** | BRL apenas. |
| **Frete real de transportadora** | Tabela de faixa por CEP e peso, configurada por loja. |
| **Notificação push, recomendação personalizada, ranking** | Nada disso bloqueia o ciclo. |

---

## 4. Requisitos funcionais

### 4.1 Identidade e conta

| # | Requisito |
|---|---|
| RF-001 | O visitante se cadastra com nome, e-mail e senha, e nasce com o papel `buyer`. |
| RF-002 | O e-mail é único na plataforma, comparado sem diferenciar maiúsculas (`citext`). |
| RF-003 | O usuário autentica por e-mail e senha e recebe um par access + refresh token. |
| RF-004 | O refresh é de **uso único**: ao ser trocado, é revogado. |
| RF-005 | O usuário edita nome, telefone e documento; **não** troca o e-mail no v1. |
| RF-006 | O usuário mantém uma agenda de endereços, com um marcado como padrão. |
| RF-007 | Um `platform_admin` concede e revoga papéis de plataforma de outro usuário. |
| RF-008 | Login errado devolve sempre o mesmo erro, seja o e-mail inexistente ou a senha errada. |

### 4.2 Loja

| # | Requisito |
|---|---|
| RF-010 | Um `buyer` cria uma loja informando nome, slug, descrição e documento (CNPJ ou CPF). Ao criar a primeira, ganha o papel `seller`. |
| RF-011 | Um usuário possui **múltiplas lojas**. O limite por usuário é configurável (`shopmaster.store.max_per_user`). |
| RF-012 | O slug da loja é único na plataforma e imutável depois de criado. |
| RF-013 | A loja nasce em `pending_approval` e só vende depois que um `platform_admin` a aprova (`active`). |
| RF-014 | Um `platform_admin` suspende uma loja (`suspended`): os produtos somem do catálogo, mas os pedidos em andamento seguem seu curso. |
| RF-015 | O `owner` convida um usuário existente para a loja, atribuindo `admin` ou `finance`. |
| RF-016 | O `owner` altera o papel de um membro e o remove da loja. |
| RF-017 | A loja tem exatamente **um** `owner`. Transferir a posse é uma ação explícita, e o antigo dono vira `admin`. |
| RF-018 | O `owner` não pode se remover nem rebaixar a si mesmo — teria loja sem dono. |
| RF-019 | A loja tem uma vitrine pública em `GET /v1/stores/{slug}`, com seus dados e produtos ativos. |
| RF-020 | Cada loja tem um percentual de comissão da plataforma; sem valor próprio, vale o padrão global. |

### 4.3 Catálogo

| # | Requisito |
|---|---|
| RF-030 | Um membro `owner`/`admin` cadastra produto na **sua** loja: título, descrição, categoria, imagens (URL) e ao menos uma variação. |
| RF-031 | Toda variação tem um **SKU global**: único em toda a plataforma, não apenas dentro da loja. Ver §7.1. |
| RF-032 | A variação carrega os atributos que a distinguem (cor, tamanho…), em campo flexível. |
| RF-033 | O produto opcionalmente carrega um GTIN/EAN — hoje só informativo. |
| RF-034 | O produto tem estados `draft`, `published` e `archived`. Só `published`, em loja `active`, aparece no catálogo público. |
| RF-035 | Publicar exige ao menos uma variação com preço e estoque definidos. |
| RF-036 | Produto não publicado devolve **404** na rota pública — o mesmo de um inexistente. |
| RF-037 | O catálogo público é buscável por texto, e filtrável por categoria, faixa de preço e **loja**. |
| RF-038 | O catálogo é paginado por cursor, com tamanho de página limitado. |
| RF-039 | Categorias são gerenciadas pelo `platform_admin` e compartilhadas por todas as lojas. |
| RF-040 | O detalhe do produto compõe catálogo, preço vigente, disponibilidade, dados da loja e resumo de avaliações. |

### 4.4 Estoque

| # | Requisito |
|---|---|
| RF-050 | Cada SKU tem um saldo, derivado de um livro-razão de movimentos — não de um contador que se sobrescreve. |
| RF-051 | Todo movimento registra motivo: entrada, venda, cancelamento, devolução ou ajuste. |
| RF-052 | O checkout **reserva** o estoque, com validade limitada. |
| RF-053 | Reserva expirada devolve o item ao saldo, sem intervenção. |
| RF-054 | Pagamento aprovado converte a reserva em baixa definitiva. |
| RF-055 | Cancelamento devolve o saldo. |
| RF-056 | O saldo **nunca** fica negativo, nem sob compras simultâneas do último item. |
| RF-057 | O membro `owner`/`admin` ajusta o estoque da sua loja, com o ajuste registrado e atribuído a ele. |

### 4.5 Carrinho e favoritos

| # | Requisito |
|---|---|
| RF-060 | O visitante monta carrinho **sem estar logado**; ele expira por inatividade. |
| RF-061 | Ao entrar na conta, o carrinho de visitante funde com o do cliente. |
| RF-062 | O carrinho aceita itens de **lojas diferentes**, agrupados por loja na exibição. |
| RF-063 | O carrinho é recalculado a cada leitura: preço atual, disponibilidade e itens que saíram do ar. |
| RF-064 | Item cuja loja foi suspensa, ou cujo produto foi despublicado, é sinalizado como indisponível — não some em silêncio. |
| RF-065 | O carrinho tem teto de itens distintos e de quantidade por item. |
| RF-066 | O cliente favorita e desfavorita produtos, e lista seus favoritos. |
| RF-067 | O favorito mostra se o produto ainda está disponível e por qual preço. |

### 4.6 Checkout e pedido

| # | Requisito |
|---|---|
| RF-070 | O cliente finaliza a compra escolhendo endereço de entrega e forma de pagamento. |
| RF-071 | Um carrinho com itens de N lojas gera **um pedido** e **N sub-pedidos**, um por loja. Ver §7.3. |
| RF-072 | O frete é cotado **por loja**: cada sub-pedido tem o seu, porque cada loja despacha de um lugar. |
| RF-073 | O pedido **congela** um snapshot de cada linha: título, SKU, preço unitário, desconto e comissão no momento da compra. |
| RF-074 | O checkout é idempotente por `Idempotency-Key`: duplo clique não gera dois pedidos. |
| RF-075 | Se qualquer etapa falhar, as reservas já feitas são liberadas e nada é cobrado. |
| RF-076 | O cliente lista e detalha **seus** pedidos. Pedido de outro cliente devolve 404. |
| RF-077 | O cliente cancela um pedido enquanto nenhum sub-pedido tiver sido despachado, informando o motivo. |
| RF-078 | Cada **sub-pedido** tem seu próprio ciclo: `pending_payment` → `paid` → `shipped` → `delivered`, com `cancelled` a partir dos dois primeiros. |
| RF-079 | O estado do pedido é derivado dos sub-pedidos — não é mantido em paralelo. |
| RF-080 | Toda transição de estado é registrada com autor, momento e motivo. |

### 4.7 Pagamento

| # | Requisito |
|---|---|
| RF-090 | O pagamento é **um só** para o pedido inteiro, mesmo com várias lojas. O cliente paga uma vez. |
| RF-091 | Pagamento aprovado promove todos os sub-pedidos a `paid` e baixa o estoque. |
| RF-092 | Pagamento recusado mantém o pedido pendente e libera as reservas ao expirar. |
| RF-093 | O webhook do provedor tem a assinatura verificada antes de qualquer processamento. |
| RF-094 | Entrega repetida do mesmo evento do provedor não é processada duas vezes. |
| RF-095 | Estorno é ação de `platform_admin`, registrada com motivo. |

### 4.8 Comissão e financeiro

| # | Requisito |
|---|---|
| RF-100 | A comissão da plataforma é calculada e **congelada por linha** no momento da compra. |
| RF-101 | Cada sub-pedido expõe bruto, comissão e líquido do lojista. |
| RF-102 | `owner` e `finance` veem o extrato da loja: pedidos pagos, comissão retida, líquido acumulado. |
| RF-103 | Cancelamento e estorno geram lançamento de reversão — o extrato nunca é reescrito. |

### 4.9 Painel do vendedor

| # | Requisito |
|---|---|
| RF-110 | `owner`/`admin` listam os pedidos da **sua** loja, filtrados por estado. |
| RF-111 | `owner`/`admin` marcam um sub-pedido como despachado, informando o código de rastreio. |
| RF-112 | Um membro só enxerga dados da loja de que participa. Loja alheia devolve 404. |
| RF-113 | Ações administrativas de loja (preço, estoque, despacho, membros) são auditadas com autor e momento. |

### 4.10 Frete

| # | Requisito |
|---|---|
| RF-120 | Cada loja configura sua tabela de frete por faixa de CEP e peso. |
| RF-121 | O checkout cota o frete de cada loja e congela valor e prazo no sub-pedido. |
| RF-122 | Endereço fora de qualquer faixa impede a compra daquela loja, com erro explícito. |

### 4.11 Avaliação

| # | Requisito |
|---|---|
| RF-130 | Só avalia o produto quem tem sub-pedido `delivered` contendo aquele produto. |
| RF-131 | Uma avaliação por cliente, por produto. |
| RF-132 | A avaliação tem nota de 1 a 5 e comentário opcional. |
| RF-133 | O produto exibe média e total de avaliações. |
| RF-134 | O `platform_admin` remove avaliação abusiva, com registro. |

### 4.12 Notificação

| # | Requisito |
|---|---|
| RF-140 | O cliente recebe e-mail na confirmação do pedido, na aprovação do pagamento e no despacho. |
| RF-141 | A loja recebe e-mail quando um pedido novo é pago. |
| RF-142 | O envio é assíncrono e **nunca** bloqueia a resposta do checkout. |
| RF-143 | Falha de envio não desfaz o pedido: é registrada e retentada. |

---

## 5. Requisitos não funcionais

### 5.1 Arquitetura e manutenibilidade

| # | Requisito |
|---|---|
| RNF-001 | `Domain/` e `Application/` não dependem de Laravel, Eloquent ou HTTP. Verificado por teste automatizado. |
| RNF-002 | Um bounded context não importa `Domain/` nem `Infrastructure/` de outro. Verificado por teste. |
| RNF-003 | Todo bind porta → adapter é centralizado em `AppServiceProvider`. |
| RNF-004 | Regra de negócio não mora em Controller, Resource nem migration. |
| RNF-005 | Trocar o gateway de pagamento é escrever um adapter novo, sem tocar em `Domain/` ou `Application/`. |

### 5.2 Qualidade e processo

| # | Requisito |
|---|---|
| RNF-010 | Desenvolvimento test-first: o teste nasce vermelho antes da implementação. |
| RNF-011 | Todo use case tem teste unitário cobrindo o caminho feliz e **cada** exceção de negócio. |
| RNF-012 | Toda rota tem teste de integração incluindo o caso sem token e o caso com token de outro usuário. |
| RNF-013 | A suíte roda sem serviço externo real: gateway e transportadora são substituídos por fake. |
| RNF-014 | O código passa no Laravel Pint sem alteração pendente. |
| RNF-015 | O contrato OpenAPI passa no lint sem erro, e é atualizado na mesma entrega da rota. |

### 5.3 Segurança

| # | Requisito |
|---|---|
| RNF-020 | Senha armazenada com hash adaptativo (bcrypt/argon2id). O hash nunca sai do repositório — nem em Value Object, nem em resposta, nem em log. |
| RNF-021 | Autenticação stateless por JWT. Access token de vida curta; refresh de uso único, revogável. |
| RNF-022 | Autorização em duas camadas: papel de plataforma no token, papel de loja resolvido por requisição. |
| RNF-023 | Toda consulta de dado pessoal ou de loja é escopada pelo requisitante. Recurso alheio devolve **404**, nunca 403. |
| RNF-024 | Repositórios usam allowlist explícita de colunas. Nunca `SELECT *`. |
| RNF-025 | Rate limit por IP nas rotas públicas e por usuário nas autenticadas. |
| RNF-026 | Webhook de terceiro só é processado com assinatura verificada. |
| RNF-027 | Dado de cartão **nunca** transita nem é armazenado pela API. Guardamos apenas o token do provedor. |
| RNF-028 | `APP_DEBUG=false` em servidor: stack trace e variáveis de ambiente não vazam na resposta de erro. |

### 5.4 Dados e consistência

| # | Requisito |
|---|---|
| RNF-030 | Valor monetário é `numeric(12,2)` no Postgres — **nunca** `float`, `real` nem o tipo `money`. Ver §7.2. |
| RNF-031 | Identificador exposto na API é UUID, não sequencial: não revela volume de vendas nem permite enumerar pedidos. |
| RNF-032 | O schema é propriedade desta API. Toda mudança estrutural nasce como migration, com `down()` que funciona. |
| RNF-033 | Estoque e criação de pedido são atômicos: ou tudo acontece, ou nada. |
| RNF-034 | Evento de domínio é publicado **depois** do commit — nunca dentro de transação que pode reverter. |
| RNF-035 | Livro-razão de estoque e extrato financeiro são append-only. Correção é lançamento novo, não edição. |
| RNF-036 | Snapshot do pedido é imutável: alteração posterior de preço, título ou comissão não reescreve compra fechada. |

### 5.5 Desempenho e escala

| # | Requisito |
|---|---|
| RNF-040 | Listagem de catálogo responde em menos de 300 ms no p95, com o cache quente. |
| RNF-041 | Checkout completo responde em menos de 2 s no p95. |
| RNF-042 | Toda listagem é paginada, com teto de tamanho de página. Nenhuma rota devolve coleção ilimitada. |
| RNF-043 | Catálogo público é cacheado em store dedicado, invalidado na publicação. |
| RNF-044 | Resolução de papel de loja é cacheada por requisição e no Redis, com TTL curto. |
| RNF-045 | Consulta quente tem índice conferido com `EXPLAIN ANALYZE`, não presumido. |

### 5.6 Disponibilidade e operação

| # | Requisito |
|---|---|
| RNF-050 | Indisponibilidade do e-mail **não** impede compra: o job fica na fila. |
| RNF-051 | Indisponibilidade do gateway impede o pagamento, mas não corrompe o pedido: ele fica pendente e as reservas expiram. |
| RNF-052 | Toda degradação silenciosa está documentada em `deploy.md` — se a falta de uma configuração só desliga uma funcionalidade, isso está escrito. |
| RNF-053 | Health check em `/up`, sem autenticação. |
| RNF-054 | Log estruturado com identificador de correlação por requisição. |
| RNF-055 | Erro de negócio nunca vira 500. Exceção tipada vira status e código estáveis. |

### 5.7 Contrato e compatibilidade

| # | Requisito |
|---|---|
| RNF-060 | API REST versionada em `/v1`. Mudança incompatível exige versão nova. |
| RNF-061 | Erro sempre no formato `{ "error": { "code", "message" } }`. O `code` é estável; a `message` não. |
| RNF-062 | O contrato OpenAPI 3.1 é a fonte única, servido em `/docs`. |
| RNF-063 | Rota que não está no contrato não existe. |

### 5.8 Portabilidade

| # | Requisito |
|---|---|
| RNF-070 | O projeto sobe em um comando via Docker, em macOS, Linux e WSL2. |
| RNF-071 | Nenhuma dependência de serviço proprietário de nuvem. |
| RNF-072 | Toda configuração de ambiente vem de variável, com `.env.example` completo. |

---

## 6. Regras de negócio transversais

| # | Regra |
|---|---|
| RN-01 | **Loja suspensa não vende.** Produtos somem do catálogo e do checkout; pedidos em andamento seguem o curso. |
| RN-02 | **Ninguém compra de si mesmo.** Membro de uma loja não finaliza compra contendo produto dela. |
| RN-03 | **Preço congelado manda.** O que vale é o snapshot do pedido, não o preço atual. |
| RN-04 | **Comissão é do momento da venda.** Mudar o percentual não altera pedido fechado. |
| RN-05 | **Sub-pedido é a unidade de fulfillment.** Despacho, frete e estado de entrega são por loja. |
| RN-06 | **Pagamento é do pedido inteiro.** O cliente paga uma vez; o rateio é interno. |
| RN-07 | **Só avalia quem recebeu.** Compra entregue é o único bilhete de entrada. |
| RN-08 | **Estoque nunca negativo**, mesmo sob concorrência. |
| RN-09 | **Recurso alheio devolve 404.** 403 confirmaria a existência. |
| RN-10 | **Toda ação administrativa tem autor.** Nada muda "sozinho". |

---

## 7. Decisões técnicas que estes requisitos impõem

### 7.1 SKU global

**Decidido:** o SKU é único em **toda a plataforma**, não por loja. Duas lojas que vendam o mesmo item têm SKUs diferentes — cada uma descreve o seu próprio anúncio.

Consequências:
- Constraint `UNIQUE` simples em `product_variants.sku`;
- `Inventory` indexa por SKU sem precisar saber de loja — o SKU já é globalmente não-ambíguo;
- o cliente pode ver o "mesmo" produto anunciado por várias lojas como **anúncios distintos**, não como uma página com vários vendedores.

O campo GTIN/EAN opcional (RF-033) existe para que, no dia em que se quiser agrupar anúncios do mesmo item, o dado já esteja lá. Ver §8.1.

### 7.2 Dinheiro em `numeric`

**Decidido:** `numeric(12,2)` nas colunas monetárias.

- **Não `float`/`double precision`.** Binário não representa `0.10` exatamente; a soma de um carrinho de dez linhas diverge do que o cliente viu.
- **Não o tipo `money` do Postgres.** Parece a escolha óbvia pelo nome, e é armadilha: a quantidade de casas decimais depende do `lc_monetary` do servidor, ele não carrega qual moeda é, e o valor muda de significado ao restaurar o banco em uma máquina de locale diferente. A própria comunidade desaconselha.
- **`numeric(12,2)` é exato**, aritmética decimal de verdade, e 12 dígitos comportam qualquer pedido realista.

Do lado do PHP, que não tem tipo decimal nativo: `Shared\Money` guarda **inteiro em centavos** internamente (exato, sem ponto flutuante) e converte na fronteira — lê e escreve `numeric` como string decimal, sem nunca passar por `float`. O VO é o único ponto de conversão da aplicação.

Na API, o valor trafega como **inteiro em centavos** (`{"amount": 19990, "currency": "BRL"}`): JSON não tem decimal, e um cliente JavaScript que receba `199.90` como número já perdeu a exatidão antes de qualquer cálculo.

### 7.3 Um pedido, N sub-pedidos

**Decidido:** carrinho com itens de N lojas gera um pedido e N sub-pedidos.

Não é preciosismo de modelagem — é o que o negócio exige: cada loja despacha de um lugar, cota o próprio frete, tem o próprio prazo e o próprio estado de entrega. Um pedido com estado único mentiria assim que uma loja despachasse antes da outra.

A alternativa — restringir o carrinho a uma loja por compra — foi descartada: é uma limitação visível ao cliente, e trocar depois significaria reescrever `Ordering` inteiro.

O estado do pedido é **derivado** dos sub-pedidos, nunca mantido em paralelo — dois campos de estado que precisam concordar acabam discordando.

### 7.4 Autorização em duas camadas

Papel de plataforma vai no token. Papel de loja **não**: é resolvido a cada requisição a partir do `store_id` da rota, com cache curto no Redis.

Isso mantém o token pequeno e limitado, e faz a remoção de um membro ter efeito imediato — não em até uma hora, quando o access token expirasse.

---

## 8. Decisões em aberto

### 8.1 Anúncio por loja vs. catálogo mestre com ofertas

O v1 adota **anúncio por loja** (§7.1): cada loja cria o próprio produto, com o próprio SKU.

O modelo alternativo — catálogo mestre onde várias lojas ofertam o mesmo item, com uma vencendo a "buy box" — é o que Amazon e Mercado Livre fazem no maduro. Ele muda `Catalog` de raiz: produto e oferta viram entidades separadas, aparece regra de eleição de vencedor, e o SKU deixa de identificar o item para identificar a oferta.

**Ficou de fora do v1 conscientemente.** O GTIN opcional é a ponte: com ele preenchido, agrupar anúncios do mesmo item depois é um trabalho de migração, não uma reescrita.

### 8.2 Retenção do valor até a entrega

Hoje o dinheiro é capturado no pagamento e a comissão registrada. Marketplaces reais retêm o valor até a entrega ser confirmada, como proteção ao comprador. Isso depende do repasse existir — e ele está fora do v1.

### 8.3 Verificação do lojista

RF-013 exige aprovação manual do `platform_admin`. Validar CNPJ em base oficial, exigir documento e fazer KYC é o passo seguinte, fora do v1.
