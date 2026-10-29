FROM node:22-bookworm-slim AS frontend

WORKDIR /var/www/html

COPY package.json ./
RUN npm install

COPY . .
RUN npm run build

FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libpq-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        gd \
        intl \
        mbstring \
        opcache \
        pdo_pgsql \
        zip \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite headers

WORKDIR /var/www/html

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-interaction \
        --prefer-dist \
        --no-scripts
COPY . .
RUN composer dump-autoload --optimize \
    && php artisan package:discover --ansi \
    && rm -rf public/build \
    && mkdir -p \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && php artisan storage:link \
    && chown -R www-data:www-data storage bootstrap/cache

COPY --from=frontend /var/www/html/public/build /var/www/html/public/build
COPY docker/apache-vhost.conf /etc/apache2/sites-available/uist.conf
COPY docker/start-container.sh /usr/local/bin/start-container

RUN a2dissite 000-default \
    && a2ensite uist \
    && chmod +x /usr/local/bin/start-container

ENV PORT=10000
EXPOSE 10000

CMD ["/usr/local/bin/start-container"]
