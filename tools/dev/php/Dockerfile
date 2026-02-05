ARG PHP_VERSION=8.5
FROM php:${PHP_VERSION}-cli-trixie

RUN apt-get update \
  && apt-get install -y --no-install-recommends \
    git \
    unzip \
    zip \
    libzip-dev \
    libpq-dev \
    curl \
  && docker-php-ext-install zip pdo_pgsql \
  && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
