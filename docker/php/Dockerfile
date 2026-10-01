FROM php:8.3-fpm-alpine AS base

RUN apk add --no-cache icu-dev oniguruma-dev libzip-dev \
    && docker-php-ext-install pdo_mysql intl mbstring zip opcache \
    && addgroup -g 1000 psiclinic \
    && adduser -u 1000 -G psiclinic -s /bin/sh -D psiclinic

COPY docker/php/php.ini /usr/local/etc/php/conf.d/psiclinic.ini

# PHP-FPM pool: a bounded number of workers (a flood queues instead of eating
# all the memory), no request can run forever, and only .php is executed.
RUN { \
        echo '[www]'; \
        echo 'pm.max_children = 10'; \
        echo 'request_terminate_timeout = 180s'; \
        echo 'security.limit_extensions = .php'; \
    } > /usr/local/etc/php-fpm.d/zz-psiclinic.conf

WORKDIR /var/www/html

RUN mkdir -p storage/uploads storage/logs \
    && chown -R psiclinic:psiclinic /var/www/html \
    && chmod -R 0770 storage

EXPOSE 9000
CMD ["php-fpm"]

# Local environment: the backend/ code arrives through a mounted volume.
FROM base AS development

USER psiclinic

# Production environment: the code ships inside the image.
FROM base AS production

COPY --chown=psiclinic:psiclinic backend/ /var/www/html

RUN rm -rf /var/www/html/tests \
    && echo 'opcache.validate_timestamps=0' >> /usr/local/etc/php/conf.d/psiclinic.ini

USER psiclinic
