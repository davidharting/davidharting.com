FROM node:22 as frontend_builder

COPY . /app
WORKDIR /app

RUN npm ci --no-audit
RUN npm run build




FROM dunglas/frankenphp:php8.2-bookworm 

RUN apt-get update && \
    apt-get install -y unzip libnss3-tools && \
    rm -rf /var/lib/apt/lists/*

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

RUN composer install --optimize-autoloader \
    && php artisan optimize:clear


ENTRYPOINT ["/entrypoint.sh"]
CMD ["php", "artisan", "octane:frankenphp", "--host", "localhost", "--https", "--http-redirect"]