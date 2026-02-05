#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
IMAGE="stoplight/spectral:6.15.0"
RULESET=".spectral.yaml"
SPEC_PATH="docs/api/openapi.yaml"

if docker run --rm \
  --user "$(id -u):$(id -g)" \
  -v "${ROOT_DIR}:/work" \
  -w /work \
  "${IMAGE}" lint -r "${RULESET}" "${SPEC_PATH}"; then
  echo "OpenAPI lint passed."
else
  echo "OpenAPI lint failed." >&2
  exit 1
fi
