#!/usr/bin/env bash
# Install boot-time services so the portal starts after reboot and stays up.
# Uses systemd when this Ubuntu is running systemd; otherwise cron + a watchdog.
set -euo pipefail
export DEBIAN_FRONTEND=noninteractive
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
USER_NAME="$(id -un)"
chmod +x "$ROOT/scripts/cyberrange-boot.sh"

echo "== Installing persistent Cyber Range services (systemd and/or cron) =="
sudo apt-get install -y cron nginx >/dev/null 2>&1 || sudo apt-get install -y cron nginx

sudo tee /usr/local/sbin/cyberrange-boot >/dev/null <<EOF
#!/bin/bash
export CYBERRANGE_ROOT='$ROOT'
exec '$ROOT/scripts/cyberrange-boot.sh' "\$@"
EOF
sudo chmod 755 /usr/local/sbin/cyberrange-boot

unit_dir="$ROOT/scripts/systemd"
tmp="$(mktemp)"
sed -e "s|__ROOT__|$ROOT|g" -e "s|__USER__|$USER_NAME|g" \
  "$unit_dir/cyberrange-api.service" >"$tmp"
sudo cp "$tmp" /etc/systemd/system/cyberrange-api.service
sed -e "s|__ROOT__|$ROOT|g" -e "s|__USER__|$USER_NAME|g" \
  "$unit_dir/cyberrange-prepare.service" >"$tmp"
sudo cp "$tmp" /etc/systemd/system/cyberrange-prepare.service
sudo chmod 644 /etc/systemd/system/cyberrange-api.service /etc/systemd/system/cyberrange-prepare.service
rm -f "$tmp"

# Cron watchdog: start on reboot and recover within a minute if something dies.
# flock in cyberrange-boot.sh makes overlapping runs a no-op.
sudo tee /etc/cron.d/cyberrange >/dev/null <<EOF
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
@reboot root /usr/local/sbin/cyberrange-boot
* * * * * root /usr/local/sbin/cyberrange-boot
EOF
sudo chmod 644 /etc/cron.d/cyberrange

if command -v cron >/dev/null 2>&1; then
  if ! pgrep -x cron >/dev/null 2>&1 && ! pgrep -x crond >/dev/null 2>&1; then
    sudo cron || true
  fi
fi
if command -v systemctl >/dev/null 2>&1 && systemctl is-system-running >/dev/null 2>&1; then
  sudo systemctl daemon-reload
  sudo systemctl enable cyberrange-prepare.service nginx.service cron.service cyberrange-api.service
  sudo systemctl start cyberrange-prepare.service
  sudo systemctl restart nginx.service || sudo nginx -s reload || sudo nginx
  if curl -sf --max-time 2 http://127.0.0.1:18000/api/health >/dev/null 2>&1; then
    echo "API already listening; leaving the running process in place."
  else
    sudo systemctl restart cyberrange-api.service
  fi
  echo "systemd enabled: cyberrange-api, nginx, cron (Restart=always)."
else
  echo "systemd is not PID 1 on this host — using cron @reboot + every-minute watchdog."
  if command -v systemctl >/dev/null 2>&1; then
    sudo systemctl enable cyberrange-api.service nginx.service cron.service 2>/dev/null || true
  fi
fi

/usr/local/sbin/cyberrange-boot || true

echo
echo "Portal boot services installed. They start on reboot and restart if they die."
echo "  Campus server: http://172.26.1.3/login"
echo "  On this host:  http://127.0.0.1/login"
echo "  Fallbacks:     http://127.0.0.1:8080/login  http://127.0.0.1:18080/login"
