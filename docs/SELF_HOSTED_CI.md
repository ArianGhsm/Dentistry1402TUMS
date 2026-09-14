# Self-hosted CI runner

Deterministic GitHub Actions for this private repository run on the dedicated
repository runner labeled `dentistry-ci`. The runner is hosted on the Iran VPS,
but it is not part of the website or bot runtime.

## Security boundary

- The service runs as the unprivileged `ghrunner` account with no `sudo` access
  and no membership in website or bot service groups.
- Production storage, bot configuration, service secrets and deployment keys
  must remain unreadable and unwritable to `ghrunner`.
- The systemd unit uses a read-only system view and may write only its runner
  installation/work directories and private temporary directory.
- The runner cgroup is capped below one CPU core (`CPUQuota=75%`), uses low
  CPU/I/O scheduling weights, and has bounded memory/tasks so a test wave
  cannot starve the production website or bot services.
- Workflows use read-only repository permissions and checkout with
  `persist-credentials: false`.
- Repository workflows must never turn this runner into a deployment agent or
  read production state. Runtime, backup and deployment gates remain separate.

Only trusted repository code may target `[self-hosted, Linux, X64,
dentistry-ci]`. Changes to `.github/workflows/**` require the same review as
deployment scripts because a self-hosted job executes code on the VPS.

## Provisioned host dependencies

The host provides PHP CLI with `mbstring`, `openssl` and `curl`, plus `qpdf`.
Python 3.11 and Node 20 are provisioned per job by the official setup actions;
Python test packages are installed into the runner-owned tool environment.
Jobs must not use `sudo` or mutate global packages.

Foreign package downloads use the VPS's loopback-only managed egress proxy via
server-side systemd environment. Proxy configuration remains outside Git.

## Operations

The runner service name follows GitHub's generated
`actions.runner.ArianGhsm-Dentistry1402TUMS.*.service` convention.

Health checks:

```bash
systemctl is-active 'actions.runner.ArianGhsm-Dentistry1402TUMS.*.service'
systemctl show 'actions.runner.ArianGhsm-Dentistry1402TUMS.*.service' -p NRestarts
```

Runner upgrades must verify the official release checksum before extraction.
Removal requires stopping/uninstalling the service and removing the repository
runner registration; deleting its directory alone is not sufficient.
