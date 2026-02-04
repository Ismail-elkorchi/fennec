# Fennec Stack (v0.1)

This document pins the baseline tech choices for Fennec.

## Baseline OS targets
- Debian 13 (stable)
- Ubuntu 24.04 LTS
- Ubuntu 26.04 LTS will be added after its release (scheduled 2026-04-23)

## Controller (API + WebUI)
- Language: PHP 8.5 (CI pins 8.5; local dev may override tooling as needed)
- Web server: NGINX stable
- Runtime: php-fpm
- UI: server-rendered HTML using pure PHP templates, minimal vanilla JS
- Background jobs: PHP CLI worker as a systemd service

## Agent
- Language: Go 1.25.x
- Runs privileged on nodes
- Pulls jobs from controller (long-poll), executes, reports results
- Controller <-> Agent transport: HTTP+JSON with mTLS

## State store + queue
- PostgreSQL 18.x
- DB-backed job queue (SKIP LOCKED pattern)
- No Redis requirement in v0.x

## Version policy
- Track latest supported stable releases.
- Patch/minor upgrades are adopted quickly.
- Major upgrades are planned, tested, then adopted without extended lag.
