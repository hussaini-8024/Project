#!/usr/bin/env bash
# Idempotent start of the Cyber Range portal on dedicated ports
# (UI 18080/18081, API 18000). Safe for systemd, cron, and environment start.
set -euo pipefail
ROOT="${CYBERRANGE_ROOT:-$(cd "$(dirname "$0")/.." && pwd)}"
# shellcheck disable=SC1091
. "$ROOT/scripts/ports.env"
API_PORT="${CYBERRANGE_API_PORT:-18000}"
UI_PORT="${CYBERRANGE_UI_PORT:-80}"
UI_ALT_PORT="${CYBERRANGE_UI_ALT_PORT:-8080}"
UI_EXTRA_PORT="${CYBERRANGE_UI_EXTRA_PORT:-18080}"
SERVER_IP="${CYBERRANGE_PUBLIC_HOST:-172.26.1.3}"
LOCK="${CYBERRANGE_LOCK:-$ROOT/backend/data/boot.lock}"
PREPARE_ONLY=0
for arg in "$@"; do
  case "$arg" in
    --prepare-only) PREPARE_ONLY=1 ;;
  esac
done

mkdir -p "$ROOT/backend/data"
if [ ! -w "$(dirname "$LOCK")" ] || { [ -e "$LOCK" ] && [ ! -w "$LOCK" ]; }; then
  sudo rm -f "$LOCK" 2>/dev/null || true
  sudo mkdir -p "$(dirname "$LOCK")" 2>/dev/null || true
fi
touch "$LOCK" 2>/dev/null || sudo touch "$LOCK"
chmod 666 "$LOCK" 2>/dev/null || sudo chmod 666 "$LOCK" 2>/dev/null || true
exec 9>"$LOCK"
if ! flock -w 60 9; then
  echo "cyberrange-boot: could not obtain lock" >&2
  exit 0
fi

api_ok() {
  curl -sf --max-time 2 "http://127.0.0.1:${API_PORT}/api/health" >/dev/null 2>&1
}

ui_ok() {
  curl -sf --max-time 2 "http://127.0.0.1/login" >/dev/null 2>&1 \
    || curl -sf --max-time 2 "http://127.0.0.1:${UI_PORT}/login" >/dev/null 2>&1 \
    || curl -sf --max-time 2 "http://127.0.0.1:${UI_ALT_PORT}/login" >/dev/null 2>&1 \
    || curl -sf --max-time 2 "http://127.0.0.1:${UI_EXTRA_PORT}/login" >/dev/null 2>&1
}

nginx_running() {
  pgrep -x nginx >/dev/null 2>&1
}

systemd_ok() {
  command -v systemctl >/dev/null 2>&1 \
    && systemctl is-system-running >/dev/null 2>&1
}

stop_stale_listeners() {
  pkill -f "uvicorn app.main:app --host 0.0.0.0 --port 8000" >/dev/null 2>&1 || true
  pkill -f "vite preview --host 0.0.0.0 --port 5173" >/dev/null 2>&1 || true
}

export CORS_ALLOW_LAN="${CORS_ALLOW_LAN:-true}"
export COOKIE_SECURE="${COOKIE_SECURE:-false}"
export PUBLIC_HOST="${PUBLIC_HOST:-$SERVER_IP}"
export PUBLIC_UI_PORT="${PUBLIC_UI_PORT:-80}"
export PUBLIC_HOST_ONLY=true

if [ -f "$ROOT/frontend/dist/index.html" ]; then
  sudo mkdir -p /var/www/cyberrange
  sudo cp -a "$ROOT/frontend/dist/." /var/www/cyberrange/
  sudo chown -R www-data:www-data /var/www/cyberrange 2>/dev/null || true
fi

if [ -f "$ROOT/scripts/nginx-lan.conf" ]; then
  sudo mkdir -p /etc/nginx/sites-available /etc/nginx/sites-enabled
  sudo cp "$ROOT/scripts/nginx-lan.conf" /etc/nginx/sites-available/cyberrange
  sudo ln -sfn /etc/nginx/sites-available/cyberrange /etc/nginx/sites-enabled/cyberrange
  sudo rm -f /etc/nginx/sites-enabled/default
