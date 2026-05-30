#!/usr/bin/env sh
set -eu

docker compose exec backend php artisan migrate:fresh --seed
docker compose exec backend php artisan storage:link || true
docker compose exec backend php artisan config:cache
# route:cache is intentionally skipped because the current route files include Closure routes.

echo "Demo data initialized."
