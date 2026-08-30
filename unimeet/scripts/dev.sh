#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"

if ! pg_isready -h 127.0.0.1 -p 5432 >/dev/null 2>&1; then
  echo "PostgreSQL is not running on 127.0.0.1:5432"
  echo "Start it, or use: docker compose up -d postgres"
  exit 1
fi

(cd "$ROOT/backend" && npm run db:migrate)
echo "Starting UniMeet API and web app..."
(cd "$ROOT/backend" && npm run dev) &
(cd "$ROOT/frontend" && npm run dev) &
wait
