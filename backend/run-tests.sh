#!/usr/bin/env bash
set -euo pipefail

# Run backend unit tests.
# Usage: ./run-tests.sh

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if [[ -f "$SCRIPT_DIR/.env" ]]; then
  set -a
  # shellcheck disable=SC1090
  source "$SCRIPT_DIR/.env"
  set +a
fi

if [[ ! -d "$SCRIPT_DIR/vendor" ]]; then
  echo "vendor/ not found. Install dependencies first:" >&2
  echo "  (cd backend && composer install)" >&2
  exit 1
fi

"$SCRIPT_DIR/vendor/bin/phpunit" -c "$SCRIPT_DIR/phpunit.xml"
