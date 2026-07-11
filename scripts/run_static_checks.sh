#!/usr/bin/env bash
#
# Deterministic, offline static checks for CI and local pre-push runs.
# No live server, database, network or credentials required.
#
# Usage: bash scripts/run_static_checks.sh
set -uo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT_DIR"

status=0
section() { printf '\n=== %s ===\n' "$1"; }
fail() { echo "FAILED: $1"; status=1; }

PHP_BIN="${PHP_BIN:-php}"
PYTHON_BIN="${PYTHON_BIN:-python3}"
NODE_BIN="${NODE_BIN:-node}"

if ! "$PYTHON_BIN" --version >/dev/null 2>&1; then
  PYTHON_BIN="python"
fi

section "PHP lint (public_html + scripts)"
while IFS= read -r -d '' file; do
  if ! "$PHP_BIN" -l "$file" >/dev/null; then
    fail "php -l $file"
  fi
done < <(find public_html scripts -type f -name '*.php' -print0)

section "JavaScript syntax (node --check)"
if command -v "$NODE_BIN" >/dev/null 2>&1; then
  while IFS= read -r -d '' file; do
    if ! "$NODE_BIN" --check "$file"; then
      fail "node --check $file"
    fi
  done < <(find public_html -type f -name '*.js' -not -path '*/vendor/*' -print0)
else
  echo "SKIP: node not available"
fi

section "Persian / UTF-8 text integrity"
"$PYTHON_BIN" scripts/check_text_integrity.py || fail "check_text_integrity.py"

section "Instruction contract audit"
"$PYTHON_BIN" scripts/check_instruction_contracts.py || fail "check_instruction_contracts.py"

section "Auth store resilience"
"$PHP_BIN" scripts/check_auth_store_resilience.php || fail "check_auth_store_resilience.php"

section "Exam content quality"
"$PHP_BIN" scripts/check_exam_content_quality.php || fail "check_exam_content_quality.php"

section "Exam catalog timeline"
"$PHP_BIN" scripts/check_exam_catalog_timeline.php || fail "check_exam_catalog_timeline.php"

section "Upload pipeline config"
"$PHP_BIN" scripts/check_upload_pipeline_config.php || fail "check_upload_pipeline_config.php"

section "Unit tests"
"$PHP_BIN" scripts/test_unit.php || fail "test_unit.php"

section "Result"
if [ "$status" -eq 0 ]; then
  echo "All static checks passed."
else
  echo "One or more static checks failed."
fi
exit "$status"
