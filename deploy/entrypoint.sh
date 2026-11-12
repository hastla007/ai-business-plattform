#!/bin/sh
set -eu
if [ "${APP_RUN_MIGRATIONS:-false}" = "true" ]; then
  php artisan migrate --force
fi
exec "$@"
