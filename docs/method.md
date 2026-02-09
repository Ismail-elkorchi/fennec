# Decision Reassessment Protocol

This protocol keeps decisions explicit, evidence-backed, falsifiable, and time-bounded.

## Required format
All new ADR entries in `docs/decisions.md` must include these sections in this order:
- Context
- Decision
- Alternatives Considered
- Evidence
- Falsifiers
- Unknowns
- Review-By

Use `docs/decision-template.md` as the starting point.

## Evidence standards
- Prefer replayable steps (commands, scripts, or CI logs).
- Cite concrete repo paths (for example `composer.json`, `.github/workflows/ci.yml`).
- Avoid vague statements like "it works" without a test or source.

## Reassessment rules
- Every decision must include a `Review-By` date in `YYYY-MM-DD` format.
- The default review window is 90 days unless justified otherwise.
- On or before the review date, re-evaluate and either:
  - reaffirm the decision with updated evidence, or
  - supersede it with a new ADR and link the predecessor.

## Contradiction handling
If two decisions or documents conflict, add an entry to `docs/contradictions.md` and capture the evidence needed to resolve it.
