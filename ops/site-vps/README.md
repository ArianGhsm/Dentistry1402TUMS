# VPS website runtime

The production website is isolated under `/srv/dentistry1402` while the existing
Telegram and Bale services remain under `/opt/integrated-dent`.

- `releases/<git-sha>` contains immutable GitHub code.
- `current` is an atomic symlink to the active release.
- `shared/storage` is the canonical production data store.
- `shared/server-only` contains sessions, secrets, temporary files, and `.env`.
- `shared/tls` and `shared/backups` are private runtime paths and never enter Git.

Install the FPM pool and Nginx virtual host only after backing up the current VPS
configuration. Validate with `php-fpm8.3 -t` and `nginx -t` before a controlled
reload. A release must be checked out from an exact `origin/main` SHA; deploys
must never copy local dirty source or overwrite `shared`.

The checked-in Nginx configuration mirrors the legacy Apache redirects and denies
dotfiles, storage, secrets, logs, database files, and backups. The ordinary PHP
upload limit is intentionally bounded for this VPS; large content uses the
application's dedicated download-host streaming path.

TLS is issued independently on the VPS by Certbot for the apex, `www`, and the
temporary cutover bridge hostname. The deploy hook validates and reloads Nginx
after renewal. `logrotate-dentistry1402` bounds the site-specific Nginx and PHP
logs without changing bot log retention.

Rollback is code/data separated: point `current` to the prior exact-SHA release
for code rollback; swap `shared/storage.pre-vps-cutover` back only when a verified
data-corruption incident requires it. DNS rollback changes only the apex A record
to the former host. Never restore old data merely because code was rolled back.
