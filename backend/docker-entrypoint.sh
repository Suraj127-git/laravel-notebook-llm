#!/bin/sh
set -e

# ── Wait for Postgres ────────────────────────────────────────────────────────
echo "Waiting for Postgres at ${DB_HOST}:${DB_PORT}..."
until pg_isready -h "${DB_HOST:-postgres}" -p "${DB_PORT:-5432}" -U "${DB_USERNAME:-postgres}" -q; do
  sleep 1
done
echo "Postgres is ready."

# ── Bootstrap .env ───────────────────────────────────────────────────────────
if [ ! -f .env ]; then
  cp .env.example .env
  echo ".env created from .env.example"
fi

# Generate app key only if not set
if grep -q "^APP_KEY=$" .env || grep -q "^APP_KEY=\"\"" .env; then
  php artisan key:generate --force
fi

# ── Run migrations ───────────────────────────────────────────────────────────
php artisan migrate --force --no-interaction

# ── Cache config in production ───────────────────────────────────────────────
if [ "${APP_ENV}" = "production" ]; then
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
fi

# ── Ensure storage dirs exist and are writable ───────────────────────────────
mkdir -p storage/logs storage/app/private/documents storage/framework/cache storage/framework/sessions storage/framework/views
chmod -R 775 storage bootstrap/cache

exec "$@"