fi

if command -v ufw >/dev/null 2>&1; then
  sudo ufw allow 80/tcp >/dev/null 2>&1 || true
  sudo ufw allow "${UI_ALT_PORT}/tcp" >/dev/null 2>&1 || true
  sudo ufw allow "${UI_EXTRA_PORT}/tcp" >/dev/null 2>&1 || true
  sudo ufw allow "${API_PORT}/tcp" >/dev/null 2>&1 || true
fi
for p in 80 "$UI_ALT_PORT" "$UI_EXTRA_PORT" "$API_PORT"; do
  sudo iptables -C INPUT -p tcp --dport "$p" -j ACCEPT 2>/dev/null \
    || sudo iptables -I INPUT -p tcp --dport "$p" -j ACCEPT 2>/dev/null \
    || true
done

env_file="$ROOT/backend/.env"
if [ ! -f "$env_file" ] && [ -f "$ROOT/.env.example" ]; then
  cp "$ROOT/.env.example" "$env_file"
fi
if [ -f "$env_file" ]; then
  upsert() {
    local key="$1" val="$2"
    if grep -q "^${key}=" "$env_file"; then
      sed -i "s|^${key}=.*|${key}=${val}|" "$env_file"
    else
      echo "${key}=${val}" >>"$env_file"
    fi
  }
  upsert PUBLIC_HOST "$PUBLIC_HOST"
  upsert PUBLIC_UI_PORT "$PUBLIC_UI_PORT"
  upsert PUBLIC_HOST_ONLY true
fi

if [ "$PREPARE_ONLY" -eq 1 ]; then
  if command -v nginx >/dev/null 2>&1; then
    sudo nginx -t >/dev/null 2>&1 || true
  fi
  exit 0
fi

reload_nginx() {
  if ! command -v nginx >/dev/null 2>&1; then
    return 0
  fi
  sudo nginx -t >/dev/null 2>&1 || return 0
  if systemd_ok; then
    sudo systemctl reload nginx 2>/dev/null \
      || sudo systemctl enable --now nginx >/dev/null 2>&1 \
      || sudo nginx -s reload 2>/dev/null || sudo nginx
  elif nginx_running; then
    sudo nginx -s reload >/dev/null 2>&1 || sudo nginx
  else
    sudo nginx >/dev/null 2>&1 || true
  fi
}

reload_nginx

if api_ok; then
  health_json="$(curl -sf --max-time 2 "http://127.0.0.1:${API_PORT}/api/health" || true)"
  if echo "$health_json" | grep -q '172.30.0.2' || ! echo "$health_json" | grep -q '"server_ip"'; then
    pkill -f "uvicorn app.main:app --host 0.0.0.0 --port ${API_PORT}" >/dev/null 2>&1 || true
    sleep 0.4
  fi
fi

if api_ok && ui_ok; then
  exit 0
fi

if systemd_ok && [ -f /etc/systemd/system/cyberrange-api.service ]; then
  sudo systemctl enable --now cyberrange-api >/dev/null 2>&1 || true
else
  if ! api_ok && [ -x "$ROOT/backend/.venv/bin/uvicorn" ]; then
    cd "$ROOT/backend"
    nohup env PUBLIC_HOST="$PUBLIC_HOST" PUBLIC_UI_PORT="$PUBLIC_UI_PORT" \
      PUBLIC_HOST_ONLY=true CORS_ALLOW_LAN=true COOKIE_SECURE=false \
      .venv/bin/uvicorn app.main:app --host 0.0.0.0 --port "$API_PORT" \
      >>"$ROOT/backend/data/api.log" 2>&1 &
    echo $! >"$ROOT/backend/data/api.pid"
  fi
fi

for _ in 1 2 3 4 5 6 7 8 9 10 11 12 13 14 15; do
  api_ok && ui_ok && break
  sleep 0.4
done
exit 0
