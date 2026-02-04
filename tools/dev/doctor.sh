#!/usr/bin/env bash
set -euo pipefail

fail=0

printf "Doctor report\n"

if command -v docker >/dev/null 2>&1; then
  echo "Docker: $(docker --version)"
  if docker compose version >/dev/null 2>&1; then
    echo "Docker Compose: $(docker compose version)"
  else
    echo "Docker Compose: MISSING"
    echo "Fix: sudo ./tools/dev/setup-ubuntu-25.10-docker.sh"
    fail=1
  fi

  if docker info >/dev/null 2>&1; then
    echo "Docker permissions: OK"
  else
    echo "Docker permissions: WARN (cannot access daemon without sudo)"
    echo "Fix: sudo usermod -aG docker $USER"
    echo "     newgrp docker"
    fail=1
  fi
else
  echo "Docker: MISSING"
  echo "Fix: sudo ./tools/dev/setup-ubuntu-25.10-docker.sh"
  fail=1
fi

if command -v git >/dev/null 2>&1; then
  echo "Git: $(git --version)"
else
  echo "Git: MISSING"
  echo "Fix: sudo apt-get install -y git"
  fail=1
fi

if [ "$fail" -ne 0 ]; then
  echo "Doctor status: issues detected"
  exit 1
fi

echo "Doctor status: OK"
