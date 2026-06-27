FROM php:8.2-fpm-alpine

RUN apk add --no-cache \
    curl \
    nginx \
    libpng-dev \
    libxml2-dev \
    postgresql-dev \
    oniguruma-dev \
    zip \
    unzip \
    libzip-dev \
    nodejs \
    npm \
    shadow

RUN docker-php-ext-install pdo pdo_pgsql mbstring exif pcntl bcmath gd zip opcache

RUN apk add --no-cache $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini
COPY docker/app/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/app/start-web.sh /usr/local/bin/start-web
RUN chmod +x /usr/local/bin/start-web

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www/html/backend
COPY backend/composer.json backend/composer.lock* ./
RUN composer install --no-interaction --prefer-dist --no-scripts --no-autoloader --no-dev

COPY backend ./
RUN mkdir -p storage/framework/sessions \
    storage/framework/views \
    storage/framework/cache \
    storage/logs \
    bootstrap/cache \
    && rm -f bootstrap/cache/*.php \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && composer dump-autoload --optimize --no-dev

COPY maker-runtime/package.json maker-runtime/package-lock.json /opt/ontolosql-maker/
RUN cd /opt/ontolosql-maker && npm ci --omit=dev
COPY maker-runtime/import-maker.mjs /opt/ontolosql-maker/import-maker.mjs

COPY foundry-runtime/package.json foundry-runtime/package-lock.json /opt/ontolosql-foundry/
RUN cd /opt/ontolosql-foundry && npm ci --omit=dev
COPY foundry-runtime/foundry.mjs foundry-runtime/sdk.mjs /opt/ontolosql-foundry/

WORKDIR /var/www/html/frontend
COPY frontend/package*.json ./
RUN npm ci
COPY frontend ./

ARG VITE_REVERB_APP_KEY=snydiagram-key
ARG VITE_REVERB_HOST=ontolosql-designer-kripb.ondigitalocean.app
ARG VITE_REVERB_PORT=443
ARG VITE_REVERB_SCHEME=https
RUN printf "VITE_REVERB_APP_KEY=%s\nVITE_REVERB_HOST=%s\nVITE_REVERB_PORT=%s\nVITE_REVERB_SCHEME=%s\n" \
    "$VITE_REVERB_APP_KEY" "$VITE_REVERB_HOST" "$VITE_REVERB_PORT" "$VITE_REVERB_SCHEME" > .env \
    && npm run build \
    && rm -rf /var/www/html/backend/public/build \
    && cp -R public/build /var/www/html/backend/public/build

WORKDIR /var/www/html/backend

CMD ["start-web"]
