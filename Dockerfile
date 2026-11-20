FROM node:22-bookworm-slim AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN npm run build


FROM php:8.2-apache-bookworm

WORKDIR /var/www/html

ENV COMPOSER_ALLOW_SUPERUSER=1

# PHPSpreadsheet requires the GD extension to generate and process spreadsheets.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        git \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libzip-dev \
        unzip \
        zip \
    && update-ca-certificates \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd pdo_mysql zip

RUN a2dismod -f mpm_event mpm_worker 2>/dev/null || true

RUN a2enmod mpm_prefork rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer config --global cafile /etc/ssl/certs/ca-certificates.crt \
    && composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress --no-scripts

COPY . .
COPY --from=assets /app/public/build ./public/build

RUN chown -R www-data:www-data storage bootstrap/cache \
    && sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf

CMD ["/bin/sh", "railway/start.sh"]
