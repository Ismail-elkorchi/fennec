# Agent Jobs Flow

This document describes the controller-agent job execution spine.

## Trust boundary

- The controller is unprivileged and issues work items.
- The agent runs with elevated privileges on the host.
- Agents authenticate to the controller using a bearer token today, with mTLS planned.

## Claim and complete flow

1. Agent calls `POST /agent/v1/jobs/claim` with its bearer token.
2. Controller selects the next queued job using `SELECT ... FOR UPDATE SKIP LOCKED`.
3. Controller returns the job and marks it `running`.
4. Agent executes the job and calls `POST /agent/v1/jobs/{id}/complete`.
5. Controller marks the job `succeeded` or `failed` and stores the result or error.

## Idempotency stance

- The queue provides at-least-once delivery.
- Jobs must be safe to retry.
- Future work will add idempotency keys on jobs and handlers.

## Future direction

- Replace or bind bearer tokens to client certificates (mTLS).
- Add explicit job timeouts and retry backoff.
