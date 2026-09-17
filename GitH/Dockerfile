# =========================================================
# LASSO — Dockerfile for Render deployment
# Render has no native PHP runtime, so PHP apps run via Docker.
# =========================================================
FROM php:8.3-apache

# libpq-dev provides the PostgreSQL headers pdo_pgsql needs to compile
RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev \
    && rm -rf /var/lib/apt/lists/*

# pdo_pgsql lets PHP's PDO talk to Supabase's PostgreSQL database
RUN docker-php-ext-install pdo pdo_pgsql pgsql

RUN a2enmod rewrite

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
