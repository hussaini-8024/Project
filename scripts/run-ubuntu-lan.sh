#!/usr/bin/env bash
# Install feature packages first, then start the range on port 80
# (http://172.26.1.3/login and http://127.0.0.1/login).
set -euo pipefail
export DEBIAN_FRONTEND=noninteractive
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
# shellcheck disable=SC1091
. "$ROOT/scripts/ports.env"
API_PORT="${CYBERRANGE_API_PORT:-18000}"
UI_PORT="${CYBERRANGE_UI_PORT:-80}"
UI_ALT_PORT="${CYBERRANGE_UI_ALT_PORT:-8080}"
UI_EXTRA_PORT="${CYBERRANGE_UI_EXTRA_PORT:-18080}"

chmod +x "$ROOT/scripts/install-ubuntu-deps.sh"
"$ROOT/scripts/install-ubuntu-deps.sh"

HOST_IP="${CYBERRANGE_PUBLIC_HOST:-172.26.1.3}"
export PUBLIC_HOST="$HOST_IP"
export PUBLIC_UI_PORT=80
export PUBLIC_HOST_ONLY=true
export CORS_ALLOW_LAN=true
export COOKIE_SECURE=false

if [ ! -f "$ROOT/backend/.env" ] && [ -f "$ROOT/.env.example" ]; then
  cp "$ROOT/.env.example" "$ROOT/backend/.env"
fi

echo "== Opening HTTP ports 80/8080/18080/18000 =="
if command -v ufw >/dev/null 2>&1; then
  sudo ufw allow 80/tcp || true
  sudo ufw allow "${UI_ALT_PORT}/tcp" || true
  sudo ufw allow "${UI_EXTRA_PORT}/tcp" || true
  sudo ufw allow "${API_PORT}/tcp" || true
fi
for p in 80 "$UI_ALT_PORT" "$UI_EXTRA_PORT" "$API_PORT"; do
  sudo iptables -C INPUT -p tcp --dport "$p" -j ACCEPT 2>/dev/null \
    || sudo iptables -I INPUT -p tcp --dport "$p" -j ACCEPT 2>/dev/null \
    || true
done

chmod +x "$ROOT/scripts/install-boot-services.sh"
"$ROOT/scripts/install-boot-services.sh"

echo
echo "Campus login (use this from other PCs — never 127.0.0.1 from another computer):"
echo "  http://${HOST_IP}/login"
echo "On the server itself (or Cursor port-forward of 80):"
echo "  http://127.0.0.1/login"
echo "Fallbacks: http://${HOST_IP}:8080/login  http://${HOST_IP}:18080/login"
