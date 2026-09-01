#!/usr/bin/env bash
# Install every OS package the Cyber Range features need, then build the UI.
# Does not start long-running servers — run scripts/run-ubuntu-lan.sh after this.
set -euo pipefail
export DEBIAN_FRONTEND=noninteractive
ROOT="$(cd "$(dirname "$0")/.." && pwd)"

need_node() {
  if ! command -v node >/dev/null 2>&1; then
    return 0
  fi
  if node -v 2>/dev/null | grep -qE '^v(1[8-9]|[2-9][0-9])'; then
    return 1
  fi
  return 0
}

echo "== 1/4 Enabling Ubuntu components and installing packages =="
if [ -f /etc/os-release ]; then
  # shellcheck disable=SC1091
  . /etc/os-release
fi
if command -v add-apt-repository >/dev/null 2>&1; then
  sudo add-apt-repository -y universe >/dev/null 2>&1 || true
  sudo add-apt-repository -y restricted >/dev/null 2>&1 || true
  sudo add-apt-repository -y multiverse >/dev/null 2>&1 || true
fi
sudo apt-get update -y
# Recommended extras (kmod, nftables) are required for live lab bridges — do not
# pass --no-install-recommends here.
sudo apt-get install -y \
  apt-utils ca-certificates curl git gnupg lsb-release xz-utils \
  software-properties-common \
  python3 python3-venv python3-pip python3-dev \
  build-essential libssl-dev libffi-dev pkg-config \
  libfreetype6-dev libjpeg-dev zlib1g-dev \
  nginx \
  iproute2 busybox bridge-utils iptables nftables kmod procps \
  iputils-ping uidmap \
  sudo ufw
# Ubuntu's nodejs/npm may be missing or too old; ignore failure and use NodeSource next.
sudo apt-get install -y nodejs npm || true

# nodejs+npm from Ubuntu may be missing or too old (12.x on 22.04). Install Node 20.
if need_node; then
  echo "== Installing Node.js 20 (needed to build the UI) =="
  if curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -; then
    sudo apt-get install -y nodejs || true
  fi
fi
if need_node; then
  echo "== NodeSource unavailable; installing official Node 20 binary =="
  NODE_VER="${NODE_VER:-v20.18.2}"
  tmp="$(mktemp -d)"
  curl -fsSL "https://nodejs.org/dist/${NODE_VER}/node-${NODE_VER}-linux-x64.tar.xz" \
    -o "$tmp/node.tar.xz"
  sudo tar -xJf "$tmp/node.tar.xz" -C /usr/local --strip-components=1
  rm -rf "$tmp"
  hash -r || true
fi
command -v node >/dev/null
command -v npm >/dev/null
node -v
npm -v

# Lab bridges: load kernel modules when they exist (ignore containers without them).
sudo modprobe bridge 2>/dev/null || true
sudo modprobe br_netfilter 2>/dev/null || true
sudo sysctl -w net.bridge.bridge-nf-call-iptables=0 >/dev/null 2>&1 || true
sudo sysctl -w net.bridge.bridge-nf-call-ip6tables=0 >/dev/null 2>&1 || true
sudo sysctl -w net.bridge.bridge-nf-call-arptables=0 >/dev/null 2>&1 || true

echo "== 2/4 Backend Python venv (API, AUKC PDF search, scheduler) =="
cd "$ROOT/backend"
if [ ! -x .venv/bin/python ]; then
  python3 -m venv .venv
fi
.venv/bin/pip install --upgrade pip
.venv/bin/pip install -r requirements.txt
mkdir -p data

echo "== 3/4 Frontend npm install + production build =="
cd "$ROOT/frontend"
if [ ! -d node_modules ]; then
  if [ -f package-lock.json ]; then
    npm ci --no-audit --no-fund
  else
    npm install --no-audit --no-fund
  fi
fi
npm run build

echo "== 4/4 Publish UI to /var/www/cyberrange for nginx =="
sudo mkdir -p /var/www/cyberrange
sudo rm -rf /var/www/cyberrange/*
sudo cp -a "$ROOT/frontend/dist/." /var/www/cyberrange/
sudo chown -R www-data:www-data /var/www/cyberrange 2>/dev/null \
  || sudo chown -R nginx:nginx /var/www/cyberrange 2>/dev/null \
  || true

echo "Dependency install complete. Next: ./scripts/run-ubuntu-lan.sh"
