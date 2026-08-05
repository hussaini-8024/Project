#!/usr/bin/env bash
# Launch UBNT Dish IP Finder (Web UI)
set -euo pipefail
cd "$(dirname "$0")"

if ! python3 -c "import flask" 2>/dev/null; then
  echo "Installing Flask..."
  pip3 install -r requirements.txt --quiet
fi

echo ""
echo "  UBNT Dish IP Finder"
echo "  Opening http://127.0.0.1:5055"
echo "  Press Ctrl+C to stop"
echo ""
exec python3 app.py
