FROM php:8.3-apache AS base

RUN docker-php-ext-install mysqli pdo_mysql \
    && a2enmod rewrite

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/apache.conf /etc/apache2/conf-available/frequencia-facil.conf
RUN a2enconf frequencia-facil \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

WORKDIR /var/www/html

FROM base AS test
COPY . .
RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip \
    && rm -rf /var/lib/apt/lists/* \
    && composer validate --no-check-publish \
    && composer install --prefer-dist --no-interaction

FROM base AS production-vendor
COPY composer.json composer.lock ./
COPY app ./app
RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip \
    && rm -rf /var/lib/apt/lists/* \
    && composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

FROM base AS production
COPY bin ./bin
COPY composer.json composer.lock ./
COPY app ./app
COPY assets ./assets
COPY index.php .htaccess ./
COPY --from=production-vendor /var/www/html/vendor ./vendor

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD php -r "exit(@file_get_contents('http://127.0.0.1/health') === false ? 1 : 0);"
