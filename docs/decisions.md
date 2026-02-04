# Architecture Decisions

This log records significant architectural decisions for the Fennec project.

Format:
- ID: ADR-XXXX
- Status: Proposed | Accepted | Superseded
- Date: YYYY-MM-DD
- Context, Decision, Consequences

---

## ADR-0001: License Selection
Status: Proposed
Date: 2026-02-04

Context:
A license must be selected before meaningful implementation begins. The current LICENSE file is a placeholder using GPL-3 text.

Decision:
TBD.

Consequences:
- TODO: Confirm final license choice and update LICENSE accordingly.

---

## ADR-0002: Fennec Technology Stack (v0.1)

Date: 2026-02-04
Status: Accepted

Decisions:
- Controller (API + WebUI) is written in PHP 8.5, with pure PHP server-rendered UI. CI pins PHP 8.5.
- Agent is written in Go 1.25.x and is the only privileged component.
- Controller <-> Agent uses HTTP+JSON with mTLS; agent pulls jobs.
- PostgreSQL 18.x is the primary state store.
- Jobs queue is DB-backed in PostgreSQL.
- NGINX stable is the reverse proxy.

Notes:
- Migration tooling lives in a separate repository and uses Go 1.25.x as a single-binary CLI.
