FROM dunglas/frankenphp:php8.4-bookworm


RUN apt-get update \
    && apt-get install -y curl ca-certificates gnupg2 \
    && curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc | gpg --dearmor -o /usr/share/keyrings/postgresql-keyring.gpg \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && echo "deb [signed-by=/usr/share/keyrings/postgresql-keyring.gpg] https://apt.postgresql.org/pub/repos/apt jammy-pgdg main" > /etc/apt/sources.list.d/pgdg.list \
    && apt-get update \
    && apt-get upgrade -y \
    && apt-get install -y unzip libnss3-tools procps postgresql-client-17 nodejs \
    && rm -rf /var/lib/apt/lists/*

RUN install-php-extensions \
    intl \
    pcntl \
    pdo \
    pdo_pgsql \
    zip

RUN echo "upload_max_filesize = 25M\npost_max_size = 27M" \
    > /usr/local/etc/php/conf.d/uploads.ini

RUN mkdir -p /app/public/build

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Dependency layers kept ahead of COPY . so source edits don't bust the cache.
COPY composer.json composer.lock /app/
RUN composer install --no-scripts --no-autoloader --no-dev --prefer-dist

COPY package.json package-lock.json /app/
RUN npm ci --no-audit

# spatie/laravel-backup expects MAIL_FROM_ADDRESS at composer post-install time.
ENV MAIL_FROM_ADDRESS=hello@davidharting.com
# Baked into the JS bundle at Vite build time.
ENV VITE_APP_NAME=davidharting.com

COPY . /app

RUN composer dump-autoload --optimize \
    && composer run-script post-autoload-dump \
    && npm run build \
    && php artisan optimize:clear

CMD ["php", "artisan", "octane:frankenphp", "--caddyfile", "Caddyfile"]
