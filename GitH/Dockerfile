# =========================================================
# LASSO — Dockerfile for Render deployment
# Render has no native PHP runtime, so PHP apps run via Docker.
# =========================================================
FROM php:8.3-apache

# pdo_pgsql lets PHP's PDO talk to Supabase's PostgreSQL database.
# libpq-dev provides libpq-fe.h, which pdo_pgsql/pgsql need to compile.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo pdo_pgsql pgsql

RUN a2enmod rewrite

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html/api/uploads -type d -exec chmod 775 {} \;

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
