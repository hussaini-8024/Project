#!/usr/bin/env bash
# Idempotent start of the Cyber Range portal (nginx :80/:8080 + API :8000).
# Safe to run from systemd, cron (@reboot and every minute), or environment start.
set -euo pipefail
ROOT="${CYBERRANGE_ROOT:-$(cd "$(dirname "$0")/.." && pwd)}"
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
if ! flock -n 9; then
  exit 0
fi

api_ok() {
  curl -sf --max-time 2 "http://127.0.0.1:8000/api/health" >/dev/null 2>&1
}

ui_ok() {
  curl -sf --max-time 2 "http://127.0.0.1/login" >/dev/null 2>&1 \
    || curl -sf --max-time 2 "http://127.0.0.1:8080/login" >/dev/null 2>&1
}

nginx_running() {
  pgrep -x nginx >/dev/null 2>&1
}

systemd_ok() {
  command -v systemctl >/dev/null 2>&1 \
    && systemctl is-system-running >/dev/null 2>&1
}

export CORS_ALLOW_LAN="${CORS_ALLOW_LAN:-true}"
export COOKIE_SECURE="${COOKIE_SECURE:-false}"
export PUBLIC_UI_PORT="${PUBLIC_UI_PORT:-80}"
if [ -z "${PUBLIC_HOST:-}" ] && command -v ip >/dev/null 2>&1; then
  if ip -4 addr show scope global | grep -q 'inet 172.26.1.3/'; then
    export PUBLIC_HOST=172.26.1.3
  fi
fi

# Publish the built UI where nginx can always read it.
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
  sudo ufw allow 8080/tcp >/dev/null 2>&1 || true
  sudo ufw allow 8000/tcp >/dev/null 2>&1 || true
fi

if [ "$PREPARE_ONLY" -eq 1 ]; then
  if command -v nginx >/dev/null 2>&1; then
    sudo nginx -t >/dev/null 2>&1 || true
  fi
  exit 0
fi

if api_ok && ui_ok; then
  exit 0
fi

if systemd_ok && [ -f /etc/systemd/system/cyberrange-api.service ]; then
  sudo nginx -t >/dev/null 2>&1 && sudo systemctl reload nginx 2>/dev/null \
    || sudo systemctl enable --now nginx >/dev/null 2>&1 \
    || sudo nginx -s reload 2>/dev/null || sudo nginx
  sudo systemctl enable --now cyberrange-api >/dev/null 2>&1 || true
else
  if command -v nginx >/dev/null 2>&1; then
    sudo nginx -t >/dev/null 2>&1 || true
    if nginx_running; then
      sudo nginx -s reload >/dev/null 2>&1 || true
    else
      sudo nginx >/dev/null 2>&1 || true
    fi
  fi
  if ! api_ok && [ -x "$ROOT/backend/.venv/bin/uvicorn" ]; then
    cd "$ROOT/backend"
    nohup .venv/bin/uvicorn app.main:app --host 0.0.0.0 --port 8000 \
      >>"$ROOT/backend/data/api.log" 2>&1 &
    echo $! >"$ROOT/backend/data/api.pid"
  fi
fi

for _ in 1 2 3 4 5 6 7 8 9 10; do
  api_ok && break
  sleep 0.4
done
exit 0
