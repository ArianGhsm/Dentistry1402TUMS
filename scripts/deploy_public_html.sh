#!/usr/bin/env bash
set -euo pipefail

# Cross-platform deploy helper for environments where PowerShell is unavailable.
# Default laptop-first flow:
# 1) local validation
# 2) optional git pull (only with explicit --pull-before-deploy)
# 3) deploy laptop code to /public_html
# 4) live health checks
# 5) commit and push laptop state to GitHub

DELETE_VSCODE=0
FULL_SYNC=0
DRY_RUN=0
SKIP_VALIDATION=0
SKIP_POST_DEPLOY_VERIFICATION=0
SKIP_GITHUB_SYNC=0
PULL_BEFORE_DEPLOY=0
COMMIT_MESSAGE="chore: sync deployed laptop state to github"
HEALTH_CHECK_URLS=("https://dentistry1402tums.ir/" "https://dentistry1402tums.ir/chat/")

while [[ $# -gt 0 ]]; do
  case "$1" in
    --delete-vscode-on-remote) DELETE_VSCODE=1; shift ;;
    --full-sync) FULL_SYNC=1; shift ;;
    --dry-run) DRY_RUN=1; shift ;;
    --skip-validation) SKIP_VALIDATION=1; shift ;;
    --skip-post-deploy-verification) SKIP_POST_DEPLOY_VERIFICATION=1; shift ;;
    --skip-github-sync) SKIP_GITHUB_SYNC=1; shift ;;
    --pull-before-deploy) PULL_BEFORE_DEPLOY=1; shift ;;
    --commit-message)
      shift
      [[ $# -gt 0 ]] || { echo "Missing value for --commit-message" >&2; exit 1; }
      COMMIT_MESSAGE="$1"
      shift
      ;;
    --health-check-url)
      shift
      [[ $# -gt 0 ]] || { echo "Missing value for --health-check-url" >&2; exit 1; }
      HEALTH_CHECK_URLS+=("$1")
      shift
      ;;
    *) echo "Unknown option: $1" >&2; exit 1 ;;
  esac
done

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
LOCAL_ROOT="$PROJECT_ROOT/public_html"
RUN_STARTED_AT="$(date -Iseconds)"

# Load deploy config from .vscode/sftp.json when available, otherwise from env vars.
CONFIG_PATH="$PROJECT_ROOT/.vscode/sftp.json"
HOST="${DEPLOY_FTP_HOST:-}"
USERNAME="${DEPLOY_FTP_USER:-}"
PASSWORD="${DEPLOY_FTP_PASS:-}"
REMOTE_PATH="${DEPLOY_REMOTE_PATH:-public_html}"

if [[ -f "$CONFIG_PATH" ]]; then
  mapfile -t CFG < <(python3 - <<'PY' "$CONFIG_PATH"
import json,sys
p=sys.argv[1]
with open(p,'r',encoding='utf-8') as f:
    c=json.load(f)
print(c.get('host',''))
print(c.get('username',''))
print(c.get('password',''))
print(c.get('remotePath',''))
PY
)
  HOST="${HOST:-${CFG[0]}}"
  USERNAME="${USERNAME:-${CFG[1]}}"
  PASSWORD="${PASSWORD:-${CFG[2]}}"
  REMOTE_PATH="${REMOTE_PATH:-${CFG[3]}}"
fi

if [[ -z "$HOST" || -z "$USERNAME" || -z "$PASSWORD" ]]; then
  echo "Missing deploy credentials. Provide .vscode/sftp.json or DEPLOY_FTP_HOST/DEPLOY_FTP_USER/DEPLOY_FTP_PASS." >&2
  exit 1
fi

if [[ "$REMOTE_PATH" != "public_html" ]]; then
  echo "Unsafe remotePath detected: $REMOTE_PATH. Deploy is locked to public_html." >&2
  exit 1
fi

VALIDATION_STATUS="not-run"
VALIDATION_STARTED_AT=""
VALIDATION_FINISHED_AT=""
VALIDATION_COMMAND=""

OPTIONAL_PULL_STATUS="not-run"
OPTIONAL_PULL_STARTED_AT=""
OPTIONAL_PULL_FINISHED_AT=""
OPTIONAL_PULL_HEAD=""

DEPLOY_STATUS="not-run"
DEPLOY_STARTED_AT=""
DEPLOY_FINISHED_AT=""
DEPLOY_MODE=""
UPLOAD_COUNT=0
DELETE_COUNT=0

VERIFY_STATUS="not-run"
VERIFY_STARTED_AT=""
VERIFY_FINISHED_AT=""

GITHUB_SYNC_STATUS="not-run"
GITHUB_SYNC_STARTED_AT=""
GITHUB_SYNC_FINISHED_AT=""
GITHUB_SYNC_BRANCH=""
GITHUB_SYNC_HEAD=""

CURRENT_BRANCH="$(git -C "$PROJECT_ROOT" rev-parse --abbrev-ref HEAD)"

run_validation() {
  VALIDATION_STARTED_AT="$(date -Iseconds)"
  if [[ "$SKIP_VALIDATION" -eq 1 ]]; then
    VALIDATION_STATUS="skipped-explicit"
    VALIDATION_FINISHED_AT="$(date -Iseconds)"
    echo "[Warn] Step 1/5 skipped by explicit --skip-validation override."
    return
  fi

  if ! command -v python3 >/dev/null 2>&1; then
    echo "python3 is required for validation." >&2
    exit 1
  fi

  VALIDATION_COMMAND="python3 scripts/check_text_integrity.py"
  echo "Step 1/5: local validation"
  (cd "$PROJECT_ROOT" && python3 scripts/check_text_integrity.py)
  VALIDATION_STATUS="completed"
  VALIDATION_FINISHED_AT="$(date -Iseconds)"
}

run_optional_pull() {
  OPTIONAL_PULL_STARTED_AT="$(date -Iseconds)"
  if [[ "$PULL_BEFORE_DEPLOY" -eq 0 ]]; then
    OPTIONAL_PULL_STATUS="skipped-default"
    OPTIONAL_PULL_FINISHED_AT="$(date -Iseconds)"
    return
  fi

  if [[ -n "$(git -C "$PROJECT_ROOT" status --porcelain --untracked-files=all)" ]]; then
    echo "Working tree is not clean. Commit/stash/discard local changes before using --pull-before-deploy." >&2
    exit 1
  fi

  local upstream
  upstream="$(git -C "$PROJECT_ROOT" rev-parse --abbrev-ref --symbolic-full-name '@{upstream}' 2>/dev/null || true)"
  [[ -n "$upstream" ]] || { echo "No upstream branch configured for current branch." >&2; exit 1; }

  echo "Step 2/5: optional git pull --ff-only (explicit override)"
  git -C "$PROJECT_ROOT" pull --ff-only
  OPTIONAL_PULL_HEAD="$(git -C "$PROJECT_ROOT" rev-parse HEAD)"
  OPTIONAL_PULL_STATUS="completed"
  OPTIONAL_PULL_FINISHED_AT="$(date -Iseconds)"
}

TMP_UPLOAD="$(mktemp)"
TMP_DELETE="$(mktemp)"
trap 'rm -f "$TMP_UPLOAD" "$TMP_DELETE" "$TMP_DELETE.filtered"' EXIT

collect_range_delta() {
  local range_spec="$1"
  [[ -n "$range_spec" ]] || return

  git -C "$PROJECT_ROOT" diff --name-only --diff-filter=ACMRTUXB "$range_spec" -- public_html | sed 's#^public_html/##' | sed '/^$/d' >> "$TMP_UPLOAD" || true
  git -C "$PROJECT_ROOT" diff --name-only --diff-filter=D "$range_spec" -- public_html | sed 's#^public_html/##' | sed '/^$/d' >> "$TMP_DELETE" || true

  while IFS=$'\t' read -r status old_path new_path; do
    [[ -n "$new_path" ]] || continue
    [[ "$old_path" == public_html/* ]] && echo "${old_path#public_html/}" >> "$TMP_DELETE"
    [[ "$new_path" == public_html/* ]] && echo "${new_path#public_html/}" >> "$TMP_UPLOAD"
  done < <(git -C "$PROJECT_ROOT" diff --name-status --diff-filter=R "$range_spec" -- public_html || true)
}

build_deploy_plan() {
  if [[ "$FULL_SYNC" -eq 1 ]]; then
    find "$LOCAL_ROOT" -type f | sed "s#^$LOCAL_ROOT/##" | sort -u > "$TMP_UPLOAD"
    : > "$TMP_DELETE"
    DEPLOY_MODE="full-sync (laptop source)"
    return
  fi

  local upstream=""
  upstream="$(git -C "$PROJECT_ROOT" rev-parse --abbrev-ref --symbolic-full-name '@{upstream}' 2>/dev/null || true)"
  if [[ -n "$upstream" ]]; then
    local divergence ahead
    divergence="$(git -C "$PROJECT_ROOT" rev-list --left-right --count '@{upstream}...HEAD')"
    ahead="${divergence##* }"
    if [[ "$ahead" -gt 0 ]]; then
      collect_range_delta '@{upstream}..HEAD'
    fi
  fi

  collect_range_delta 'HEAD'
  git -C "$PROJECT_ROOT" ls-files --others --exclude-standard -- public_html | sed 's#^public_html/##' | sed '/^$/d' >> "$TMP_UPLOAD" || true

  sort -u -o "$TMP_UPLOAD" "$TMP_UPLOAD"
  sort -u -o "$TMP_DELETE" "$TMP_DELETE"
  comm -23 "$TMP_DELETE" "$TMP_UPLOAD" > "$TMP_DELETE.filtered"
  mv "$TMP_DELETE.filtered" "$TMP_DELETE"

  DEPLOY_MODE="local-delta (laptop source)"
}

run_deploy() {
  DEPLOY_STARTED_AT="$(date -Iseconds)"
  DEPLOY_STATUS="completed"

  build_deploy_plan

  UPLOAD_COUNT="$(wc -l < "$TMP_UPLOAD" | tr -d ' ')"
  DELETE_COUNT="$(wc -l < "$TMP_DELETE" | tr -d ' ')"

  echo "Step 3/5: deploy to host"
  if [[ "$UPLOAD_COUNT" -eq 0 && "$DELETE_COUNT" -eq 0 ]]; then
    echo "No local delta detected under public_html. Nothing to deploy."
    DEPLOY_FINISHED_AT="$(date -Iseconds)"
    return
  fi

  echo "Deploy mode: $DEPLOY_MODE"
  echo "Upload count: $UPLOAD_COUNT"
  echo "Delete count: $DELETE_COUNT"

  while IFS= read -r relative; do
    [[ -n "$relative" ]] || continue
    src="$LOCAL_ROOT/$relative"
    target="ftp://$HOST/$REMOTE_PATH/$relative"
    if [[ "$DRY_RUN" -eq 1 ]]; then
      echo "[DryRun] Upload $relative"
    else
      echo "Uploading $relative"
      curl --silent --show-error --ftp-create-dirs --user "$USERNAME:$PASSWORD" -T "$src" "$target"
    fi
  done < "$TMP_UPLOAD"

  while IFS= read -r relative; do
    [[ -n "$relative" ]] || continue
    if [[ "$DRY_RUN" -eq 1 ]]; then
      echo "[DryRun] Delete remote $relative"
    else
      echo "Deleting remote $relative"
      curl --silent --show-error --user "$USERNAME:$PASSWORD" --quote "DELE /$REMOTE_PATH/$relative" "ftp://$HOST/"
    fi
  done < "$TMP_DELETE"

  if [[ "$DELETE_VSCODE" -eq 1 && "$DRY_RUN" -eq 0 ]]; then
    curl --silent --show-error --user "$USERNAME:$PASSWORD" --quote "RMD /$REMOTE_PATH/.vscode" "ftp://$HOST/" || true
  fi

  DEPLOY_FINISHED_AT="$(date -Iseconds)"
}

run_health_checks() {
  VERIFY_STARTED_AT="$(date -Iseconds)"
  if [[ "$DRY_RUN" -eq 1 ]]; then
    echo "[DryRun] Step 4/5 skipped: post-deploy verification"
    VERIFY_STATUS="skipped-dry-run"
    VERIFY_FINISHED_AT="$(date -Iseconds)"
    return
  fi

  if [[ "$SKIP_POST_DEPLOY_VERIFICATION" -eq 1 ]]; then
    echo "[Warn] Step 4/5 skipped by explicit override."
    VERIFY_STATUS="skipped-explicit"
    VERIFY_FINISHED_AT="$(date -Iseconds)"
    return
  fi

  echo "Step 4/5: live post-deploy verification"
  for url in "${HEALTH_CHECK_URLS[@]}"; do
    [[ -n "$url" ]] || continue
    echo "Health check: $url"
    code="$(curl -L -sS -o /dev/null -w '%{http_code}' "$url")"
    if [[ "$code" -lt 200 || "$code" -ge 400 ]]; then
      echo "Health check failed for '$url' with status $code." >&2
      exit 1
    fi
    echo "Health check OK: $url ($code)"
  done

  VERIFY_STATUS="completed"
  VERIFY_FINISHED_AT="$(date -Iseconds)"
}

sync_github() {
  GITHUB_SYNC_STARTED_AT="$(date -Iseconds)"

  if [[ "$DRY_RUN" -eq 1 ]]; then
    echo "[DryRun] Step 5/5 skipped: GitHub sync"
    GITHUB_SYNC_STATUS="skipped-dry-run"
    GITHUB_SYNC_FINISHED_AT="$(date -Iseconds)"
    return
  fi

  if [[ "$SKIP_GITHUB_SYNC" -eq 1 ]]; then
    echo "[Warn] Step 5/5 skipped by explicit --skip-github-sync override."
    GITHUB_SYNC_STATUS="skipped-explicit"
    GITHUB_SYNC_FINISHED_AT="$(date -Iseconds)"
    return
  fi

  echo "Step 5/5: sync GitHub from deployed laptop state"

  git -C "$PROJECT_ROOT" add -A
  if ! git -C "$PROJECT_ROOT" diff --cached --quiet --exit-code; then
    git -C "$PROJECT_ROOT" commit -m "$COMMIT_MESSAGE"
  else
    echo "No new local changes to commit; push will sync existing local commits if needed."
  fi

  local upstream
  upstream="$(git -C "$PROJECT_ROOT" rev-parse --abbrev-ref --symbolic-full-name '@{upstream}' 2>/dev/null || true)"
  if [[ -n "$upstream" ]]; then
    git -C "$PROJECT_ROOT" push
  else
    git -C "$PROJECT_ROOT" push -u origin "$CURRENT_BRANCH"
  fi

  GITHUB_SYNC_STATUS="completed"
  GITHUB_SYNC_BRANCH="$CURRENT_BRANCH"
  GITHUB_SYNC_HEAD="$(git -C "$PROJECT_ROOT" rev-parse HEAD)"
  GITHUB_SYNC_FINISHED_AT="$(date -Iseconds)"
}

run_validation
run_optional_pull
run_deploy
run_health_checks
sync_github

RUN_FINISHED_AT="$(date -Iseconds)"

echo "Deploy completed to /$REMOTE_PATH"
echo "Deployment report (laptop-first):"
echo " - Run started at: $RUN_STARTED_AT"
echo " - Validation status: $VALIDATION_STATUS"
[[ -n "$VALIDATION_STARTED_AT" ]] && echo " - Validation started at: $VALIDATION_STARTED_AT"
[[ -n "$VALIDATION_FINISHED_AT" ]] && echo " - Validation finished at: $VALIDATION_FINISHED_AT"
[[ -n "$VALIDATION_COMMAND" ]] && echo " - Validation command: $VALIDATION_COMMAND"
echo " - Optional pull status: $OPTIONAL_PULL_STATUS"
[[ -n "$OPTIONAL_PULL_STARTED_AT" ]] && echo " - Optional pull started at: $OPTIONAL_PULL_STARTED_AT"
[[ -n "$OPTIONAL_PULL_FINISHED_AT" ]] && echo " - Optional pull finished at: $OPTIONAL_PULL_FINISHED_AT"
[[ -n "$OPTIONAL_PULL_HEAD" ]] && echo " - HEAD after optional pull: $OPTIONAL_PULL_HEAD"
echo " - Deploy status: $DEPLOY_STATUS"
[[ -n "$DEPLOY_STARTED_AT" ]] && echo " - Deploy step started at: $DEPLOY_STARTED_AT"
[[ -n "$DEPLOY_FINISHED_AT" ]] && echo " - Deploy step finished at: $DEPLOY_FINISHED_AT"
[[ -n "$DEPLOY_MODE" ]] && echo " - Deploy mode: $DEPLOY_MODE"
echo " - Upload count: $UPLOAD_COUNT"
echo " - Delete count: $DELETE_COUNT"
echo " - Verification status: $VERIFY_STATUS"
[[ -n "$VERIFY_STARTED_AT" ]] && echo " - Verification started at: $VERIFY_STARTED_AT"
[[ -n "$VERIFY_FINISHED_AT" ]] && echo " - Verification finished at: $VERIFY_FINISHED_AT"
echo " - GitHub sync status: $GITHUB_SYNC_STATUS"
[[ -n "$GITHUB_SYNC_STARTED_AT" ]] && echo " - GitHub sync started at: $GITHUB_SYNC_STARTED_AT"
[[ -n "$GITHUB_SYNC_FINISHED_AT" ]] && echo " - GitHub sync finished at: $GITHUB_SYNC_FINISHED_AT"
[[ -n "$GITHUB_SYNC_BRANCH" ]] && echo " - GitHub sync branch: $GITHUB_SYNC_BRANCH"
[[ -n "$GITHUB_SYNC_HEAD" ]] && echo " - GitHub sync HEAD: $GITHUB_SYNC_HEAD"
echo " - Run finished at: $RUN_FINISHED_AT"
