FROM dunglas/frankenphp:php8.3-bookworm


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


COPY docker-entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

RUN mkdir -p /app/public/build

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY . /app

RUN composer install --optimize-autoloader && php artisan optimize:clear

RUN npm ci --no-audit
RUN npm run build

ENTRYPOINT ["bash", "/entrypoint.sh"]
CMD ["php", "artisan", "octane:frankenphp", "--caddyfile", "Caddyfile", "--https", "--http-redirect"]
