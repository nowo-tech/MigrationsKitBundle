#!/bin/sh
set -e


# FRANKENPHP_MODE: classic | worker (REQ-DEMO-010). Default: worker.
# Set via .env / Compose only — not baked into the image ENV.
MODE="${FRANKENPHP_MODE:-worker}"
case "$MODE" in
	classic)
		if [ -f /app/Caddyfile.dev ]; then
			cp /app/Caddyfile.dev /etc/caddy/Caddyfile
		elif [ -f /etc/frankenphp/Caddyfile.dev ]; then
			cp /etc/frankenphp/Caddyfile.dev /etc/frankenphp/Caddyfile
		fi
		;;
	worker)
		if [ -f /app/Caddyfile ]; then
			cp /app/Caddyfile /etc/caddy/Caddyfile
		fi
		# else keep image default Caddyfile (worker enabled)
		;;
	*)
		echo "Unknown FRANKENPHP_MODE=$MODE (expected classic|worker)" >&2
		exit 1
		;;
esac
echo "FrankenPHP mode: $MODE"


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
