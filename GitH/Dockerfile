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

# The app's HTML/JS hardcode absolute paths under /lasso/ (from local dev,
# e.g. http://localhost/lasso/), so it must be served from that subfolder
# rather than the document root.
COPY . /var/www/html/lasso/

# Redirect the site root to /lasso/ so visiting the bare domain still works.
RUN printf '<?php header("Location: /lasso/index.html"); exit;\n' > /var/www/html/index.php

RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html/lasso/api/uploads -type d -exec chmod 775 {} \;

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
