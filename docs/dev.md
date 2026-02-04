# Local Development (Ubuntu 25.10)

Fennec uses containerized toolchains for reliable local testing. You do not need host PHP or Go installed.

## Install Docker Engine
Run the setup script using sudo:

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

## Common problems
- Docker permission denied: run `sudo usermod -aG docker $USER` then `newgrp docker`.
- Docker Compose missing: rerun `sudo ./tools/dev/setup-ubuntu-25.10-docker.sh`.
- Composer install fails inside container: rerun `make test` (the container installs dependencies into `vendor/`).
