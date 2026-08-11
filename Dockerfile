FROM composer:2 AS vendor

WORKDIR /app

ENV COMPOSER_ALLOW_SUPERUSER=1

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

FROM php:8.4-cli

WORKDIR /app

COPY . .
COPY --from=vendor /app/vendor ./vendor

EXPOSE 8000

CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
