# API Governance

This directory is contract-first by default.
The OpenAPI file is the source of truth for UI, CLI, agent, and integrations.
Implementation should follow the contract, not the other way around.

## Versioning policy

- The API contract is versioned in the OpenAPI `info.version` field.
- Backward-incompatible changes require a new major version and an explicit release note.
- When a path prefix is introduced, it will follow `/v{major}` (for example, `/v1`).

## Linting and enforcement

- OpenAPI linting uses Spectral with a pinned Docker image.
- Markdown linting uses `markdownlint-cli2` with a pinned Docker image.
- Run locally:
  - `make lint-openapi`
  - `make lint-md`
  - `make lint`
- CI runs the same `make lint` target to keep the contract and docs consistent.
