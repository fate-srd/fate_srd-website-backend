#!/usr/bin/env bash
# Dump the live DreamHost database over SSH into .ddev/.downloads/db.sql.gz
# Used by: ddev pull live  /  scripts/download-db.sh

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="${ROOT_DIR}/scripts/.env.db"
DUMP_DIR="${ROOT_DIR}/.ddev/.downloads"
DUMP_FILE="${DUMP_DIR}/db.sql.gz"

if [[ ! -f "${ENV_FILE}" ]]; then
  echo "Missing ${ENV_FILE}" >&2
  echo "  cp scripts/.env.db.example scripts/.env.db" >&2
  echo "  chmod 600 scripts/.env.db" >&2
  exit 1
fi

# shellcheck disable=SC1090
set -a
source "${ENV_FILE}"
set +a

: "${SSH_HOST:?SSH_HOST is required in .env.db}"
: "${SSH_USER:?SSH_USER is required in .env.db}"
: "${DB_HOST:?DB_HOST is required in .env.db}"
: "${DB_PORT:?DB_PORT is required in .env.db}"
: "${DB_NAME:?DB_NAME is required in .env.db}"
: "${DB_USER:?DB_USER is required in .env.db}"
: "${DB_PASSWORD:?DB_PASSWORD is required in .env.db}"

if ! command -v ssh >/dev/null 2>&1; then
  echo "Required command not found: ssh" >&2
  exit 1
fi

env_mode="$(stat -f '%Lp' "${ENV_FILE}" 2>/dev/null || stat -c '%a' "${ENV_FILE}")"
if [[ "${env_mode}" != "600" && "${env_mode}" != "400" ]]; then
  chmod 600 "${ENV_FILE}"
fi

mkdir -p "${DUMP_DIR}"
chmod 700 "${DUMP_DIR}"

cleanup() {
  local exit_code=$?
  if [[ "${exit_code}" -ne 0 && -f "${DUMP_FILE}" ]]; then
    rm -f "${DUMP_FILE}"
  fi
}
trap cleanup EXIT

SSH_OPTS=(-o StrictHostKeyChecking=accept-new)
if [[ -n "${SSH_IDENTITY:-}" ]]; then
  SSH_IDENTITY="${SSH_IDENTITY/#\~/${HOME}}"
  SSH_OPTS+=(-i "${SSH_IDENTITY}")
fi

umask 077

echo "Dumping ${DB_NAME} on ${SSH_USER}@${SSH_HOST}..."
ssh "${SSH_OPTS[@]}" "${SSH_USER}@${SSH_HOST}" 'bash -s' <<REMOTE > "${DUMP_FILE}"
set -euo pipefail
umask 077

if ! command -v mysqldump >/dev/null 2>&1; then
  echo "mysqldump not found on remote host" >&2
  exit 1
fi

cnf="\$(mktemp "\${TMPDIR:-/tmp}/fate-my.cnf.XXXXXX")"
cleanup_remote() {
  rm -f "\${cnf}"
}
trap cleanup_remote EXIT

cat > "\${cnf}" <<'EOF'
[client]
host=${DB_HOST}
port=${DB_PORT}
user=${DB_USER}
password=${DB_PASSWORD}
EOF
chmod 600 "\${cnf}"

dump_opts=(
  --defaults-extra-file="\${cnf}"
  --single-transaction
  --quick
  --hex-blob
  --routines
  --triggers
  --no-tablespaces
)
if mysqldump --help 2>/dev/null | grep -q -- '--set-gtid-purged'; then
  dump_opts+=(--set-gtid-purged=OFF)
fi

mysqldump "\${dump_opts[@]}" '${DB_NAME}' | gzip -c
REMOTE

chmod 600 "${DUMP_FILE}"

if [[ ! -s "${DUMP_FILE}" ]]; then
  echo "Dump file is empty. Check SSH access and DB credentials." >&2
  exit 1
fi

echo "Saved dump: ${DUMP_FILE}"
ls -lh "${DUMP_FILE}"
