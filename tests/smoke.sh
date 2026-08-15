#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

bash scripts/package.sh --check >/dev/null
php -l tests/fake-api/router.php >/dev/null

if command -v docker >/dev/null 2>&1; then
  docker compose config >/dev/null
fi

test -f dist/vedismm-1.1.0.tar.gz
archive_entries="$(mktemp "${TMPDIR:-/tmp}/vedismm-drupal-archive.XXXXXX")"
trap 'rm -f "$archive_entries"' EXIT
tar -tzf dist/vedismm-1.1.0.tar.gz > "$archive_entries"
grep -q '^vedismm/vedismm.info.yml$' "$archive_entries"
printf 'Drupal smoke checks passed.\n'
