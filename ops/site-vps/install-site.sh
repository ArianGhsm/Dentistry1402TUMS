#!/usr/bin/env bash
set -Eeuo pipefail

if [[ $# -ne 2 ]]; then
  echo "usage: install-site.sh <release-sha> <bundle-dir>" >&2
  exit 64
fi

release_sha="$1"
bundle_dir="$2"
root=/srv/dentistry1402
release_dir="$root/releases/$release_sha"
paused_backup_timers=()

restore_paused_backup_timers() {
  local status=$?
  trap - EXIT
  for timer in "${paused_backup_timers[@]}"; do
    systemctl start "$timer" || status=1
  done
  exit "$status"
}
trap restore_paused_backup_timers EXIT

[[ "$release_sha" =~ ^[0-9a-f]{40}$ ]] || { echo "invalid release sha" >&2; exit 65; }
for required in \
  "code-$release_sha.tar.gz" storage.tar.gz server-only.tar.gz production.env \
  site-cert.pem site-key.pem nginx-dentistry1402.conf php-fpm-dentistry1402.conf \
  session-clean.sh dentistry1402-session-clean.service dentistry1402-session-clean.timer \
  backup-runtime.sh dentistry1402-backup.service dentistry1402-backup.timer \
  backup_retention.py dentistry1402-backup-retention.service dentistry1402-backup-retention.timer \
  restore-drill.sh dentistry1402-restore-drill.service dentistry1402-restore-drill.timer \
  send-bale-database-backup.py dentistry1402-bale-database-backup.service dentistry1402-bale-database-backup.timer \
  housekeeping.sh dentistry1402-housekeeping.service dentistry1402-housekeeping.timer; do
  [[ -f "$bundle_dir/$required" ]] || { echo "missing bundle: $required" >&2; exit 66; }
done

for timer in \
  dentistry1402-backup.timer \
  dentistry1402-backup-retention.timer \
  dentistry1402-restore-drill.timer \
  dentistry1402-bale-database-backup.timer; do
  if systemctl is-active --quiet "$timer"; then
    systemctl stop "$timer"
    paused_backup_timers+=("$timer")
  fi
done
for service in \
  dentistry1402-backup.service \
  dentistry1402-backup-retention.service \
  dentistry1402-restore-drill.service \
  dentistry1402-bale-database-backup.service; do
  if systemctl is-active --quiet "$service"; then
    echo "A Dentistry backup service is still active; retry after it completes." >&2
    exit 75
  fi
done

if ! getent passwd dentweb >/dev/null; then
  useradd --system --home-dir "$root" --shell /usr/sbin/nologin dentweb
fi
usermod -a -G dentweb www-data

install -d -o root -g dentweb -m 0750 "$root" "$root/releases"
install -d -o dentweb -g dentweb -m 0750 \
  "$root/shared/storage" \
  "$root/shared/server-only" \
  "$root/shared/server-only/sessions" \
  "$root/shared/server-only/secrets" \
  "$root/shared/server-only/tmp" \
  "$root/shared/server-only/backups"
install -d -o root -g root -m 0755 "$root/shared/acme/.well-known/acme-challenge"
install -d -o root -g root -m 0750 "$root/shared/tls"
install -d -o root -g root -m 0755 /usr/local/lib/dentistry1402
install -d -o root -g root -m 0700 \
  /var/backups/dentistry1402/runtime \
  /var/backups/dentistry1402/site-data \
  /var/backups/dentistry1402/bale-database \
  /var/backups/dentistry1402/restore-drills

if [[ ! -d "$release_dir" ]]; then
  install -d -o root -g dentweb -m 0750 "$release_dir"
  tar -xzf "$bundle_dir/code-$release_sha.tar.gz" -C "$release_dir"
fi

if [[ -z "$(find "$root/shared/storage" -mindepth 1 -maxdepth 1 -print -quit)" ]]; then
  tar -xzf "$bundle_dir/storage.tar.gz" -C "$root/shared/storage"
fi
if [[ -z "$(find "$root/shared/server-only" -mindepth 1 -maxdepth 1 ! -name sessions ! -name secrets ! -name tmp ! -name backups -print -quit)" ]] \
   && [[ -z "$(find "$root/shared/server-only/sessions" -mindepth 1 -print -quit)" ]]; then
  tar -xzf "$bundle_dir/server-only.tar.gz" -C "$root/shared/server-only"
fi

install -o root -g dentweb -m 0640 "$bundle_dir/production.env" "$root/shared/server-only/.env"
install -o root -g root -m 0644 "$bundle_dir/site-cert.pem" "$root/shared/tls/fullchain.pem"
install -o root -g root -m 0600 "$bundle_dir/site-key.pem" "$root/shared/tls/privkey.pem"

chown -R root:dentweb "$release_dir"
find "$release_dir" -type d -exec chmod 0750 {} +
find "$release_dir" -type f -exec chmod 0640 {} +
chown -R dentweb:dentweb "$root/shared/storage" "$root/shared/server-only"
find "$root/shared/storage" "$root/shared/server-only" -type d -exec chmod 0750 {} +
find "$root/shared/storage" "$root/shared/server-only" -type f -exec chmod 0640 {} +
chmod 0640 "$root/shared/server-only/.env"

ln -sfn "releases/$release_sha" "$root/current.next"
mv -Tf "$root/current.next" "$root/current"

install -o root -g root -m 0644 "$bundle_dir/php-fpm-dentistry1402.conf" /etc/php/8.3/fpm/pool.d/dentistry1402.conf
install -o root -g root -m 0644 "$bundle_dir/nginx-dentistry1402.conf" /etc/nginx/sites-available/dentistry1402.conf
ln -sfn /etc/nginx/sites-available/dentistry1402.conf /etc/nginx/sites-enabled/dentistry1402.conf
install -o root -g root -m 0755 "$bundle_dir/session-clean.sh" /usr/local/lib/dentistry1402/session-clean
install -o root -g root -m 0644 "$bundle_dir/dentistry1402-session-clean.service" /etc/systemd/system/dentistry1402-session-clean.service
install -o root -g root -m 0644 "$bundle_dir/dentistry1402-session-clean.timer" /etc/systemd/system/dentistry1402-session-clean.timer
install -o root -g root -m 0755 "$bundle_dir/backup-runtime.sh" /usr/local/lib/dentistry1402/backup-runtime
install -o root -g root -m 0644 "$bundle_dir/dentistry1402-backup.service" /etc/systemd/system/dentistry1402-backup.service
install -o root -g root -m 0644 "$bundle_dir/dentistry1402-backup.timer" /etc/systemd/system/dentistry1402-backup.timer
install -o root -g root -m 0755 "$bundle_dir/backup_retention.py" /usr/local/lib/dentistry1402/backup-retention
install -o root -g root -m 0644 "$bundle_dir/dentistry1402-backup-retention.service" /etc/systemd/system/dentistry1402-backup-retention.service
install -o root -g root -m 0644 "$bundle_dir/dentistry1402-backup-retention.timer" /etc/systemd/system/dentistry1402-backup-retention.timer
install -o root -g root -m 0755 "$bundle_dir/restore-drill.sh" /usr/local/lib/dentistry1402/restore-drill
install -o root -g root -m 0644 "$bundle_dir/dentistry1402-restore-drill.service" /etc/systemd/system/dentistry1402-restore-drill.service
install -o root -g root -m 0644 "$bundle_dir/dentistry1402-restore-drill.timer" /etc/systemd/system/dentistry1402-restore-drill.timer
install -o root -g root -m 0755 "$bundle_dir/send-bale-database-backup.py" /usr/local/lib/dentistry1402/send-bale-database-backup
install -o root -g root -m 0644 "$bundle_dir/dentistry1402-bale-database-backup.service" /etc/systemd/system/dentistry1402-bale-database-backup.service
install -o root -g root -m 0644 "$bundle_dir/dentistry1402-bale-database-backup.timer" /etc/systemd/system/dentistry1402-bale-database-backup.timer
install -o root -g root -m 0755 "$bundle_dir/housekeeping.sh" /usr/local/lib/dentistry1402/housekeeping
install -o root -g root -m 0644 "$bundle_dir/dentistry1402-housekeeping.service" /etc/systemd/system/dentistry1402-housekeeping.service
install -o root -g root -m 0644 "$bundle_dir/dentistry1402-housekeeping.timer" /etc/systemd/system/dentistry1402-housekeeping.timer

php-fpm8.3 -t
nginx -t
systemctl daemon-reload
systemctl reload php8.3-fpm
systemctl reload nginx
/usr/local/lib/dentistry1402/backup-retention --migrate-legacy --migrate-only
systemctl enable --now dentistry1402-session-clean.timer dentistry1402-backup.timer dentistry1402-backup-retention.timer dentistry1402-restore-drill.timer dentistry1402-bale-database-backup.timer dentistry1402-housekeeping.timer
systemctl start dentistry1402-session-clean.service
paused_backup_timers=()
trap - EXIT

printf 'SITE_INSTALL_OK release=%s\n' "$release_sha"
