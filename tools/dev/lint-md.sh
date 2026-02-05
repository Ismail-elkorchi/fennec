#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
IMAGE="davidanson/markdownlint-cli2:v0.20.0"
CONFIG=".markdownlint.yaml"
PATTERNS=(
  "docs/**/*.md"
  "README.md"
  "CONTRIBUTING.md"
  "SECURITY.md"
  "CODE_OF_CONDUCT.md"
)

if docker run --rm \
  --user "$(id -u):$(id -g)" \
  -v "${ROOT_DIR}:/work" \
  -w /work \
  "${IMAGE}" "${PATTERNS[@]}" --config "${CONFIG}"; then
  echo "Markdown lint passed."
else
  echo "Markdown lint failed." >&2
  exit 1
fi
