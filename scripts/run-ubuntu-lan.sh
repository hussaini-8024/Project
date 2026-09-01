#!/usr/bin/env bash
# Install every OS package the Cyber Range needs on Ubuntu, then start it so
# http://<LAN-IP>/login works (port 80). Ping is ICMP; the website is TCP 80/8080.
set -euo pipefail
export DEBIAN_FRONTEND=noninteractive
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
HOST_IP="${PUBLIC_HOST:-172.26.1.3}"
export PUBLIC_HOST="$HOST_IP"
export PUBLIC_UI_PORT="${PUBLIC_UI_PORT:-80}"
export CORS_ALLOW_LAN=true
export COOKIE_SECURE=false

echo "== Installing packages (python, node, nginx, iproute, firewall) =="
sudo apt-get update -y
sudo apt-get install -y --no-install-recommends \
  ca-certificates curl git gnupg \
  python3 python3-venv python3-pip \
  nginx \
  iproute2 busybox bridge-utils iptables \
  build-essential

if ! command -v node >/dev/null 2>&1 || ! node -v | grep -qE 'v(1[8-9]|[2-9][0-9])'; then
  echo "== Installing Node.js 20 =="
  curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
  sudo apt-get install -y nodejs
fi
node -v
npm -v

echo "== Opening HTTP ports (ufw + iptables). Ping does not open these. =="
if command -v ufw >/dev/null; then
  sudo ufw allow 80/tcp || true
  sudo ufw allow 8080/tcp || true
  sudo ufw allow 5173/tcp || true
  sudo ufw allow 8000/tcp || true
fi
for p in 80 8080 5173 8000; do
  sudo iptables -C INPUT -p tcp --dport "$p" -j ACCEPT 2>/dev/null \
    || sudo iptables -I INPUT -p tcp --dport "$p" -j ACCEPT
done

echo "== Backend venv =="
cd "$ROOT/backend"
if [ ! -x .venv/bin/uvicorn ]; then
  python3 -m venv .venv
  .venv/bin/pip install -q --upgrade pip
  .venv/bin/pip install -q -r requirements.txt
fi
mkdir -p data

echo "== Frontend build =="
cd "$ROOT/frontend"
if [ ! -d node_modules ]; then
  npm install --no-audit --no-fund
fi
npm run build

echo "== nginx on ports 80 and 8080 =="
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
echo "Login from this Ubuntu or another PC on the same network:"
echo "  http://${HOST_IP}/login"
echo "  http://${HOST_IP}:8080/login"
echo "  http://${HOST_IP}:5173/login"
echo

cd "$ROOT/backend"
.venv/bin/uvicorn app.main:app --host 0.0.0.0 --port 8000 &
API_PID=$!
trap 'kill $API_PID 2>/dev/null || true' EXIT
sleep 2
cd "$ROOT/frontend"
exec npm run preview -- --host 0.0.0.0 --port 5173
