#!/usr/bin/env bash
set -euo pipefail

# Cross-platform deploy helper for environments where PowerShell is unavailable.
# Mirrors scripts/deploy_public_html.ps1 flow:
# 1) git pull --ff-only (unless --skip-git-pull)
# 2) FTP deploy to /public_html
# 3) live health checks (unless --skip-post-deploy-verification)

DELETE_VSCODE=0
FULL_SYNC=0
DRY_RUN=0
SKIP_GIT_PULL=0
SKIP_POST_DEPLOY_VERIFICATION=0
HEALTH_CHECK_URLS=("https://dentistry1402tums.ir/" "https://dentistry1402tums.ir/chat/")

while [[ $# -gt 0 ]]; do
  case "$1" in
    --delete-vscode-on-remote) DELETE_VSCODE=1; shift ;;
    --full-sync) FULL_SYNC=1; shift ;;
    --dry-run) DRY_RUN=1; shift ;;
    --skip-git-pull) SKIP_GIT_PULL=1; shift ;;
    --skip-post-deploy-verification) SKIP_POST_DEPLOY_VERIFICATION=1; shift ;;
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

CURRENT_BRANCH="$(git -C "$PROJECT_ROOT" rev-parse --abbrev-ref HEAD)"
COMMIT_RANGE=""

run_health_checks() {
  [[ $DRY_RUN -eq 1 ]] && { echo "[DryRun] Step 3/3 skipped: post-deploy verification"; return; }
  [[ $SKIP_POST_DEPLOY_VERIFICATION -eq 1 ]] && { echo "[Warn] Step 3/3 skipped by explicit override."; return; }
  echo "Step 3/3: live post-deploy verification"
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
}

if [[ $SKIP_GIT_PULL -eq 0 ]]; then
  STATUS="$(git -C "$PROJECT_ROOT" status --porcelain --untracked-files=all)"
  if [[ -n "$STATUS" ]]; then
    echo "Working tree is not clean. Commit/stash/discard before deploy, or use --skip-git-pull." >&2
    exit 1
  fi

  UPSTREAM="$(git -C "$PROJECT_ROOT" rev-parse --abbrev-ref --symbolic-full-name '@{upstream}' 2>/dev/null || true)"
  [[ -n "$UPSTREAM" ]] || { echo "No upstream branch configured for current branch." >&2; exit 1; }

  DIVERGENCE="$(git -C "$PROJECT_ROOT" rev-list --left-right --count '@{upstream}...HEAD')"
  BEHIND="${DIVERGENCE%% *}"
  AHEAD="${DIVERGENCE##* }"
  if [[ "$AHEAD" -gt 0 && "$BEHIND" -eq 0 ]]; then
    echo "Local branch is ahead of $UPSTREAM by $AHEAD commit(s). Push first." >&2
    exit 1
  fi
  if [[ "$AHEAD" -gt 0 && "$BEHIND" -gt 0 ]]; then
    echo "Local and remote branches are diverged (ahead $AHEAD, behind $BEHIND)." >&2
    exit 1
  fi

  BEFORE_HEAD="$(git -C "$PROJECT_ROOT" rev-parse HEAD)"
  echo "Step 1/3: git pull --ff-only from $UPSTREAM"
  git -C "$PROJECT_ROOT" pull --ff-only
  AFTER_HEAD="$(git -C "$PROJECT_ROOT" rev-parse HEAD)"
  if [[ "$BEFORE_HEAD" != "$AFTER_HEAD" ]]; then
    COMMIT_RANGE="$BEFORE_HEAD..$AFTER_HEAD"
    echo "Git sync updated HEAD: $BEFORE_HEAD -> $AFTER_HEAD"
  else
    echo "Git sync completed: already up to date."
  fi
fi

TMP_UPLOAD="$(mktemp)"
TMP_DELETE="$(mktemp)"
trap 'rm -f "$TMP_UPLOAD" "$TMP_DELETE"' EXIT

