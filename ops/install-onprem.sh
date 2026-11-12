#!/bin/sh
set -eu
if [ ! -f .env ]; then cp .env.example .env; fi
if grep -q '^APP_KEY=$' .env; then
  key=$(openssl rand -base64 32 | tr -d '\n')
  sed -i "s|^APP_KEY=.*|APP_KEY=base64:$key|" .env
fi
if grep -q '^DB_PASSWORD=replace-with-a-different-long-random-secret$' .env; then
  password=$(openssl rand -hex 32)
  sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=$password|" .env
fi
if grep -q '^REDIS_PASSWORD=replace-with-a-long-random-secret$' .env; then
  password=$(openssl rand -hex 32)
  sed -i "s|^REDIS_PASSWORD=.*|REDIS_PASSWORD=$password|" .env
fi
docker compose -f docker-compose.prod.yml -f docker-compose.onprem.yml build
docker compose -f docker-compose.prod.yml -f docker-compose.onprem.yml up -d
docker compose -f docker-compose.prod.yml -f docker-compose.onprem.yml run --rm app php artisan migrate --force
