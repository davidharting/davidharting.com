FROM node:22 AS frontend_builder

COPY . /app
WORKDIR /app

RUN npm ci --no-audit
RUN npm run build




FROM dunglas/frankenphp:php8.2-bookworm


RUN sh -c 'echo "deb https://apt.postgresql.org/pub/repos/apt bookworm-pgdg main" > /etc/apt/sources.list.d/pgdg.list' \
    && apt-get update \
    && apt-get install -y curl ca-certificates gnupg \
    && curl https://www.postgresql.org/media/keys/ACCC4CF8.asc | gpg --dearmor | tee /etc/apt/trusted.gpg.d/apt.postgresql.org.gpg >/dev/null \
    && apt-get update \
    && apt-get install -y unzip libnss3-tools procps postgresql-client-17 \
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
COPY --from=frontend_builder /app/public/build/ /app/public/build/


COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY . /app

RUN composer install --optimize-autoloader && php artisan optimize:clear


ENTRYPOINT ["bash", "/entrypoint.sh"]
CMD ["php", "artisan", "octane:frankenphp", "--caddyfile", "Caddyfile", "--https", "--http-redirect"]
