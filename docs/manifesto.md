# Fennec Manifesto
Version: 0.1 (bootstrap)
Status: Draft (living document)

## What Fennec is
Fennec is a modern open-source server control plane built for 2026+ hosting needs.

Fennec is not a redesign of HestiaCP.
Fennec is a new system with a different internal architecture, a new data model, and a new operational approach.

Migration from HestiaCP will exist, but it will live in a separate project and must not constrain Fennec’s internal design.

## Why Fennec exists
Hosting operators need:
- safer defaults
- predictable changes
- clean automation interfaces
- reliable upgrades
- solid observability
- separation of privileges (UI must not be a root orchestrator)

Fennec exists to make those properties the baseline.

## Principles
### Security as a default
- No “web app as root” design.
- Clear separation between:
  - an unprivileged control plane (API/UI/CLI)
  - a privileged agent that performs system changes
- Strong authentication, RBAC, and audit logging from day one.
- Secrets are treated as first-class objects with rotation support.

### Predictable operations
- Idempotent actions: running an operation twice must not create drift.
- Explicit planning: support “plan/apply” workflows where possible.
- Safe writes: staged config generation, validation, then reload.
- Failure behavior is defined: errors must be actionable and leave the system consistent.

### Automation-first
- Stable API (versioned).
- CLI is a client of the same API.
- UI is a client of the same API.
- Everything a human can do can be done via API, with the same permission checks.

### Observability-first
- Structured logs
- metrics
- tracing hooks
- correlation IDs across requests and jobs
- audit log: who did what, when, from where, and what changed

### Extensible by design
- Pluggable service backends (web stack providers, DNS providers, mail providers).
- A clear plugin boundary is more important than “support everything” in core.

## Non-goals (at least for the initial releases)
- Backward compatibility with HestiaCP internals.
- Reusing HestiaCP’s scripts as the orchestration engine.
- Supporting every Linux distro and every service variant.
- Allowing manual edits inside generated configuration files as an official workflow.

## Product promises
Fennec will:
- be safe to run on real servers
- provide deterministic outcomes
- make automation straightforward
- avoid hidden state
- keep upgrades boring

## Open-source posture
- Public development
- Clear contribution rules
- Transparent security policy and disclosure process

## Terminology (initial)
- Control plane: API + UI + job scheduler, unprivileged.
- Agent: privileged executor on a node.
- Node: a server managed by Fennec.
- Project: a tenant boundary (accounts/domains/resources).
- Resource: a managed object (domain, certificate, database, mailbox, etc.)

## Roadmap (high-level)
- Phase 0: bootstrap repo(s), docs, CI, dev environment, core architecture skeleton.
- Phase 1: core resource model + job system + audit log + node agent handshake.
- Phase 2: web + TLS + DNS MVP.
- Phase 3: backups/restore + database management.
- Phase 4: mail stack (optional scope depending on goals).
- Phase 5: multi-node orchestration (controller coordinating multiple agents).

(Details live in PROJECT_SPEC.md)
