#!/bin/sh
set -eu

port="${PORT:-8080}"
case "$port" in
    ''|*[!0-9]*)
        echo "PORT must be a numeric TCP port." >&2
        exit 1
        ;;
esac

a2dismod mpm_event mpm_worker mpm_prefork
a2enmod mpm_prefork
apache2ctl -M

mkdir -p "${INVOICE_STORAGE_PATH:-/data/facturas_sap}"
chown -R www-data:www-data "${INVOICE_STORAGE_PATH:-/data/facturas_sap}"

sed -i "s/^Listen 80$/Listen ${port}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \\*:80>/<VirtualHost *:${port}>/" /etc/apache2/sites-available/000-default.conf

apache2ctl configtest

exec apache2-foreground
