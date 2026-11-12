#!/bin/sh
set -eu
: "${BACKUP_DIR:=./backups}"
mkdir -p "$BACKUP_DIR"
stamp=$(date -u +%Y%m%dT%H%M%SZ)
compose="-f docker-compose.prod.yml -f docker-compose.onprem.yml"
docker compose $compose stop nginx app worker scheduler
restart_services() { docker compose $compose up -d; }
trap restart_services EXIT
docker compose $compose exec -T postgres sh -c 'pg_dump -U "$POSTGRES_USER" "$POSTGRES_DB"' > "$BACKUP_DIR/database-$stamp.sql"
docker compose $compose run --rm --no-deps app tar -C /var/www/html/storage/app -czf - . > "$BACKUP_DIR/files-$stamp.tar.gz"
echo "Backup written: $BACKUP_DIR/*-$stamp.*"
