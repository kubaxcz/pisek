#!/usr/bin/env bash
set -euo pipefail

# Run backend locally using the PHP built-in server.
# Usage: ./run-backend.sh

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if [[ -f "$SCRIPT_DIR/.env" ]]; then
  set -a
  # shellcheck disable=SC1090
  source "$SCRIPT_DIR/.env"
  set +a
fi

HOST=${HOST:-"127.0.0.1"}
PORT=${PORT:-"8080"}

echo "Starting PHP server on http://${HOST}:${PORT}"

php -S "${HOST}:${PORT}" -t "$SCRIPT_DIR/public" "$SCRIPT_DIR/public/index.php"
