# =========================================================
# LASSO — Dockerfile for Render deployment
# Render has no native PHP runtime, so PHP apps run via Docker.
# =========================================================
FROM php:8.3-apache

# libpq-dev provides the PostgreSQL headers pdo_pgsql needs to compile
# libpng/libjpeg/libwebp let PHP's GD library shrink page images into reader thumbnails
RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev \
        libpng-dev libjpeg62-turbo-dev libwebp-dev \
    && rm -rf /var/lib/apt/lists/*

# pdo_pgsql lets PHP's PDO talk to Supabase's PostgreSQL database; gd makes thumbnails
RUN docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo pdo_pgsql pgsql gd

RUN a2enmod rewrite

COPY . /var/www/html/
COPY uploads.ini /usr/local/etc/php/conf.d/uploads.ini

RUN chown -R www-data:www-data /var/www/html

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
