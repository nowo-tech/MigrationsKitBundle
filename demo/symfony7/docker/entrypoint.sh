#!/bin/sh
set -e

if [ "${APP_ENV:-prod}" = "dev" ] && [ -f /etc/frankenphp/Caddyfile.dev ]; then
	cp /etc/frankenphp/Caddyfile.dev /etc/frankenphp/Caddyfile
fi

cd /app
mkdir -p var/cache var/log var
chmod -R 777 var 2>/dev/null || true

if [ ! -f vendor/autoload_runtime.php ]; then
	echo "📦 Installing dependencies..."
	composer install --no-interaction || true
	if [ ! -f vendor/autoload_runtime.php ]; then
		echo "⚠️  vendor/ missing. Keep container running; run 'make install' from host to install."
		exec sleep infinity
	fi
fi

exec frankenphp run --config /etc/frankenphp/Caddyfile --adapter caddyfile
