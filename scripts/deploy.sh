#!/usr/bin/env bash
# Install dependencies and run Drupal database updates.
#
# Git runs this after a successful `git pull` when scripts/post-merge is
# installed as .git/hooks/post-merge. It can also be run directly:
#
#   ./scripts/deploy.sh

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

# SSH login uses PHP 8.3 via ~/.bashrc. Git hooks do not, so set it here.
export PATH="/usr/local/php83/bin:${HOME}/bin:${PATH}"
export COMPOSER_MEMORY_LIMIT="${COMPOSER_MEMORY_LIMIT:--1}"

if ! command -v composer >/dev/null 2>&1; then
  echo "composer not found on PATH" >&2
  exit 1
fi

if ! command -v php >/dev/null 2>&1; then
  echo "php not found on PATH" >&2
  exit 1
fi

echo "Installing Composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

echo "Running Drupal deploy..."
vendor/bin/drush deploy -y
