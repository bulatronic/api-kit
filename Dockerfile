FROM dunglas/frankenphp:1-php8.5

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# install-php-extensions is already in the dunglas/frankenphp base image
RUN set -eux; install-php-extensions zip opcache

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    GIT_CONFIG_COUNT=1 \
    GIT_CONFIG_KEY_0=safe.directory \
    GIT_CONFIG_VALUE_0=/app

WORKDIR /app

# composer.lock is .gitignore'd (this is a library, not an app — consumers
# resolve their own lock), so a fresh CI checkout never has it. The trailing
# "*" makes the COPY optional: present locally -> locked install, absent in
# CI -> composer install resolves fresh.
COPY composer.json composer.lock* ./
RUN composer install --optimize-autoloader --no-interaction --no-scripts

COPY . .

RUN mkdir -p /app/public /app/var/cache /app/var/log \
    && chown -R www-data:www-data /app/var \
    && chmod -R 775 /app/var \
    && chown www-data:www-data /app
