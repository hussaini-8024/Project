#!/usr/bin/env bash
# Build AU-Kamra agent for Linux or macOS and place it for admin-panel download.
set -euo pipefail
cd "$(dirname "$0")/.."
PLATFORM="$(uname -s | tr '[:upper:]' '[:lower:]')"
if [[ "$PLATFORM" == "darwin" ]]; then
  DEST_DIR="bin/agents/macos"
else
  DEST_DIR="bin/agents/linux"
fi
mkdir -p "$DEST_DIR" .venv
python3 -m venv .venv
# shellcheck disable=SC1091
source .venv/bin/activate
pip install -U pip
pip install -r requirements.txt
pyinstaller --noconfirm --onefile --name AU-Kamra-Remote-Manager-Agent \
  --distpath "$DEST_DIR" \
  --paths . \
  --hidden-import agent.discovery \
  --hidden-import agent.install_service \
  --hidden-import shared.config \
  --hidden-import shared.protocol \
  --hidden-import websocket \
  --hidden-import requests \
  --hidden-import mss \
  --hidden-import PIL \
  run_agent.py
chmod +x "$DEST_DIR/AU-Kamra-Remote-Manager-Agent"
echo "Built: $DEST_DIR/AU-Kamra-Remote-Manager-Agent"
echo "Upload via admin panel or use Generate; clients can Download ${PLATFORM}."
