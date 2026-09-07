# PHP 8.5 para bater com o runtime local (composer.json exige ^8.3).
FROM php:8.5-cli

# git/unzip: usados pelo Composer. libzip/libicu: deps das extensões zip/intl.
# libpq-dev: header do cliente Postgres, exigido por pdo_pgsql.
#
# Só o que a imagem base NÃO traz: ela já inclui mbstring, ctype, tokenizer,
# dom, xml, curl, openssl, session, fileinfo e OPcache. Instalar opcache aqui
# quebra o build ("cannot stat 'modules/*'"), porque já vem compilado estático.
# PDO vem só com pdo_sqlite — pdo_pgsql é o que de fato falta.
#
# Redis é falado via predis (PHP puro), então NÃO é preciso compilar a
# extensão phpredis: ver REDIS_CLIENT no .env.example.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
        libicu-dev \
        libpq-dev \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        zip \
        intl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Camada de dependências separada do código: mexer numa classe não invalida
# o cache do `composer install`.
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts --no-autoloader

COPY . /app

RUN composer dump-autoload --optimize

EXPOSE 8000

# `php -S` direto, não `artisan serve`: o servidor embutido do artisan
# encerra o processo em erro fatal, e o container morreria a cada 500.
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public", "docker/router.php"]
