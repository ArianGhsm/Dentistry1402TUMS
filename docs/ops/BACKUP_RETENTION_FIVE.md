# Backup retention: five complete sets per project

## Goal and scope

Dentistry retains at most five newest complete backup sets in total across its
verified runtime archives, site-data snapshots, database-only Bale packages,
and explicitly recognized dated recovery artifacts. The count is shared among
these families, not applied separately to each directory. Unknown files,
incomplete sets, restore-drill reports and stale workspaces are outside the
deletion matcher. Project backup paths remain isolated; this helper cannot
write other projects' backup directories. Restic/Arvan is managed separately
and is not in this code path.

## Source-of-truth layout

The durable project-owned backup tree is
`/var/backups/dentistry1402/{runtime,site-data,bale-database}`. Restore-drill
reports live under the same tree but do not count as backup sets. Dated manual
recovery artifacts are recognized only under
`/srv/dentistry1402/shared/server-only/backups`.

The retention helper checks archive SHA-256 sidecars and site-data manifests
before moving or deleting complete automatic sets. It accepts the exact
absolute checksum paths written by the previous site deployer and basename
entries from the new writer. It preserves the newest available set from each
family before filling the remaining slots by timestamp. Moves from legacy
paths use same-filesystem rename, not a duplicate copy. Incomplete sets and
unknown files remain in place; invalid calendar timestamps are ignored without
blocking pruning of valid sets. The helper uses an exclusive lock so scheduled
cleanup and deployment migration cannot race. Restore drills acquire the same
lock before selecting an archive and hold it through verification, so retention
cannot remove the selected archive mid-drill. The deployer installs the new
backup, restore and Bale consumers before migrating archive paths; its
migration-only call does not prune protected `shared/server-only` data. A
separate systemd timer starts its first hourly run one hour after activation,
and the runtime and Bale backup services trigger retention after a verified
backup is created.

## Implementation state

Source work is on `backup-retention-five`, based on the recorded
`origin/main` SHA `2590672c6218b647b830a698024415511cae4002`. Production has not
been changed from this feature branch. The first rollout must pass GitHub CI
and review, then use the exact merged `origin/main` SHA through
`scripts/run_release_gate.ps1` / `scripts/deploy_site_vps.ps1`.

The release gate pauses the Dentistry runtime, Bale, retention and restore-drill
timers and checks that none of their services is active before creating and
verifying a new site-data recovery snapshot. It installs the replacement
retention executable only after that snapshot is verified, then installs the
other backup consumers and migrates complete legacy snapshots in place. The
deployer does not prune recovery artifacts under `shared/server-only`; the
separate retention service applies the five-set cap after the release gate has
completed. Its exit handler restarts timers that were active before a failed
release. The installer performs the same idempotent migration for bootstrap
installs and restores previously active backup timers if it exits unsuccessfully.
No operation writes into `/srv/dentistry1402/current` directly.

## Validation recorded for this branch

- Targeted retention, Bale sender, restore-drill, housekeeping and deployer
  contract suite: 21 passed.
- `scripts/test_vps_site_deploy_contract.py`: passed.
- `scripts/check_instruction_contracts.py`: passed.
- `scripts/check_repository_hygiene.py`: passed.
- `scripts/test_release_source_contract.py`: 5 passed.
- `git diff --check`: passed.
- Full bot runtime test collection was attempted on Windows; 453 tests passed,
  while two environment-specific tests failed because `qpdf` is unavailable
  and a SQLite file remained locked during Windows temporary-directory cleanup.
  GitHub CI on its Linux runner remains the full regression gate.

## Remaining work

1. Review the branch and complete GitHub CI.
2. Merge only after the repository's required review and divergence checks.
3. Release the exact merged SHA through the canonical gate.
4. Verify the retention timer, backup services, live site/runtime health, and
   that only five complete Dentistry backup sets remain.
5. Record the release SHA, report ID, migration/prune counts and live checks in
   the access-controlled server operations record.
