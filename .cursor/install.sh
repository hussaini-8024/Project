#!/usr/bin/env bash
# Idempotent bootstrap for the University Cyber Range cloud-agent environment.
# Installs the system packages required for LIVE guest provisioning
# (network namespaces + Linux bridge + veth via `ip`, plus busybox for the
# Alpine guest shell) and prepares the backend/venv and frontend builds.
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_ROOT"

# --- System packages (need sudo; the agent user has passwordless sudo) ---
# iproute2  -> the `ip` command (netns/link/addr) the runtime shells out to
# busybox   -> shared applet binary used inside each Alpine guest
# bridge-utils, iptables -> per-lab bridge management and filter control
# python3-venv/pip -> backend virtualenv
if command -v sudo >/dev/null 2>&1; then
  sudo apt-get update -y
    sudo DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
      iproute2 busybox bridge-utils iptables python3-venv python3-pip nginx curl
fi

# --- Backend: virtualenv + dependencies ---
cd "$REPO_ROOT/backend"
if [ ! -x .venv/bin/python ]; then
  python3 -m venv .venv
fi
.venv/bin/pip install --upgrade pip
.venv/bin/pip install -r requirements.txt

# --- Frontend: dependencies + production build ---
cd "$REPO_ROOT/frontend"
if [ -f package-lock.json ]; then
  npm ci
else
  npm install
fi
npm run build

echo "Cyber Range environment bootstrap complete."
