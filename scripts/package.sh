#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_DIR="$(mktemp -d "${TMPDIR:-/tmp}/vedismm-drupal-package.XXXXXX")"
DIST_DIR="$ROOT/dist"
TARBALL="$DIST_DIR/vedismm-1.0.0.tar.gz"
trap 'rm -rf "$BUILD_DIR"' EXIT

required_files=(
  "vedismm.info.yml"
  "translations/vedismm.ru.po"
  "docs/en/guide.md"
  "docs/ru/guide.md"
  "marketplace/en/listing.md"
  "marketplace/ru/listing.md"
  "docker-compose.yml"
  "tests/fake-api/router.php"
  "tests/smoke.sh"
  ".github/workflows/ci.yml"
)

fail() {
  printf 'package check failed: %s\n' "$1" >&2
  exit 1
}

for file in "${required_files[@]}"; do
  [[ -f "$ROOT/$file" ]] || fail "missing $file"
done

grep -q 'core_version_requirement: \^11' "$ROOT/vedismm.info.yml" || fail "Drupal 11 compatibility missing"
grep -q 'VediSMM account' "$ROOT/marketplace/en/listing.md" || fail "listing misses external account disclosure"

mkdir -p "$BUILD_DIR/vedismm" "$DIST_DIR"
rm -f "$TARBALL"
cp "$ROOT"/vedismm.* "$BUILD_DIR/vedismm/"
cp "$ROOT/composer.json" "$BUILD_DIR/vedismm/composer.json"
cp -R "$ROOT/src" "$BUILD_DIR/vedismm/src"
cp -R "$ROOT/config" "$BUILD_DIR/vedismm/config"
cp -R "$ROOT/translations" "$BUILD_DIR/vedismm/translations"

(
  cd "$BUILD_DIR"
  tar -czf "$TARBALL" vedismm
)

entries="$(tar -tzf "$TARBALL")"
printf '%s\n' "$entries" | grep -q '^vedismm/vedismm.info.yml$' || fail "archive misses info.yml"
printf '%s\n' "$entries" | grep -q '^vedismm/translations/vedismm.ru.po$' || fail "archive misses Russian translation"
printf '%s\n' "$entries" | grep -q '^vedismm/tests/' && fail "archive contains tests"

printf 'Built %s\n' "$TARBALL"
