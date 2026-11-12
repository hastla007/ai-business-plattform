#!/bin/sh
set -eu
if [ "$#" -ne 1 ]; then echo "Usage: $0 PREVIOUS_VERSION" >&2; exit 2; fi
export APP_IMAGE="ai-business-engine:$1"
compose="-f docker-compose.prod.yml -f docker-compose.onprem.yml"
docker compose $compose up -d --no-build --remove-orphans
docker compose $compose ps
