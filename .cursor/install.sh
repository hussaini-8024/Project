#!/usr/bin/env bash
# Idempotent bootstrap for the University Cyber Range cloud-agent environment.
# Installs every feature's OS packages, Python venv, and a production UI build.
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
chmod +x "$REPO_ROOT/scripts/install-ubuntu-deps.sh" "$REPO_ROOT/scripts/install-boot-services.sh"
"$REPO_ROOT/scripts/install-ubuntu-deps.sh"
"$REPO_ROOT/scripts/install-boot-services.sh"
echo "Cyber Range environment bootstrap complete."
