#!/usr/bin/env bash
# Start the Cyber Range on this Ubuntu host, bound to all interfaces.
# Other PCs on the LAN open: http://172.26.1.3:5173/login
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
HOST_IP="${PUBLIC_HOST:-172.26.1.3}"
export PUBLIC_HOST="$HOST_IP"
export CORS_ALLOW_LAN=true
export COOKIE_SECURE=false

echo "University Cyber Range — LAN bind 0.0.0.0, advertised IP ${HOST_IP}"

if ! command -v python3 >/dev/null; then
  echo "Install Python 3.12+: sudo apt install -y python3 python3-venv python3-pip" >&2
  exit 1
fi
if ! command -v node >/dev/null; then
  echo "Install Node 20+: see https://nodejs.org" >&2
  exit 1
fi
if ! command -v ip >/dev/null; then
  sudo apt-get install -y iproute2 busybox bridge-utils iptables python3-venv || true
fi

cd "$ROOT/backend"
if [ ! -x .venv/bin/uvicorn ]; then
  python3 -m venv .venv
  .venv/bin/pip install -q --upgrade pip
  .venv/bin/pip install -q -r requirements.txt
fi
mkdir -p data

cd "$ROOT/frontend"
if [ ! -d node_modules ]; then
  npm install --no-audit --no-fund
fi
if [ ! -d dist ]; then
  npm run build
fi

echo
echo "Open on this Ubuntu or any PC on the same network:"
echo "  http://${HOST_IP}:5173/login"
echo

cd "$ROOT/backend"
.venv/bin/uvicorn app.main:app --host 0.0.0.0 --port 8000 &
API_PID=$!
trap 'kill $API_PID 2>/dev/null || true' EXIT
sleep 2
cd "$ROOT/frontend"
exec npm run preview -- --host 0.0.0.0 --port 5173
