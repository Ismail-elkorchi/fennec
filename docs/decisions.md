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
