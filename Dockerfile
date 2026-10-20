# Imagem da aplicação (API + telas) — PHP 8.3 com Apache.
# O CD constrói esta imagem e publica no GitHub Container Registry.

# 1) Dependências do Composer (só produção)
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

# 2) Imagem final
FROM php:8.3-apache

RUN docker-php-ext-install pdo_mysql \
 && a2enmod rewrite

# Apache servindo a pasta public/
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf

WORKDIR /var/www/html
COPY --from=vendor /app/vendor ./vendor
COPY config ./config
COPY src ./src
COPY public ./public
RUN mkdir -p logs && chown -R www-data:www-data logs

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
  CMD php -r "exit(@file_get_contents('http://localhost/api/health') === false ? 1 : 0);"
