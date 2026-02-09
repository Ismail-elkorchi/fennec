# Fennec Project Specification
Version: 0.1 (bootstrap)
Status: Draft (living document)

This spec defines the project boundaries, repo strategy, and startup constraints so implementation work can begin with minimal ambiguity.

---

## 1) Hard decisions (locked for the bootstrap)

### 1.1 Greenfield project (not a fork)
Fennec will be implemented as a new control panel from scratch.

Reasons:
- We are not targeting backward compatibility with HestiaCP behavior.
- We want freedom to redesign the architecture around a control-plane + agent boundary.
- Migration from HestiaCP will be handled by a separate tool.

Evidence from upstream (for context only):
- HestiaCP is based on VestaCP (legacy lineage).
  https://github.com/hestiacp/hestiacp
- HestiaCP code composition is primarily Shell + PHP + Smarty.
  https://github.com/hestiacp/hestiacp
- HestiaCP restricts redistribution under its name/logo (requires rebranding even for forks).
  https://github.com/hestiacp/hestiacp

Important constraint:
- The extracted hestiacp/ folder must be treated as a reference dataset only.
- Do not copy source files or blocks of code into Fennec repositories.
- It is acceptable to study behaviors, configuration patterns, and UX flows, then implement new code.

### 1.2 Multi-repo strategy
Fennec will use two repositories:

1) fennec
- Main control panel repository.
- Contains controller, agent, UI, docs, tooling in one repo for early velocity.

2) fennec-migrate
- Migration toolkit repository (HestiaCP -> Fennec).
- Must remain separate so migration constraints do not leak into the core design.

Optional follow-up repos (not part of bootstrap, decision by 2026-03-15):
- fennec-sdk (client libraries)
- fennec-docs (public site)

---

## 2) Scope and sequencing (bootstrap focus)
This document focuses on preparation only.

Bootstrap deliverables:
- repo(s) created
- baseline docs committed
- CI skeleton
- basic dev workflow documented
- placeholder architecture (directories + minimal “hello world” services if needed)

No production features are required in the bootstrap stage.

---

## 3) Target architecture (v1 direction)
### 3.1 Control plane + agent model
- Controller (unprivileged):
  - API server
  - auth/RBAC
  - job queue / scheduler
  - state store
  - audit log
- Agent (privileged on each node):
  - applies desired state
  - renders configs, validates, reloads services
  - reports status/metrics
  - enforces local safety rules

Communication:
- Default: controller <-> agent over mutually authenticated TLS.
- Single-node dev mode may use loopback.

### 3.2 State model
Fennec will have an explicit canonical state store (not scattered filesystem state).
- Accounts / Projects
- Nodes
- Domains
- Certificates
- DNS zones/records (if DNS is in scope)
- Databases and users (if DB is in scope)
- Backups
- Jobs
- Audit events

---

## 4) Platform support (bootstrap statement)
Bootstrap work must assume:
- Linux servers
- systemd-based distros

Exact support matrix will be finalized by 2026-03-15.
Avoid tying early code to distro-specific paths unless behind an abstraction layer.

---

## 5) Engineering standards (bootstrap)
### 5.1 Repo hygiene
- Always run formatters + linters in CI.
- Every PR must update docs if it changes behavior.
- Add “decisions” notes for any architectural change.

### 5.2 Security baseline
- No secrets in repo.
- Structured audit logs exist early.
- Any privileged action must be routed through the agent layer.

### 5.3 Observability baseline
- structured logs
- correlation IDs for requests/jobs
- minimal metrics endpoint

---

## 6) Repo layout (fennec repo)
Inside the fennec repository, use a monorepo-style layout:

fennec/
  docs/
  controller/
  agent/
  ui/
  pkg/                (shared libs)
  tools/              (dev scripts)
  .github/            (CI workflows)

Inside the fennec-migrate repository:
fennec-migrate/
  docs/
  src/
  fixtures/
  tools/

---

## 7) Initial documents to commit (bootstrap)
In fennec/docs/:
- manifesto.md  (from FENNEC_MANIFESTO.md)
- spec.md       (this file)
- decisions.md  (architecture decisions log)
- threat-model.md (initial threat notes)

At repo root:
- README.md
- LICENSE (choose before writing code)
- CONTRIBUTING.md
- SECURITY.md
- CODE_OF_CONDUCT.md

---

## 8) License (bootstrap requirement)
A license must be selected before meaningful implementation begins.

Placeholder options:
- GPL-3.0-or-later (compatible with future reuse patterns if needed)
- AGPL-3.0-or-later (strong network copyleft)

Decision to be recorded in docs/decisions.md before the first feature work.

---

## 9) Milestones (bootstrap only)
M0: Bootstrap
- Create repos and initial structure
- Commit docs
- Add CI skeleton
- Add dev workflow notes

M1: Architecture skeleton (next step after bootstrap)
- Controller process skeleton
- Agent skeleton
- Auth placeholder
- State store placeholder
- Minimal end-to-end request -> job -> agent noop execution

---

## 10) Vision references
- docs/vision/competitive-landscape.md
- docs/vision/north-star-metrics.md
