# North-Star Metrics (2026+)

These metrics define measurable outcomes for the "best control panel in the world" goal.
Targets are intentionally ambitious but should be testable and repeatable.

## Delivery metrics

- Install time to first HTTPS site: <= 15 minutes on a fresh Ubuntu LTS VM using the official guide (measured end-to-end, including DNS challenge or self-signed fallback).
- Time to rollback a config change: <= 60 seconds for single-node, <= 3 minutes for multi-node (from rollback command to service health OK).
- Percentage of operations that are idempotent: >= 95% for production-facing operations; all non-idempotent ops must be explicitly flagged.

## Reliability and correctness

- No-downtime reload guarantees where supported: >= 99% of config applies use graceful reloads for services that support it; restarts require explicit operator confirmation.
- Test coverage for critical subsystems must meet these thresholds.
- Controller policy/RBAC logic: >= 90% line coverage.
- Agent config renderers + validators: >= 95% line coverage.
- Audit log pipeline: >= 90% line coverage and 100% event schema validation coverage.

## Security baselines

- mTLS agent authentication: 100% of agent connections must present a valid client cert; no shared secrets.
- RBAC enforcement: 100% of mutating endpoints require explicit role checks; no implicit admin fallbacks.
- Audit trail completeness: 100% of mutating actions emit an audit event with actor, target, diff/summary, and timestamp.

## Measurement notes

- Benchmarks must be runnable from CI and a local dev machine.
- Metrics should be tracked per release and compared over time.
- A failed metric is a release blocker for the next major version.

## Anti-goals

- We will not ship features that require root-level UI access in the controller.
- We will not trade auditability for convenience in core workflows.
- We will not adopt a PaaS-style abstraction that hides server ownership.
- We will not accept non-deterministic config state as "good enough" in production.
