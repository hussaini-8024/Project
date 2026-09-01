#!/usr/bin/env bash
# Start the Cyber Range so typing the Ubuntu IP in a browser works (port 80).
# Ping uses ICMP. The web page is HTTP on TCP 80 — that is why ping can work
# while http://172.26.1.3 does not, until nginx is listening on 80.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
HOST_IP="${PUBLIC_HOST:-172.26.1.3}"
export PUBLIC_HOST="$HOST_IP"
export PUBLIC_UI_PORT="${PUBLIC_UI_PORT:-80}"
export CORS_ALLOW_LAN=true
export COOKIE_SECURE=false

echo "University Cyber Range — bind 0.0.0.0, login http://${HOST_IP}/login"

sudo apt-get update -qq
sudo apt-get install -y python3 python3-venv python3-pip nginx iproute2 busybox bridge-utils iptables
if command -v ufw >/dev/null && sudo ufw status 2>/dev/null | grep -qi active; then
  sudo ufw allow 80/tcp || true
  sudo ufw allow 5173/tcp || true
  sudo ufw allow 8000/tcp || true
fi

if ! command -v node >/dev/null; then
  echo "Install Node 20+ then re-run. Example: curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash - && sudo apt-get install -y nodejs" >&2
  exit 1
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

sudo cp "$ROOT/scripts/nginx-lan.conf" /etc/nginx/sites-available/cyberrange
sudo ln -sfn /etc/nginx/sites-available/cyberrange /etc/nginx/sites-enabled/cyberrange
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
if command -v systemctl >/dev/null && systemctl is-system-running >/dev/null 2>&1; then
  sudo systemctl enable --now nginx
  sudo systemctl reload nginx
else
  sudo nginx -s reload 2>/dev/null || sudo nginx
fi

echo
echo "Open in a browser (no port number needed):"
echo "  http://${HOST_IP}/login"
echo "  http://${HOST_IP}"
echo

cd "$ROOT/backend"
.venv/bin/uvicorn app.main:app --host 0.0.0.0 --port 8000 &
API_PID=$!
trap 'kill $API_PID 2>/dev/null || true' EXIT
sleep 2
cd "$ROOT/frontend"
exec npm run preview -- --host 0.0.0.0 --port 5173
