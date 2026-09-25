#!/usr/bin/env bash
set -euo pipefail

action="${1:-}"
peer_id="${2:-}"
message_id="${3:-}"

case "$action" in
  edit|send-file) ;;
  *) exit 0 ;;
esac

[[ "$peer_id" =~ ^[0-9]+$ ]] || exit 0
[[ "$message_id" =~ ^[0-9]+$ ]] || exit 0

set -a
source /etc/integrated-dent/dent-bot.env
set +a

source_peer="${DENT_BOT_BOOKLET_SOURCE_CHANNEL_ID#-100}"
power_peer="${DENT_BOT_POWER_SOURCE_CHANNEL_ID:-0}"
power_peer="${power_peer#-100}"
if [[ "$peer_id" != "$source_peer" && "$peer_id" != "$power_peer" ]]; then
  exit 0
fi

# Reconciliation reads the source channel directly through the authorized
# user session and registers metadata without sending any temporary message.
exec systemctl start integrated-dent-booklet-source-reconcile.service
