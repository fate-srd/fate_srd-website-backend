#!/usr/bin/env bash
# Download the live database into DDEV via the "live" provider.
#
# Credentials: scripts/.env.db (gitignored)
# Provider:    .ddev/providers/live.yaml
#
# Usage:
#   ./scripts/download-db.sh
#   ddev pull live --skip-files -y

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

if ! command -v ddev >/dev/null 2>&1; then
  echo "ddev is required on PATH" >&2
  exit 1
fi

if [[ ! -f "${ROOT_DIR}/scripts/.env.db" ]]; then
  echo "Missing scripts/.env.db" >&2
  echo "  cp scripts/.env.db.example scripts/.env.db" >&2
  echo "  chmod 600 scripts/.env.db" >&2
  exit 1
fi

exec ddev pull live --skip-files -y
