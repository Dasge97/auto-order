# Imagen de Auto-order: Apache con PHP, que sirve la aplicación y los archivos de public/.
FROM php:8.4-apache AS base

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    COMPOSER_ALLOW_SUPERUSER=1

# poppler-utils trae pdftoppm, que convierte cada página de un PDF en una imagen.
# Sin él no se pueden leer las cartas que llegan en PDF.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libpq-dev libicu-dev libzip-dev libpng-dev poppler-utils \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql intl zip gd opcache \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Las cartas llegan en PDF o en fotos, que pesan más que el límite por defecto.
RUN { \
        echo 'upload_max_filesize=25M'; \
        echo 'post_max_size=120M'; \
        echo 'memory_limit=512M'; \
        echo 'max_execution_time=120'; \
        echo 'opcache.enable=1'; \
        echo 'opcache.validate_timestamps=0'; \
    } > /usr/local/etc/php/conf.d/auto-order.ini

COPY docker/apache-site.conf /etc/apache2/sites-available/000-default.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Las dependencias se instalan antes que el código, para aprovechar la caché de capas.
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .

# La cache de Symfony no se genera aqui: en la construccion todavia no existen las
# variables de configuracion reales. La prepara el entrypoint al arrancar.
RUN composer dump-autoload --classmap-authoritative --no-dev \
    && mkdir -p var/uploads var/pdf-paginas var/cache var/log \
    && chown -R www-data:www-data var

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

ENTRYPOINT ["entrypoint"]
CMD ["apache2-foreground"]
