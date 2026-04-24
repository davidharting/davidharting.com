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

# PHP runtime config.
#
# memory_limit: the dunglas/frankenphp base image does not override php.ini, so
# by default we'd inherit PHP's compile-time default of 128M. Bumped to 256M
# to give Octane worker threads headroom for Filament exports and import
# actions.
#
# Sizing rule from FrankenPHP's performance docs:
#     num_threads × memory_limit < available_memory
# FrankenPHP spawns 2 × CPU cores worker threads by default. On the Render
# starter plan (0.5 CPU, 512 MB) that's ~1 thread, so 256M fits comfortably
# alongside Caddy + Go runtime overhead. If we move to a larger Render plan,
# revisit this — on standard (1 CPU, 2 GB) the default 2 threads × 256M = 512M
# is still fine, but on pro (2 CPU, 4 GB) with 4 threads we'd use 1 GB for PHP.
#
# The worker / scheduler services run a single PHP process (queue:work /
# schedule:work) so the thread multiplier doesn't apply — 256M is strictly
# safe there.
RUN printf "memory_limit = 256M\nupload_max_filesize = 25M\npost_max_size = 27M\n" \
    > /usr/local/etc/php/conf.d/php.ini

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
