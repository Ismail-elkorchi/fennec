# Local Development (Ubuntu 25.10)

Fennec uses containerized toolchains for reliable local testing. You do not need host PHP or Go installed.
If Docker is already installed and you can run `docker` without sudo, skip the install step below.

## Install Docker Engine
Run the setup script using sudo only for fresh setups (development commands must not use sudo):

```bash
sudo ./tools/dev/setup-ubuntu-25.10-docker.sh
```

## Verify your environment
Run the doctor script:

```bash
./tools/dev/doctor.sh
```

## Run tests

```bash
make test
```

Optional minimum-version check:

```bash
make test-php84
```

## Quickstart (Agent MVP)
This is the canonical end-to-end loop for controller + DB + agent.

Start the dev controller and database:

```bash
make dev-up
make db-wait
make migrate
```

For faster lease-expiry proofs:

```bash
FENNEC_JOB_LEASE_SECONDS=10 make dev-up
```

Create an agent token and enqueue a noop job:

```bash
AGENT_NAME=local-agent make create-agent
make enqueue-noop
```

Run the agent once against the controller (replace the token from above):

```bash
FENNEC_CONTROLLER_URL="http://controller:8080" FENNEC_AGENT_TOKEN="1.<secret>" make agent-run-once
```

Heartbeat guidance: keep `FENNEC_HEARTBEAT_INTERVAL_SECONDS` at or below half of the lease time (default lease is 60s; heartbeat default is 20s).
CLI flags accept both `--flag value` and `--flag=value`.

When finished:

```bash
make dev-down
```

## Common problems
- Docker permission denied: run `sudo usermod -aG docker $USER` then `newgrp docker`.
- Docker Compose missing: rerun `sudo ./tools/dev/setup-ubuntu-25.10-docker.sh`.
- Composer install fails inside container: rerun `make test` (the container installs dependencies into `vendor/`).

## Notes
- If Docker requires sudo, fix group membership instead of running development commands with sudo.
- Rootless Docker is an alternative for development: https://docs.docker.com/engine/security/rootless/
