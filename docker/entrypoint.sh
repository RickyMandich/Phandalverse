#!/bin/sh
set -e

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Ripopola il volume condiviso con nginx con gli asset Vite dell'immagine corrente
# (svuota prima, cosi' i file hashati delle build precedenti non restano)
if [ -d /opt/build-seed ]; then
    mkdir -p /var/www/html/public/build
    find /var/www/html/public/build -mindepth 1 -delete
    cp -a /opt/build-seed/. /var/www/html/public/build/
fi

exec "$@"
