#!/bin/sh
set -eu
if [ "$#" -ne 2 ]; then echo "Usage: $0 database.sql files.tar.gz" >&2; exit 2; fi
compose="-f docker-compose.prod.yml -f docker-compose.onprem.yml"
echo "This replaces the current database and uploaded files."
docker compose $compose stop nginx app worker scheduler
restart_services() { docker compose $compose up -d; }
trap restart_services EXIT
docker compose $compose exec -T postgres sh -c 'dropdb -U "$POSTGRES_USER" --if-exists "$POSTGRES_DB" && createdb -U "$POSTGRES_USER" "$POSTGRES_DB"'
docker compose $compose exec -T postgres sh -c 'psql -U "$POSTGRES_USER" "$POSTGRES_DB"' < "$1"
docker compose $compose run --rm --no-deps app sh -c 'find /var/www/html/storage/app -mindepth 1 -maxdepth 1 -exec rm -rf {} + && tar -C /var/www/html/storage/app -xzf -' < "$2"
