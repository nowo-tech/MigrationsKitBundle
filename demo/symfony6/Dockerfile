# FrankenPHP + PHP 8.2 for Migrations Kit Bundle Demo (Symfony 6)
# https://frankenphp.dev/docs/docker/
FROM dunglas/frankenphp:1-php8.2-alpine

RUN apk add --no-cache git unzip bash

RUN install-php-extensions zip intl pdo_sqlite

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN git config --global --add safe.directory /app && \
	git config --global --add safe.directory /var/migrations-kit-bundle

WORKDIR /app

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV PATH="/app/vendor/bin:${PATH}"
ENV SERVER_NAME=:80

COPY docker/frankenphp/Caddyfile /etc/frankenphp/Caddyfile
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
