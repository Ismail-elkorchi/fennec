# Architecture Decisions

This log records significant architectural decisions for the Fennec project.
See `docs/method.md` and `docs/decision-template.md` for the required format.

---

## ADR-0001: License Selection
Status: Proposed
Date: 2026-02-04

### Context
A license must be selected before meaningful implementation begins. The current `LICENSE` file is a placeholder using GPL-3 text.

### Decision
TBD.

### Alternatives Considered
- MIT
- Apache-2.0
- GPL-3.0

### Evidence
- `LICENSE` currently contains placeholder GPL-3 text.

### Falsifiers
- A final license is chosen and the `LICENSE` file is updated accordingly.

### Unknowns
- Final license selection and its downstream distribution implications.

### Review-By
2026-05-05

---

## ADR-0003: Dev/Test Environment via Docker on Ubuntu 25.10
Status: Accepted
Date: 2026-02-04

### Context
Local tests must be reproducible on Ubuntu 25.10 without relying on host PHP/Go packages. The project baseline is Debian 13 (trixie) as the latest stable, but the prior dev container base used Debian 12 (bookworm), which conflicts with the latest stable/LTS alignment goal.

### Decision
- Use Docker Engine from the official Docker apt repository for local dev/testing on Ubuntu 25.10.
- Run PHP tests in a container built from `php:${PHP_VERSION}-cli-trixie`.
- Run Go tests in `golang:1.25.6-trixie`.
- Use `compose.yaml` and Makefile targets to run tests via `docker compose run --rm`.
- If Docker requires sudo, use `sudo docker` and `sudo make test` until group membership is configured.

### Alternatives Considered
- Host toolchains for PHP and Go (rejected due to OS package availability and reproducibility concerns).
- Rootless Docker (kept as an option to revisit once baseline setup is stable).
- Podman (not selected to minimize divergence from CI/official Docker docs).

### Evidence
- `docker --version` returns `Docker version 29.2.1, build a5c7197`.
- `docker compose version` returns `Docker Compose version v5.0.2`.
- Makefile test targets run containers as the host user via `--user $(UID):$(GID)` and set `HOME=/tmp` and `COMPOSER_CACHE_DIR=/tmp/composer-cache` for deterministic, non-root Composer usage.
- User-owned artifacts confirmed:
  - `stat -c '%u:%g %n' composer.lock` -> `1000:1000 composer.lock`.
  - `stat -c '%u:%g %n' vendor` -> `1000:1000 vendor`.
- Reproduce: `make test`, `make test-php84`.

### Falsifiers
- Docker becomes unavailable or unsupported on the target OS.
- Required images no longer provide Debian 13 (trixie) variants.
- Security policy mandates rootless-only containers for development.

### Unknowns
- Confirm `php:${PHP_VERSION}-cli-trixie` availability for both 8.4 and 8.5 in the official image list.
- Whether rootless Docker should be required for developers (pending a separate review).

### Review-By
2026-05-05

---

## ADR-0002: Fennec Technology Stack (v0.1)
Status: Accepted
Date: 2026-02-04

### Context
Fennec needs a small, reliable control plane with a privileged agent. The WebUI must remain pure PHP and compatible with PHP 8.4+. Earlier notes assumed PHP 8.5 as the minimum, which conflicts with the project baseline.

### Decision
- Controller (API + WebUI) is written in PHP >= 8.4, with pure PHP server-rendered UI. Primary tested version is PHP 8.5.
- Agent is written in Go 1.25.x and is the only privileged component.
- Controller <-> Agent uses HTTP+JSON with mTLS; agent pulls jobs.
- PostgreSQL 18.x is the primary state store.
- Jobs queue is DB-backed in PostgreSQL.
- NGINX stable is the reverse proxy.
- Migration tooling lives in a separate repository and uses Go 1.25.x as a single-binary CLI.

### Alternatives Considered
- Controller in Go or Node.js instead of PHP.
- Agent in Rust instead of Go.
- Redis-backed job queue instead of PostgreSQL SKIP LOCKED.

### Evidence
- `composer.json` requires PHP >= 8.4.
- CI is pinned to PHP 8.5 as the primary tested version.
- `agent/go.mod` specifies Go 1.25.x.

### Falsifiers
- PHP 8.4 becomes unsupported for required dependencies.
- Go 1.25.x becomes unavailable or incompatible with target OSes.
- Operational needs demand a dedicated queue or different proxy.

### Unknowns
- Final throughput targets and sizing requirements.
- Whether future UI needs exceed pure PHP templates.

### Review-By
2026-05-05
