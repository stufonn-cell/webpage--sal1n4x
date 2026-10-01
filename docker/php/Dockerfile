FROM php:8.3-fpm-alpine AS base

RUN apk add --no-cache icu-dev oniguruma-dev libzip-dev \
    && docker-php-ext-install pdo_mysql intl mbstring zip opcache \
    && addgroup -g 1000 psiclinic \
    && adduser -u 1000 -G psiclinic -s /bin/sh -D psiclinic

COPY docker/php/php.ini /usr/local/etc/php/conf.d/psiclinic.ini

WORKDIR /var/www/html

RUN mkdir -p storage/uploads storage/logs \
    && chown -R psiclinic:psiclinic /var/www/html

EXPOSE 9000
CMD ["php-fpm"]

# Entorno local: el codigo de backend/ llega por un volumen montado.
FROM base AS development

USER psiclinic

# Entorno de produccion: el codigo viaja dentro de la imagen.
FROM base AS production

COPY --chown=psiclinic:psiclinic backend/ /var/www/html

RUN rm -rf /var/www/html/tests \
    && echo 'opcache.validate_timestamps=0' >> /usr/local/etc/php/conf.d/psiclinic.ini

USER psiclinic
