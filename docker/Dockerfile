FROM php:8.3-apache

RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/apache.conf /etc/apache2/conf-available/frequencia-facil.conf
RUN a2enconf frequencia-facil \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

WORKDIR /var/www/html
COPY . .
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
