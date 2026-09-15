#!/usr/bin/env bash
set -euo pipefail

service=integrated-dent-telegram-egress.service
source_env=/etc/integrated-dent/telegram-egress.env
target_config=/etc/integrated-dent/telegram-egress.json
xray=/usr/local/lib/integrated-dent/xray/xray
selector=/usr/local/lib/integrated-dent/xray/select-telegram-egress.py
active_port=11080
probe_port=11081
probe_url=https://api.telegram.org/bot0:invalid/getMe

exec 9>/run/lock/integrated-dent-telegram-egress-refresh.lock
if ! flock -n 9; then
  echo '{"success":true,"skipped":"already-running"}'
  exit 0
fi

probe_once() {
  local code
  code="$(curl --silent --show-error --proxy "http://127.0.0.1:${active_port}" \
    --max-time 4 -o /dev/null -w '%{http_code}' "$probe_url" 2>/dev/null || true)"
  test "$code" = 401
}

current_healthy() {
  systemctl is-active --quiet "$service" || return 1
  ss -H -lnt "sport = :${active_port}" | grep -q "127.0.0.1:${active_port}" || return 1
  local success=0
  local attempt
  for attempt in 1 2 3; do
    if probe_once; then
      success=$((success + 1))
    fi
    test "$attempt" = 3 || sleep 0.25
  done
  test "$success" -ge 2
}

# Normal timer runs are health checks only. Do not fetch subscriptions, rewrite
# configuration or restart Xray while the currently active route is healthy.
if current_healthy; then
  echo '{"success":true,"action":"healthy-no-change"}'
  exit 0
fi

work="$(mktemp -d /run/integrated-dent-egress-refresh.XXXXXX)"
trap 'rm -rf -- "$work"' EXIT
candidate="$work/candidate.json"
previous="$work/previous.json"
had_previous=0
if test -s "$target_config"; then
  cp -a "$target_config" "$previous"
  had_previous=1
fi

# Failover path only: discover and pre-validate a replacement on the probe port.
"$selector" \
  --env-file "$source_env" \
  --xray "$xray" \
  --output "$candidate" \
  --port "$active_port" --probe-port "$probe_port"
chown root:dentegress "$candidate"
chmod 0640 "$candidate"
runuser -u dentegress -- "$xray" run -test -c "$candidate" >/dev/null

candidate_hash="$(sha256sum "$candidate" | cut -d' ' -f1)"
current_hash=''
if test -s "$target_config"; then
  current_hash="$(sha256sum "$target_config" | cut -d' ' -f1)"
fi
if test "$candidate_hash" != "$current_hash"; then
  install -o root -g dentegress -m 0640 "$candidate" "$target_config.new"
  mv -f "$target_config.new" "$target_config"
fi

# The bot unit only Wants this service, so an egress recovery does not stop the
# long-polling bot process. At worst polling observes a short transport retry.
systemctl restart "$service"

listener_ready=0
for wait_attempt in $(seq 1 50); do
  if ss -H -lnt "sport = :${active_port}" | grep -q "127.0.0.1:${active_port}"; then
    listener_ready=1
    break
  fi
  sleep 0.2
done
live_ok=0
if test "$listener_ready" = 1; then
  for live_attempt in 1 2; do
    if probe_once; then
      live_ok=$((live_ok + 1))
    fi
  done
fi
if test "$live_ok" != 2; then
  if test "$had_previous" = 1; then
    install -o root -g dentegress -m 0640 "$previous" "$target_config"
    systemctl restart "$service" || true
  fi
  echo '{"success":false,"reason":"live-post-activation-probe"}' >&2
  exit 1
fi

if test "$candidate_hash" = "$current_hash"; then
  echo '{"success":true,"action":"recovered-current-config"}'
else
  echo '{"success":true,"action":"failed-over"}'
fi