#!/usr/bin/env bash
# Deploy the Cyber Range on the campus Ubuntu server 172.26.1.3.
# Run this ON that server (not from another PC's browser localhost).
set -euo pipefail
export PUBLIC_HOST=172.26.1.3
export PUBLIC_UI_PORT=80
export PUBLIC_HOST_ONLY=true
export CORS_ALLOW_LAN=true
export COOKIE_SECURE=false
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
chmod +x "$ROOT/scripts/run-ubuntu-lan.sh"
exec "$ROOT/scripts/run-ubuntu-lan.sh"
