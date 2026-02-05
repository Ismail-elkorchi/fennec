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

## ADR-0004: Contract-First API + Lint Governance
Status: Accepted
Date: 2026-02-05

### Context
Fennec needs a stable, versioned API contract that the UI, CLI, and agent can trust.
We also need enforceable linting rules to keep the contract and documentation consistent in CI.
Tooling support for the most recent OpenAPI versions is still uneven.

### Decision
- Adopt a contract-first approach with a canonical OpenAPI document at `docs/api/openapi.yaml`.
- Use OpenAPI 3.1.0 for maximum tool compatibility today, with a planned upgrade to 3.1.2 or 3.2.x once ecosystem support is reliable.
- Standardize error responses on RFC 9457 Problem Details (`application/problem+json`).
- Define baseline security schemes in the contract: session cookie, bearer token, and mutual TLS for agents.
- Enforce linting via Docker-pinned tools:
  - Spectral (`stoplight/spectral:6.15.0`) for OpenAPI.
  - markdownlint-cli2 (`davidanson/markdownlint-cli2:v0.20.0`) for Markdown.
- CI runs `make lint` to ensure the contract and docs remain consistent.

### Alternatives Considered
- Framework-first implementation (Laravel/Symfony) without a contract-first spec.
- OpenAPI 3.0.x for broader compatibility, or OpenAPI 3.2.x for latest features.
- Ad hoc endpoint documentation instead of a single OpenAPI source of truth.
- Host-installed lint tooling instead of Docker-pinned, reproducible tools.

### Tradeoffs
- OpenAPI 3.1.0 is not the newest version, but it is reliably supported by current tooling.
- Docker-based linting adds image pull time but improves reproducibility and avoids host tool drift.
- Minimal stub endpoints do not cover full product scope but provide a testable contract baseline.

### Evidence
- `docs/api/openapi.yaml` defines the initial contract and problem+json schema.
- `tools/dev/lint-openapi.sh` and `tools/dev/lint-md.sh` run linting via pinned Docker images.
- `make lint` is wired to the lint scripts, and CI runs it.

### Falsifiers
- Spectral and ecosystem tools fully support OpenAPI 3.1.2 or 3.2.x without regressions.
- Contract-first slows delivery or causes frequent spec/implementation drift.
- RFC 9457 proves incompatible with operational needs or client requirements.

### Unknowns
- Whether agent mutual TLS will require additional OpenAPI tooling accommodations.
- Future versioning strategy once public clients are onboarded.

### Review-By
2026-05-05

---

## ADR-0005: Controller Persistence: Postgres 18.1 + PDO + SQL Migrations
Status: Accepted
Date: 2026-02-05

### Context
The controller needs a durable state store and a minimal, testable persistence layer.
We want predictable local development using Docker, with migration history tracked explicitly.
Authentication bootstrap requires a safe password hashing baseline.

### Decision
- Use PostgreSQL 18.1 (Debian trixie) in development via Docker compose profile `db`.
- Use PDO directly with strict error handling and prepared statements only.
- Maintain schema changes through ordered SQL migration files.
- Adopt RFC 9457 Problem Details for readiness errors and use `/readyz` to reflect DB availability.
- Use Argon2id for password hashing with configurable parameters and safe defaults aligned with OWASP guidance.

### Alternatives Considered
- SQLite for embedded local development.
- MySQL/MariaDB for wider hosting panel familiarity.
- File-based or JSON state for bootstrap simplicity.

### Tradeoffs
- PostgreSQL adds container overhead but provides the strongest baseline for correctness and concurrency.
- Raw SQL migrations keep control but require discipline in writing reversible migrations later.
- PDO avoids framework lock-in but leaves more manual query code to maintain.

### Evidence
- `compose.yaml` defines a pinned `postgres:18.1-trixie` profile service with health checks.
- `migrations/001_init.sql` establishes schema_migrations, users, and audit_events.
- `bin/fennec` provides a migration runner and admin bootstrap command.
- `/readyz` checks DB connectivity to separate readiness from liveness.
- OWASP Password Storage guidance recommends Argon2id for password hashing:
  https://cheatsheetseries.owasp.org/cheatsheets/Password_Storage_Cheat_Sheet.html

### Falsifiers
- PostgreSQL 18.1 becomes incompatible with deployment targets or introduces blocking issues.
- PDO becomes a bottleneck for safety or maintainability compared to a lightweight DBAL.
- Operational evidence shows a different engine is required for scaling or compatibility.

### Unknowns
- Long-term migration strategy for multi-node environments.
- Whether additional schema tooling is needed for safe rollbacks.

### Review-By
2026-05-05

---

## ADR-0006: Job Execution Spine: Postgres Queue + Agent Pull + Token Auth (mTLS Later)
Status: Accepted
Date: 2026-02-05

### Context
The controller needs a deterministic, auditable job queue for agent execution.
The agent must authenticate and claim work without a heavy message broker.
We need a minimal, testable workflow that supports concurrency and future hardening.

### Decision
- Use a Postgres-backed job queue with `SELECT ... FOR UPDATE SKIP LOCKED` to claim jobs.
- Add job leases with heartbeat and a requeue path for expired leases.
- Agents authenticate via bearer tokens in the format `<agent_id>.<secret>` for O(1) lookup.
- Token secrets are stored as Argon2id hashes.
- Agent-facing endpoints support claim and complete flows under `/agent/v1`.
- Job ownership conflicts return HTTP 409 to signal an invalid completion attempt.
- mTLS remains the long-term goal for agent authentication but is not implemented yet.

### Alternatives Considered
- External broker (Redis/RabbitMQ/SQS).
- Cron-based polling and filesystem queues.
- Controller-only execution without agents.
- Advisory locks per job.
- Long-lived transactions holding locks.

### Tradeoffs
- SKIP LOCKED provides queue-like behavior but can return an inconsistent view of the table.
- Leases add minimal complexity but require a requeue path to avoid stuck jobs.
- DB queues are simpler to operate but concentrate load on Postgres.
- Bearer tokens are easy to bootstrap but weaker than mTLS.

### Evidence
- Postgres SKIP LOCKED is intended for queue-like tables:
  https://www.postgresql.org/docs/current/sql-select.html
- SKIP LOCKED can return an inconsistent view and requires abandoned jobs to be handled explicitly:
  https://www.postgresql.org/docs/current/sql-select.html
- Example queue pattern with SKIP LOCKED:
  https://neon.com/guides/queue-system
- Problem Details standard:
  https://www.rfc-editor.org/rfc/rfc9457.html
- IANA media type for application/problem+json:
  https://www.iana.org/assignments/media-types/application/problem%2Bjson

### Falsifiers
- Postgres becomes a bottleneck or unavailable in target environments.
- Agents require stronger authentication guarantees before beta rollout.
- Operational evidence favors an external broker.

### Unknowns
- When to require idempotency keys for all job types.
- How to bind tokens to certs during the mTLS transition.

### Review-By
2026-05-05
