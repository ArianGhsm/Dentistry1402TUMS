# Workflow migration audit — 2026-09-07

## Pre-migration baseline

- Required repository: `ArianGhsm/Dentistry1402TUMS` (remote verified).
- Original local HEAD: `9901d5354bccb2d72933e612d9c4f7a1b6c8cfe3`.
- Fetched `origin/main`: `e78fc96cd975c1275da5a65a5240d616f5840e10`.
- Last website release record: `20260907-195131`, completed at
  `2026-09-07T19:52:55+03:30`.
- The original branch was 159 commits ahead and 229 behind with a large dirty
  tree. It was preserved untouched. Migration was developed in a clean
  worktree based on `origin/main`.

## Divergence finding

The legacy pipeline deployed a dirty local tree and subsequently replayed its
code delta into a temporary upstream worktree for a GitHub push. It did not
advance the original checkout's HEAD. Consequently its recorded deploy HEAD
could be stale even when deployed files and the pushed commit represented the
same behavior.

The last host manifest contains 1,430 files and matches the old deployment
workspace byte-for-byte. Against the fetched GitHub tree, 93 existing files
differed only in line ending/blank whitespace (zero non-whitespace mismatch).
Three deployed PDF.js files were ignored dependencies and one deployed file
was a Python cache artifact. Four retired `private_notes_*` files were present
in GitHub but absent from production and contradicted the current architecture.

Resolution:

- PDF.js source and license are now tracked.
- the Python cache remains excluded;
- retired, undeployed private-notes source is removed;
- runtime generated/secret/state files remain excluded;
- future deploys compare the full exact-SHA tree with the verified host
  manifest and record the exact GitHub SHA;
- deploy no longer creates/pushes commits or mutates version stamps.

The formatting-only byte differences are transparent legacy evidence. A future
integrated release may normalize those bytes through the reviewed manifest
delta, but this workflow-only migration does not perform a no-behavior-change
bulk production upload.

## Data and backup safety

Production storage remains canonical. Existing one-way host-to-laptop mirrors,
schema/hash/size checks, critical-store double reads, verified-only `latest`
promotion, rollback material and fail-closed persistence remain release gates.
No production data or protected local state was copied into Git.
