#!/bin/sh
set -eu
if [ "$#" -ne 1 ]; then echo "Usage: $0 VERSION" >&2; exit 2; fi
version="$1"
export APP_IMAGE="ai-business-engine:$version"
compose="-f docker-compose.prod.yml -f docker-compose.onprem.yml"
docker compose $compose build app worker
docker compose $compose run --rm app php artisan migrate --force
docker compose $compose up -d --remove-orphans
docker compose $compose ps
