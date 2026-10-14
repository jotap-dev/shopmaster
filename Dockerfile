# syntax=docker/dockerfile:1

# =============================================================================
# A topologia: DOIS containers por aplicação
# =============================================================================
#
#   nginx  ── fala HTTP, não executa PHP
#     │  FastCGI na porta 9000
#   php-fpm ── executa PHP, não fala HTTP
#
# Não são um dentro do outro: são irmãos. Cada container é um processo
# principal mais um sistema de arquivos — o de nginx não tem PHP instalado, o
# de PHP não tem nginx.
#
# Este arquivo produz três imagens, por alvos diferentes:
#
#   docker build --target dev        -t shopmaster:dev    .
#   docker build --target production -t shopmaster:php    .
#   docker build --target nginx      -t shopmaster:nginx  .

# =============================================================================
# base — o PHP comum ao desenvolvimento e à produção
# =============================================================================
#
# `-fpm`: a variante que roda o FastCGI Process Manager, em vez da CLI.
# `-alpine`: base Alpine Linux (~5 MB contra ~75 MB do Debian), o que derruba
#   a imagem final em centenas de megabytes.
# Versão fixada: `latest` faria o build de hoje e o de amanhã produzirem
#   imagens diferentes sem nenhuma mudança no código.
FROM php:8.4-fpm-alpine AS base

# Extensões compiladas à mão, sem script auxiliar — assim fica visível de que
# biblioteca de sistema cada uma depende:
#
#   pdo_pgsql, pgsql  ->  postgresql-dev (compilar), libpq (rodar)
#   zip               ->  libzip-dev     (compilar), libzip (rodar)
#   intl              ->  icu-dev        (compilar), icu-libs (rodar)
#   opcache           ->  nenhuma, vem no código-fonte do PHP
#
# `$PHPIZE_DEPS` é uma variável da imagem oficial com o compilador e o
# autoconf. Ela entra no grupo `.build-deps` e é REMOVIDA no fim: sem isso a
# imagem final carregaria um compilador C inteiro, que é peso e superfície de
# ataque sem utilidade nenhuma em produção.
#
# Redis é falado por `predis` (PHP puro), então NÃO compilamos `phpredis`.
RUN apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        postgresql-dev \
        libzip-dev \
        icu-dev \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        zip \
        intl \
        opcache \
    && apk add --no-cache libpq libzip icu-libs curl \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# O pool do php-fpm: quantos processos, e o reciclo contra vazamento de
# memória. Prefixo `zz-` para ser lido depois do www.conf da imagem.
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-www.conf

ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /app

# =============================================================================
# dev — o que o `docker compose up` usa
# =============================================================================
#
# ATENÇÃO: este alvo fala FastCGI na 9000, não HTTP. O `compose.yaml` local
# precisa de um serviço nginx na frente — sozinho ele não responde no navegador.
FROM base AS dev

# Camada de dependências separada do código: mexer numa classe não invalida o
# cache do `composer install`.
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts --no-autoloader

COPY . /app

RUN composer dump-autoload

EXPOSE 9000

# =============================================================================
# production — a imagem do php-fpm que vai para o GHCR
# =============================================================================
FROM base AS production

# `--no-dev` tira PHPUnit, Pint, Faker e Collision: menos peso e menos
# superfície. `--classmap-authoritative` resolve classe sem consultar o
# sistema de arquivos, o que vale quando o autoload é imutável.
COPY composer.json composer.lock ./
RUN composer install \
        --no-interaction \
        --prefer-dist \
        --no-scripts \
        --no-autoloader \
        --no-dev

COPY . /app

RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

# OPcache guarda o PHP já compilado em memória compartilhada: da segunda
# requisição em diante, pula ler o arquivo, analisar a sintaxe e compilar.
#
# `validate_timestamps=0` dispensa conferir a data de cada arquivo a cada
# requisição. Só é seguro PORQUE um deploy troca a imagem inteira — num
# servidor onde o código muda no lugar, isto serviria código velho até o
# reinício, e ninguém entenderia por quê.
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.max_accelerated_files=20000'; \
        echo 'opcache.interned_strings_buffer=16'; \
    } > "$PHP_INI_DIR/conf.d/10-opcache.ini"

# Desliga display_errors e aperta o que a imagem base deixa em modo de
# desenvolvimento.
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Diretórios que o Laravel escreve em execução. O php-fpm roda como www-data,
# então sem isto a primeira requisição falha ao gravar log ou compilar view.
RUN chown -R www-data:www-data storage bootstrap/cache

# NÃO rodamos `config:cache` aqui: ele gravaria os valores do build, e as
# variáveis reais só existem quando o container sobe. Quem faz isso é o deploy.

EXPOSE 9000

# `php-fpm -t` valida a configuração do pool. É o que dá para checar sem HTTP
# — quem testa a pilha inteira é o healthcheck do nginx.
HEALTHCHECK --interval=30s --timeout=5s --start-period=15s --retries=3 \
    CMD php-fpm -t || exit 1

# =============================================================================
# nginx — a segunda imagem, com a configuração versionada dentro
# =============================================================================
#
# Ela NÃO carrega o código da aplicação, e isso é deliberado: numa API todo
# caminho cai no index.php, e quem abre esse arquivo é o php-fpm. Aqui vão só
# os estáticos que o nginx serve sem acordar o PHP.
#
# Se algum dia a app servir assets de verdade, eles precisam vir para cá também
# — senão o nginx devolve 404 e a requisição nem chega ao PHP.
FROM nginx:1.27-alpine AS nginx

COPY docker/nginx/api.conf /etc/nginx/conf.d/default.conf

# `public/` inteiro, não só favicon e robots: assim qualquer estático que a
# aplicação venha a ter funciona igual em desenvolvimento e em produção. O
# index.php vem junto e é inofensivo — o `location = /index.php` o manda para
# o FastCGI antes de o nginx cogitar servir o arquivo.
COPY public/ /app/public/

RUN apk add --no-cache curl

EXPOSE 80

# Bate na rota de saúde atravessando a pilha inteira: nginx, FastCGI, php-fpm
# e Laravel. Um healthcheck que só verificasse o nginx ficaria verde com o PHP
# morto.
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD curl -fsS http://127.0.0.1/up || exit 1
