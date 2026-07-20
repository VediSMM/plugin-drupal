#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

bash scripts/package.sh --check >/dev/null
php -l tests/fake-api/router.php >/dev/null

if command -v docker >/dev/null 2>&1; then
  docker compose config >/dev/null
fi

test -f dist/vedismm-1.0.0.tar.gz
tar -tzf dist/vedismm-1.0.0.tar.gz | grep -q '^vedismm/vedismm.info.yml$'
printf 'Drupal smoke checks passed.\n'
