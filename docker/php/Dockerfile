# syntax=docker/dockerfile:1

FROM php:8.4-fpm-alpine AS base

WORKDIR /var/www

# install-php-extensions resolves build deps, compiles, enables and cleans up in one step —
# no manual apk add / docker-php-ext-configure dance, no leftover build-essential layer.
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

# Beyond what php:alpine ships (see `composer check-platform-reqs --no-dev`, which flags only
# zip and intl as missing): pdo_pgsql to talk to Postgres, pcntl for queue worker signal
# handling, opcache for performance, redis so the redis backend stays a pure env switch.
RUN install-php-extensions pdo_pgsql pcntl opcache redis intl zip

ARG user=www
ARG uid=1000

# Alpine's useradd/groupadd are addgroup/adduser; keep the uid/gid configurable so it can be
# matched to the host user for bind-mount permissions in dev.
RUN addgroup -g ${uid} ${user} \
    && adduser -D -u ${uid} -G ${user} -h /home/${user} ${user} \
    && mkdir -p /home/${user}/.composer \
    && chown -R ${user}:${user} /home/${user} /var/www

USER ${user}

# ---------------------------------------------------------------------------------------------

FROM base AS dev

ARG user=www

USER root

RUN install-php-extensions xdebug

COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

COPY ./docker/php/xdebug.ini /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
COPY ./docker/php/error_reporting.ini /usr/local/etc/php/conf.d/error_reporting.ini

USER ${user}

# ---------------------------------------------------------------------------------------------

# `base` already carries every extension the app needs, so it doubles as the composer runtime
# here — no risk of a generic composer image missing intl/zip and failing composer's platform
# check. --no-scripts: post-autoload-dump would run artisan package:discover/filament:upgrade
# against an application that has no .env yet; the production stage runs package:discover
# itself once the code is in place.
FROM base AS vendor

USER root

COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --prefer-dist --no-interaction --optimize-autoloader

COPY . .
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# ---------------------------------------------------------------------------------------------

FROM base AS production

ARG user=www

USER root

COPY docker/php/production.ini /usr/local/etc/php/conf.d/zz-production.ini

COPY --chown=${user}:${user} . .
COPY --from=vendor --chown=${user}:${user} /var/www/vendor ./vendor

# package:discover needs the app code + vendor present; optimize/filament:optimize run at
# container start instead (docker/php/entrypoint.sh), once the real runtime env is mounted in —
# baking them in here would cache config from a build-time environment that isn't the real one.
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && php artisan storage:link \
    && php artisan package:discover --ansi \
    && chown -R ${user}:${user} storage bootstrap/cache

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

USER ${user}

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]

# ---------------------------------------------------------------------------------------------

FROM nginx:stable-alpine AS web

# Substituted into the template at container start by the nginx image's own envsubst step
# (/etc/nginx/templates/*.template -> /etc/nginx/conf.d/*.conf) — override to point at a
# differently-named app service.
ENV PHP_FPM_HOST=app

COPY docker/nginx/templates/default.conf.template /etc/nginx/templates/default.conf.template
COPY --from=production /var/www/public /var/www/public
