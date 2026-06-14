#!/usr/bin/env bash
set -euo pipefail

# Run a local MariaDB container with data persisted in ./db-data.
# Usage: ./start-mariadb.sh

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DATA_DIR="$PROJECT_ROOT/db-data"
CONTAINER_NAME=${CONTAINER_NAME:-"piskari-mariadb"}

mkdir -p "$DATA_DIR"

DB_ROOT_PASSWORD=${DB_ROOT_PASSWORD:-"rootpass"}
DB_NAME=${DB_NAME:-"piskari"}
DB_USER=${DB_USER:-"piskari_user"}
DB_PASSWORD=${DB_PASSWORD:-"piskari_pass"}

echo "Starting MariaDB container '$CONTAINER_NAME' with data dir: $DATA_DIR"

docker run -d \
  --name "$CONTAINER_NAME" \
  -e MARIADB_ROOT_PASSWORD="$DB_ROOT_PASSWORD" \
  -e MARIADB_DATABASE="$DB_NAME" \
  -e MARIADB_USER="$DB_USER" \
  -e MARIADB_PASSWORD="$DB_PASSWORD" \
  -p 3306:3306 \
  -v "$DATA_DIR:/var/lib/mysql" \
  mariadb:11

echo "MariaDB is starting on port 3306. Stop it with: docker stop $CONTAINER_NAME && docker rm $CONTAINER_NAME"
