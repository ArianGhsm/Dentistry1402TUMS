# Bot runtime instructions

The repository-root `AGENTS.md` is authoritative. This directory is the
canonical Git source for Telegram/Bale runtime code previously kept in the
unversioned `IntegratedDent1402Tums` sibling.

- Production still runs release copies under `/opt/integrated-dent`; service paths do not follow the laptop checkout.
- Never add live `.env`, tokens, sessions, runtime databases/JSON state, logs, caches, PIDs, locks, generated output, backups, snapshots, or private keys.
- Runtime source changes use targeted tests here, then integration/runtime gates from `docs/DEVELOPMENT_WORKFLOW.md`.
- Central router, scheduler, site API client, service units, deployment scripts, and dependency manifests are integration-only hotspots.
