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
"$PYTHON_BIN" scripts/test_auth_sessions_contracts.py || fail "test_auth_sessions_contracts.py"
"$NODE_BIN" scripts/test_auth_frontend_session_contracts.cjs || fail "test_auth_frontend_session_contracts.cjs"

section "Exam content quality"
"$PHP_BIN" scripts/check_exam_content_quality.php || fail "check_exam_content_quality.php"

section "Exam catalog timeline"
"$PHP_BIN" scripts/check_exam_catalog_timeline.php || fail "check_exam_catalog_timeline.php"

section "Exam home highlights index"
"$PHP_BIN" scripts/build_exam_home_highlights_index.php --check || fail "build_exam_home_highlights_index.php --check"

section "Term 6 final-exam schedule"
"$PHP_BIN" scripts/check_term6_final_exam_schedule.php || fail "check_term6_final_exam_schedule.php"

section "Upload pipeline config"
"$PHP_BIN" scripts/check_upload_pipeline_config.php || fail "check_upload_pipeline_config.php"

section "Unit tests"
"$PHP_BIN" scripts/test_unit.php || fail "test_unit.php"
"$PHP_BIN" scripts/test_exam_reminder_retirement.php || fail "test_exam_reminder_retirement.php"
"$PHP_BIN" scripts/test_term7_academic_assistant.php || fail "test_term7_academic_assistant.php"

section "Signed bot integration contracts"
"$PYTHON_BIN" scripts/test_bot_integration_contracts.py || fail "test_bot_integration_contracts.py"
"$PHP_BIN" scripts/test_bot_persistence.php || fail "test_bot_persistence.php"
"$PYTHON_BIN" scripts/test_bot_recovery_merge.py || fail "test_bot_recovery_merge.py"
"$PYTHON_BIN" scripts/test_bot_snapshot_safety.py || fail "test_bot_snapshot_safety.py"

section "Zibal first-party payment handoff"
"$PYTHON_BIN" scripts/test_payment_handoff_http.py || fail "test_payment_handoff_http.py"

section "Result"
if [ "$status" -eq 0 ]; then
  echo "All static checks passed."
else
  echo "One or more static checks failed."
fi
exit "$status"
