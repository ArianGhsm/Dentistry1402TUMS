# Runtime source migration inventory

The unversioned `IntegratedDent1402Tums` sibling was inventoried before import.
It is not treated as another repository and was not modified.

## Imported into `bot_runtime/`

- A — source: `dent_bot/`, `deploy_notifier/`, operational scripts.
- B — tests: deterministic Python/runtime tests.
- C — docs: runtime architecture, runbooks and handoffs.
- D — non-secret deploy/service configuration: systemd templates, setup/deploy scripts, example environment files and dependency manifests.

The allowlisted import contained 161 files and approximately 1.99 MB. Runtime
paths are root-relative and production releases remain under
`/opt/integrated-dent`, so canonicalizing source does not move live services.

## Deliberately excluded from Git

- E — runtime data: SQLite/JSON state, sessions, PID/lock files.
- F — sensitive configuration: real `.env`, tokens, credentials, SSH metadata/private paths, protected identity/session material.
- G — generated material: logs, caches, output PDFs/reports, benchmark JSON, downloaded binaries, snapshots and backups.

At audit time excluded material included the sibling `.codex-local/`,
`backups/`, and `output/` trees. Values were not copied or reported.

## Operational compatibility

The sibling remains temporarily as a server-only state/config location for
laptop operational scripts. Canonical scripts are executed from this
repository and may be given an absolute server-config path to that ignored
state. This is a documented boundary, not dual source ownership. Relocating
the protected state later requires a verified secret/backup migration; no
source restructuring or reverse migration is required.
