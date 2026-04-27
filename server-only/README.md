# Runtime data lives here (server-only) and must never be committed.
# This directory is intentionally ignored except this README and .gitignore.

Recommended layout:
server-only/
  storage/
  tmp/
  sessions/
  backups/
  secrets/

Notes:
- Keep real data and credentials here, not under repo root tracked files.
- Point runtime through env vars when needed:
  - DENT_SERVER_ONLY_ROOT
  - DENT_STORAGE_ROOT
  - DENT_SESSION_SAVE_PATH

