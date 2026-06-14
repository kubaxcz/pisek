#!/usr/bin/env bash
set -euo pipefail

# Run backend linting (PHP-CS-Fixer in dry-run mode).
# Usage: ./run-lint.sh

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if [[ ! -d "$SCRIPT_DIR/vendor" ]]; then
  echo "vendor/ not found. Install dependencies first:" >&2
  echo "  (cd backend && composer install)" >&2
  exit 1
fi

"$SCRIPT_DIR/vendor/bin/php-cs-fixer" fix --allow-risky=yes --dry-run --diff
