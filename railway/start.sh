#!/usr/bin/env sh
set -eu

role="${RAILWAY_RUN_ROLE:-${SERVICE_ROLE:-web}}"

if [ "$role" = "worker" ]; then
    exec php artisan queue:work "${QUEUE_CONNECTION:-redis}" --tries=3 --sleep=1 --timeout=90
fi

php artisan migrate --force
php artisan storage:link --force || true

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"
