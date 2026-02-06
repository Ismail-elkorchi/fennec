# Local Development (Ubuntu 25.10)

Fennec uses containerized toolchains for reliable local testing. You do not need host PHP or Go installed.
If Docker is already installed and you can run `docker` without sudo, skip the install step below.

## Install Docker Engine
Run the setup script using sudo (only for fresh setups):

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

Create an agent token and enqueue a noop job:

```bash
COMPOSE_PROFILES=db docker compose run --rm --user $(id -u):$(id -g) \
  -e HOME=/tmp -e COMPOSER_CACHE_DIR=/tmp/composer-cache \
  -e FENNEC_DB_DSN="pgsql:host=db;port=5432;dbname=fennec" \
  -e FENNEC_DB_USER=fennec -e FENNEC_DB_PASSWORD=fennec-dev \
  php85 php bin/fennec create-agent --name=local-agent

COMPOSE_PROFILES=db docker compose run --rm --user $(id -u):$(id -g) \
  -e HOME=/tmp -e COMPOSER_CACHE_DIR=/tmp/composer-cache \
  -e FENNEC_DB_DSN="pgsql:host=db;port=5432;dbname=fennec" \
  -e FENNEC_DB_USER=fennec -e FENNEC_DB_PASSWORD=fennec-dev \
  php85 php bin/fennec enqueue-noop
```

Run the agent once against the controller (replace the token from above):

```bash
FENNEC_CONTROLLER_URL="http://controller:8080" FENNEC_AGENT_TOKEN="1.<secret>" make agent-run-once
```

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
