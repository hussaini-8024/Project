#!/usr/bin/env bash
# Build single-file AU Labs Server, Agent, and Setup binaries (Linux/macOS).
# Output lands in dist/ — on Windows use scripts/build_windows.bat for .exe files.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

echo "[build] Preparing virtualenv..."
if [[ ! -d .venv ]]; then
  python3 -m venv .venv
fi
# shellcheck disable=SC1091
source .venv/bin/activate
pip install -q -r requirements.txt pyinstaller==6.11.1

rm -rf build dist/AULabsServer dist/AULabsAgent dist/AULabsSetup \
  dist/AULabsServer.exe dist/AULabsAgent.exe dist/AULabsSetup.exe 2>/dev/null || true
mkdir -p dist release

echo "[build] Building AULabsServer (onefile)..."
pyinstaller --noconfirm --clean --distpath dist --workpath build packaging/AULabsServer.spec

echo "[build] Building AULabsAgent (onefile)..."
pyinstaller --noconfirm --clean --distpath dist --workpath build packaging/AULabsAgent.spec

# Stage payload for the setup wizard
mkdir -p dist/payload
cp -f dist/AULabsServer dist/payload/AULabsServer 2>/dev/null || cp -f dist/AULabsServer.exe dist/payload/AULabsServer.exe
cp -f dist/AULabsAgent dist/payload/AULabsAgent 2>/dev/null || cp -f dist/AULabsAgent.exe dist/payload/AULabsAgent.exe

echo "[build] Building AULabsSetup installer (onefile)..."
python packaging/generate_setup_spec.py
pyinstaller --noconfirm --clean --distpath dist --workpath build packaging/_AULabsSetup_gen.spec

# Also produce console setup helper (useful on headless servers)
pyinstaller --noconfirm --clean --onefile --name AULabsSetupConsole \
  --distpath dist --workpath build \
  --paths . \
  --add-data "aulabs:aulabs" \
  --add-binary "dist/AULabsServer:payload" \
  --add-binary "dist/AULabsAgent:payload" \
  --hidden-import aulabs.setup_wizard \
  packaging/run_setup.py || true

# Create release folder with user-friendly names
OS_NAME="$(uname -s | tr '[:upper:]' '[:lower:]')"
ARCH="$(uname -m)"
REL="release/AULabs-${OS_NAME}-${ARCH}"
rm -rf "$REL"
mkdir -p "$REL"
for f in AULabsServer AULabsAgent AULabsSetup AULabsSetupConsole; do
  if [[ -f "dist/$f" ]]; then
    cp -f "dist/$f" "$REL/$f"
    chmod +x "$REL/$f"
  fi
  if [[ -f "dist/$f.exe" ]]; then
    cp -f "dist/$f.exe" "$REL/$f.exe"
  fi
done
cp -f README.md "$REL/README.txt" 2>/dev/null || true
cat > "$REL/HOW_TO_INSTALL.txt" <<'EOF'
AU Labs IT Management — Easy Install
====================================

Like VLC / other desktop software:

1. Double-click (or run)  AULabsSetup
2. Click Next → choose Server and/or Agent
3. Pick install folder → Install
4. Open http://127.0.0.1:8787
   Login: admin / aulabs-admin

You can also run components directly:
  ./AULabsServer     — start the web panel
  ./AULabsAgent      — start the host agent

Windows users: build with scripts\build_windows.bat to get:
  AULabsSetup.exe / AULabsServer.exe / AULabsAgent.exe
EOF

echo
echo "[build] Done."
echo "  Binaries : dist/"
echo "  Release  : $REL/"
ls -lh dist/AULabs* "$REL"/ 2>/dev/null || ls -lh dist/
