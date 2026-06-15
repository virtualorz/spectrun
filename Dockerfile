# syntax=docker/dockerfile:1

##### Stage 1: composer 依賴 #####
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

##### Stage 2: runtime #####
FROM php:8.3-apache

# 系統套件 + PHP 擴充
RUN apt-get update && apt-get install -y --no-install-recommends \
        libsqlite3-dev libonig-dev libzip-dev unzip supervisor \
    && docker-php-ext-install -j"$(nproc)" pdo pdo_sqlite mbstring zip bcmath \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# 用 production 版 php.ini
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# 讓 Apache 聽 5971,docroot 指到 Laravel 的 public/
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!Listen 80!Listen 5971!' /etc/apache2/ports.conf \
 && sed -ri 's!:80>!:5971>!' /etc/apache2/sites-available/000-default.conf \
 && sed -ri "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" \
        /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf \
 && echo "ServerName localhost" >> /etc/apache2/apache2.conf

# public/ 允許 .htaccess
RUN printf '<Directory %s>\n    AllowOverride All\n    Require all granted\n</Directory>\n' \
        "$APACHE_DOCUMENT_ROOT" > /etc/apache2/conf-available/laravel.conf \
 && a2enconf laravel

WORKDIR /var/www/html
COPY --from=vendor /app /var/www/html
COPY docker/supervisord.conf /etc/supervisor/conf.d/spectrum.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
 && chown -R www-data:www-data storage bootstrap/cache

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/data/database.sqlite

VOLUME ["/data"]
EXPOSE 5971
ENTRYPOINT ["entrypoint.sh"]
CMD ["supervisord","-c","/etc/supervisor/conf.d/spectrum.conf"]
