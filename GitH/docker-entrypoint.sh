#!/bin/sh
# Render assigns a dynamic $PORT and requires the app to bind to it.
# Apache defaults to port 80, so remap it at container startup.
set -e
: "${PORT:=80}"
sed -i "s/80/${PORT}/g" /etc/apache2/ports.conf /etc/apache2/sites-enabled/000-default.conf
exec "$@"
