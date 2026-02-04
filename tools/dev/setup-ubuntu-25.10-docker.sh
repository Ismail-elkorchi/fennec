#!/usr/bin/env bash
set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
  echo "Please run as root (e.g., sudo $0)."
  exit 1
fi

echo "Removing conflicting packages (if present)..."
for pkg in docker.io docker-doc docker-compose docker-compose-v2 podman-docker containerd runc; do
  apt-get remove -y "$pkg" >/dev/null 2>&1 || true
done

echo "Installing prerequisites..."
apt-get update
apt-get install -y ca-certificates curl gnupg

install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
chmod a+r /etc/apt/keyrings/docker.asc

. /etc/os-release
arch=$(dpkg --print-architecture)
cat > /etc/apt/sources.list.d/docker.sources <<EOF_SOURCES
Types: deb
URIs: https://download.docker.com/linux/ubuntu
Suites: ${VERSION_CODENAME}
Components: stable
Architectures: ${arch}
Signed-By: /etc/apt/keyrings/docker.asc
EOF_SOURCES

echo "Installing Docker Engine..."
apt-get update
apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

docker --version
docker compose version

if docker run --rm hello-world >/dev/null 2>&1; then
  echo "Docker hello-world succeeded."
else
  echo "Docker hello-world failed. You may need to add your user to the docker group and re-login."
fi

user="${SUDO_USER:-}"
if [ -z "$user" ]; then
  user="$(logname 2>/dev/null || true)"
fi

echo "Next steps for non-root usage:"
if [ -n "$user" ]; then
  echo "  sudo usermod -aG docker $user"
else
  echo "  sudo usermod -aG docker <your-user>"
fi
echo "  newgrp docker"
