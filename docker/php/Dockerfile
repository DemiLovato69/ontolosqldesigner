FROM php:8.2-fpm-alpine

RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libxml2-dev \
    libpq-dev \
    oniguruma-dev \
    zip \
    unzip \
    libzip-dev \
    nodejs \
    npm \
    shadow

RUN docker-php-ext-install pdo pdo_pgsql mbstring exif pcntl bcmath gd zip

RUN apk add --no-cache $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS


COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

ARG USER_ID=1000
ARG GROUP_ID=1000

RUN addgroup -g ${GROUP_ID} -S www && \
    adduser -u ${USER_ID} -S www -G www

WORKDIR /var/www/html/backend

RUN mkdir -p storage/framework/{sessions,views,cache} storage/logs bootstrap/cache && \
    chmod -R 777 storage bootstrap/cache

COPY backend/composer.json backend/composer.lock* ./
RUN composer install --no-interaction --prefer-dist --no-scripts --no-autoloader --no-dev

COPY maker-runtime/package.json maker-runtime/package-lock.json /opt/ontolosql-maker/
RUN cd /opt/ontolosql-maker && npm ci --omit=dev
COPY maker-runtime/import-maker.mjs /opt/ontolosql-maker/import-maker.mjs

COPY docker/php/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]
