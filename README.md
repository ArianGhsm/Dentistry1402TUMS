# Dentistry1402TUMS

Canonical repository for the Dentistry 1402 TUMS website, shared contracts, and
Telegram/Bale runtime source.

## Sources of truth

- Code: an exact commit in `ArianGhsm/Dentistry1402TUMS`.
- Runtime data: production `storage/` and service-owned state.
- Recovery copies: verified, non-Git laptop/server-only mirrors.

Start with [AGENTS.md](AGENTS.md), [CONTRIBUTING.md](CONTRIBUTING.md), and
[DEPLOY.md](DEPLOY.md). Parallel development and release rules are in
[docs/DEVELOPMENT_WORKFLOW.md](docs/DEVELOPMENT_WORKFLOW.md).
Historical migration evidence is retained under
[docs/archive/WORKFLOW_MIGRATION_AUDIT.md](docs/archive/WORKFLOW_MIGRATION_AUDIT.md) and is non-normative.

`bot_runtime/` contains source, tests, documentation, and non-secret service
templates only. It never contains live tokens, sessions, databases, logs,
backups, caches, PIDs, or production snapshots.
