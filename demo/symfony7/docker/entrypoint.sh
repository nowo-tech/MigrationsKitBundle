#!/bin/sh
set -e

cd /app
mkdir -p var/cache var/log var
chmod -R 777 var 2>/dev/null || true

if [ ! -f vendor/autoload_runtime.php ]; then
	echo "📦 Installing dependencies..."
	composer install --no-interaction
	echo "✅ Composer install done."
fi

exec frankenphp run --config /etc/frankenphp/Caddyfile --adapter caddyfile
