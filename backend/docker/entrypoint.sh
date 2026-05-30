#!/usr/bin/env sh
set -eu

cd /var/www/html

if [ -n "${APP_KEY:-}" ]; then
  echo "APP_KEY is configured."
else
  echo "ERROR: APP_KEY is empty. Set BACKEND_APP_KEY in the root .env file." >&2
  exit 1
fi

php artisan storage:link || true
php artisan config:cache

exec "$@"
