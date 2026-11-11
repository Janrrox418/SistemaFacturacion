FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev \
    && docker-php-ext-install curl pdo_mysql \
    && a2dismod mpm_event mpm_worker \
    && a2enmod mpm_prefork rewrite \
    && apache2ctl configtest \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY . /var/www/html/
COPY docker/php-production.ini /usr/local/etc/php/conf.d/production.ini
COPY railway-start.sh /usr/local/bin/railway-start

RUN chmod 755 /usr/local/bin/railway-start \
    && mkdir -p /data/facturas_sap \
    && chown -R www-data:www-data /data/facturas_sap

ENV APP_ENV=production
ENV INVOICE_STORAGE_PATH=/data/facturas_sap

CMD ["/usr/local/bin/railway-start"]
