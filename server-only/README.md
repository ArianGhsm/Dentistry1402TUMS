# Runtime Data Boundary (`server-only`)

## File Identity
- What: Runtime storage boundary documentation.
- Where: `server-only/README.md`.
- Role: Prevents committing live runtime/secrets and defines expected layout.
- Controls: location of runtime data and env-path wiring.
- Dependencies:
  - `.gitignore`
  - runtime bootstrap env resolution
- Read when:
  - configuring new machine/host
  - changing runtime storage/session paths

## Rule
- Runtime data lives under `server-only/` and must never be committed.
- This directory is intentionally ignored except this `README.md` and `.gitignore`.

## Recommended Layout
```text
server-only/
  storage/
  tmp/
  sessions/
  backups/
  secrets/
```

## Notes
- Keep real data and credentials here, not in tracked root files.
- Point runtime using env vars when needed:
  - `DENT_SERVER_ONLY_ROOT`
  - `DENT_STORAGE_ROOT`
  - `DENT_SESSION_SAVE_PATH`
