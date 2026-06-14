#!/usr/bin/env bash
set -euo pipefail

# Apply SQL schema files into the running MariaDB container.
# Usage: ./setup-db.sh

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

CONTAINER_NAME=${CONTAINER_NAME:-"piskari-mariadb"}
DB_ROOT_PASSWORD=${DB_ROOT_PASSWORD:-"rootpass"}
DB_NAME=${DB_NAME:-"piskari"}
CREATE_TEST_DB=${CREATE_TEST_DB:-"0"}

SQL_FILES=(
  "$PROJECT_ROOT/001_catalog.sql"
  "$PROJECT_ROOT/002_scrape.sql"
  "$PROJECT_ROOT/003_users_ascents.sql"
)

echo "Using container: $CONTAINER_NAME"
echo "Target database: $DB_NAME"

echo "Waiting for MariaDB to be ready..."
for i in {1..60}; do
  if docker exec "$CONTAINER_NAME" mariadb-admin ping -uroot -p"$DB_ROOT_PASSWORD" --silent >/dev/null 2>&1; then
    break
  fi
  sleep 1
  if [[ $i -eq 60 ]]; then
    echo "MariaDB did not become ready in time." >&2
    exit 1
  fi
done

if [[ "$CREATE_TEST_DB" == "1" ]]; then
  echo "Ensuring test database and user..."
  docker exec -i "$CONTAINER_NAME" mariadb -uroot -p"$DB_ROOT_PASSWORD" -e "CREATE DATABASE IF NOT EXISTS piskari_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER IF NOT EXISTS 'piskari_test_user'@'%' IDENTIFIED BY 'piskari_test_pass'; GRANT ALL PRIVILEGES ON piskari_test.* TO 'piskari_test_user'@'%'; FLUSH PRIVILEGES;"
fi

echo "Applying SQL files to $DB_NAME..."
for f in "${SQL_FILES[@]}"; do
  if [[ ! -f "$f" ]]; then
    echo "Missing SQL file: $f" >&2
    exit 1
  fi
  echo "- Applying $(basename "$f")"
  docker exec -i "$CONTAINER_NAME" mariadb -uroot -p"$DB_ROOT_PASSWORD" "$DB_NAME" < "$f"
done

echo "Done."