if [[ $FULL_SYNC -eq 1 ]]; then
  find "$LOCAL_ROOT" -type f | sed "s#^$LOCAL_ROOT/##" | sort -u > "$TMP_UPLOAD"
else
  if [[ $SKIP_GIT_PULL -eq 1 ]]; then
    git -C "$PROJECT_ROOT" diff --name-only --diff-filter=ACMRTUXB HEAD -- public_html | sed 's#^public_html/##' | sed '/^$/d' | sort -u > "$TMP_UPLOAD" || true
    git -C "$PROJECT_ROOT" diff --name-only --diff-filter=D HEAD -- public_html | sed 's#^public_html/##' | sed '/^$/d' | sort -u > "$TMP_DELETE" || true
    git -C "$PROJECT_ROOT" ls-files --others --exclude-standard -- public_html | sed 's#^public_html/##' | sed '/^$/d' | sort -u >> "$TMP_UPLOAD" || true
  elif [[ -n "$COMMIT_RANGE" ]]; then
    git -C "$PROJECT_ROOT" diff --name-only --diff-filter=ACMRTUXB "$COMMIT_RANGE" -- public_html | sed 's#^public_html/##' | sed '/^$/d' | sort -u > "$TMP_UPLOAD" || true
    git -C "$PROJECT_ROOT" diff --name-only --diff-filter=D "$COMMIT_RANGE" -- public_html | sed 's#^public_html/##' | sed '/^$/d' | sort -u > "$TMP_DELETE" || true
  fi
fi

sort -u -o "$TMP_UPLOAD" "$TMP_UPLOAD"
sort -u -o "$TMP_DELETE" "$TMP_DELETE"

comm -23 "$TMP_DELETE" "$TMP_UPLOAD" > "$TMP_DELETE.filtered"
mv "$TMP_DELETE.filtered" "$TMP_DELETE"

UPLOAD_COUNT="$(wc -l < "$TMP_UPLOAD" | tr -d ' ')"
DELETE_COUNT="$(wc -l < "$TMP_DELETE" | tr -d ' ')"

if [[ "$UPLOAD_COUNT" -eq 0 && "$DELETE_COUNT" -eq 0 ]]; then
  echo "No changes detected under public_html. Nothing to deploy."
else
  [[ $FULL_SYNC -eq 1 ]] && MODE_LABEL="full-sync" || MODE_LABEL="changed-only"
  [[ $SKIP_GIT_PULL -eq 1 ]] && MODE_LABEL="changed-only (local-state override)"

  echo "Step 2/3: deploy to host"
  echo "Deploy mode: $MODE_LABEL"
  echo "Upload count: $UPLOAD_COUNT"
  echo "Delete count: $DELETE_COUNT"

  while IFS= read -r relative; do
    [[ -n "$relative" ]] || continue
    src="$LOCAL_ROOT/$relative"
    target="ftp://$HOST/$REMOTE_PATH/$relative"
    if [[ $DRY_RUN -eq 1 ]]; then
      echo "[DryRun] Upload $relative"
    else
      echo "Uploading $relative"
      curl --silent --show-error --ftp-create-dirs --user "$USERNAME:$PASSWORD" -T "$src" "$target"
    fi
  done < "$TMP_UPLOAD"

  while IFS= read -r relative; do
    [[ -n "$relative" ]] || continue
    if [[ $DRY_RUN -eq 1 ]]; then
      echo "[DryRun] Delete remote $relative"
    else
      echo "Deleting remote $relative"
      curl --silent --show-error --user "$USERNAME:$PASSWORD" --quote "DELE /$REMOTE_PATH/$relative" "ftp://$HOST/"
    fi
  done < "$TMP_DELETE"
fi

if [[ $DELETE_VSCODE -eq 1 && $DRY_RUN -eq 0 ]]; then
  curl --silent --show-error --user "$USERNAME:$PASSWORD" --quote "RMD /$REMOTE_PATH/.vscode" "ftp://$HOST/" || true
fi

run_health_checks

echo "Deploy completed to /$REMOTE_PATH"
