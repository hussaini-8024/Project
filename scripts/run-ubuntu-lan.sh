#!/usr/bin/env bash
# Install feature packages first, then start the range on dedicated ports
# 18080/18081 (UI) and 18000 (API). Do not use 80/8080/5173/8000.
set -euo pipefail
export DEBIAN_FRONTEND=noninteractive
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
# shellcheck disable=SC1091
. "$ROOT/scripts/ports.env"
API_PORT="${CYBERRANGE_API_PORT:-18000}"
UI_PORT="${CYBERRANGE_UI_PORT:-18080}"
UI_ALT_PORT="${CYBERRANGE_UI_ALT_PORT:-18081}"

detect_lan_ip() {
  if [ -n "${PUBLIC_HOST:-}" ]; then
    printf '%s\n' "$PUBLIC_HOST"
    return
  fi
  local ip
  if command -v ip >/dev/null 2>&1; then
    if ip -4 addr show scope global | grep -q 'inet 172.26.1.3/'; then
      printf '%s\n' "172.26.1.3"
      return
    fi
    ip -4 -o addr show scope global | awk '{print $4}' | cut -d/ -f1 | while read -r ip; do
      case "$ip" in
        127.*|172.17.*|172.18.*|172.19.*) continue ;;
        10.0.0.1) continue ;;
      esac
      printf '%s\n' "$ip"
      break
    done
    return
  fi
  printf '%s\n' "172.26.1.3"
}

chmod +x "$ROOT/scripts/install-ubuntu-deps.sh"
"$ROOT/scripts/install-ubuntu-deps.sh"

HOST_IP="$(detect_lan_ip)"
HOST_IP="${HOST_IP:-172.26.1.3}"
export PUBLIC_HOST="$HOST_IP"
export PUBLIC_UI_PORT="${PUBLIC_UI_PORT:-$UI_PORT}"
export CORS_ALLOW_LAN=true
export COOKIE_SECURE=false

if [ ! -f "$ROOT/backend/.env" ] && [ -f "$ROOT/.env.example" ]; then
  cp "$ROOT/.env.example" "$ROOT/backend/.env"
fi

echo "== Opening dedicated HTTP ports (ufw + iptables). Failures here must not stop the site. =="
if command -v ufw >/dev/null 2>&1; then
  sudo ufw allow "${UI_PORT}/tcp" || true
  sudo ufw allow "${UI_ALT_PORT}/tcp" || true
  sudo ufw allow "${API_PORT}/tcp" || true
fi
for p in "$UI_PORT" "$UI_ALT_PORT" "$API_PORT"; do
  sudo iptables -C INPUT -p tcp --dport "$p" -j ACCEPT 2>/dev/null \
    || sudo iptables -I INPUT -p tcp --dport "$p" -j ACCEPT 2>/dev/null \
    || true
done

chmod +x "$ROOT/scripts/install-boot-services.sh"
"$ROOT/scripts/install-boot-services.sh"

echo
echo "Login from this Ubuntu or another PC on the same network:"
echo "  http://${HOST_IP}:${UI_PORT}/login"
echo "  http://${HOST_IP}:${UI_ALT_PORT}/login"
echo
echo "API health: http://${HOST_IP}:${UI_PORT}/api/health"
echo "Services are enabled on boot (systemd Restart=always, or cron watchdog)."
