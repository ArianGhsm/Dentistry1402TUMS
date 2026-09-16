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

## Recovery verification

`backup-runtime.sh` proves that a snapshot is internally readable; `restore-drill.sh`
proves that the latest snapshot can be reconstructed into a runnable isolated
environment. The monthly drill runs only on loopback, validates the archive and
internal checksum manifest, parses every JSON store, checks every SQLite database,
reconstructs the production ownership contract, opens databases as the real bot
service users, loads the PHP auth store from restored paths, and boots a temporary
PHP server against restored mutable state. It must leave the live site symlink and
Telegram/Bale process IDs unchanged. Sanitized reports are retained under
`/var/backups/dentistry1402-runtime/restore-drills` (24 newest reports).

The runtime backup intentionally stores mutable state and configuration rather than
GitHub code. `metadata/runtime-pointers.txt` links a snapshot to its immutable code
releases. SQLite `.backup` copies are root-owned inside the archive, so a real
restore must explicitly reconstruct `dentbot:dentbot`, `dentbale:dentbale`, and
`root:dentcommerce` ownership before services are started. The restore drill fails
closed if an unrecognized SQLite database appears without an ownership recipe.
