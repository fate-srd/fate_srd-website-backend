#!/usr/bin/env bash
# Point the checkout's post-merge hook at scripts/post-merge.
#
# Run once on the server, from anywhere in the repository:
#   ./scripts/install-deploy-hook.sh

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

chmod +x scripts/deploy.sh scripts/post-merge
mkdir -p .git/hooks
ln -sfn ../../scripts/post-merge .git/hooks/post-merge

echo "Installed .git/hooks/post-merge -> scripts/post-merge"
echo "git pull will now run scripts/deploy.sh"
