#!/usr/bin/env bash
# Install Flutter + Android SDK command-line tools on Linux for app building.
# Usage: bash scripts/install-flutter-linux.sh
set -euo pipefail

FLUTTER_DIR="${FLUTTER_DIR:-$HOME/development/flutter}"
ANDROID_SDK_ROOT="${ANDROID_SDK_ROOT:-$HOME/Android/Sdk}"
JAVA_HOME_CANDIDATE="${JAVA_HOME:-/usr/lib/jvm/java-17-openjdk-amd64}"

echo "==> Installing system packages (needs sudo)..."
sudo apt-get update -qq
sudo DEBIAN_FRONTEND=noninteractive apt-get install -y -qq \
  curl git unzip xz-utils zip libglu1-mesa \
  clang cmake ninja-build pkg-config libgtk-3-dev \
  openjdk-17-jdk wget ca-certificates

if [[ ! -d "$JAVA_HOME_CANDIDATE" ]]; then
  JAVA_HOME_CANDIDATE="$(dirname "$(dirname "$(readlink -f "$(command -v java)")")")"
fi
export JAVA_HOME="$JAVA_HOME_CANDIDATE"

echo "==> Installing Flutter SDK to $FLUTTER_DIR ..."
mkdir -p "$(dirname "$FLUTTER_DIR")"
if [[ ! -x "$FLUTTER_DIR/bin/flutter" ]]; then
  TMP_TAR="$(mktemp /tmp/flutter-XXXXXX.tar.xz)"
  # Resolve latest stable Linux tarball from the Flutter storage API
  LATEST_URL="$(curl -fsSL https://storage.googleapis.com/flutter_infra_release/releases/releases_linux.json \
    | python3 -c 'import json,sys; d=json.load(sys.stdin); h=d["current_release"]["stable"]; print(next(r["archive"] for r in d["releases"] if r["hash"]==h))')"
  curl -fsSL -o "$TMP_TAR" "https://storage.googleapis.com/flutter_infra_release/releases/${LATEST_URL}"
  tar -C "$(dirname "$FLUTTER_DIR")" -xf "$TMP_TAR"
  rm -f "$TMP_TAR"
  # Tarball extracts as "flutter/" next to FLUTTER_DIR parent
  if [[ ! -d "$FLUTTER_DIR" ]]; then
    mv "$(dirname "$FLUTTER_DIR")/flutter" "$FLUTTER_DIR"
  fi
else
  echo "    Flutter already present; skipping download."
fi

echo "==> Installing Android SDK command-line tools to $ANDROID_SDK_ROOT ..."
mkdir -p "$ANDROID_SDK_ROOT/cmdline-tools"
if [[ ! -x "$ANDROID_SDK_ROOT/cmdline-tools/latest/bin/sdkmanager" ]]; then
  TMP_ZIP="$(mktemp /tmp/cmdline-tools-XXXXXX.zip)"
  curl -fsSL -o "$TMP_ZIP" https://dl.google.com/android/repository/commandlinetools-linux-13114758_latest.zip
  TMP_DIR="$(mktemp -d)"
  unzip -q "$TMP_ZIP" -d "$TMP_DIR"
  rm -rf "$ANDROID_SDK_ROOT/cmdline-tools/latest"
  mkdir -p "$ANDROID_SDK_ROOT/cmdline-tools/latest"
  mv "$TMP_DIR"/cmdline-tools/* "$ANDROID_SDK_ROOT/cmdline-tools/latest/"
  rm -rf "$TMP_DIR" "$TMP_ZIP"
fi

export ANDROID_HOME="$ANDROID_SDK_ROOT"
export ANDROID_SDK_ROOT
export PATH="$FLUTTER_DIR/bin:$ANDROID_SDK_ROOT/cmdline-tools/latest/bin:$ANDROID_SDK_ROOT/platform-tools:$JAVA_HOME/bin:$PATH"

echo "==> Accepting Android licenses and installing SDK packages..."
yes | sdkmanager --licenses >/dev/null || true
sdkmanager --install \
  "platform-tools" \
  "platforms;android-35" \
  "platforms;android-34" \
  "build-tools;35.0.0" \
  "build-tools;34.0.0"

flutter config --android-sdk "$ANDROID_SDK_ROOT"
flutter config --no-analytics || true
yes | flutter doctor --android-licenses >/dev/null || true

MARKER="# FLUTTER_DEV_SETUP"
if ! grep -q "$MARKER" "$HOME/.bashrc" 2>/dev/null; then
  cat >> "$HOME/.bashrc" << EOF

$MARKER
export JAVA_HOME=$JAVA_HOME
export ANDROID_HOME=$ANDROID_SDK_ROOT
export ANDROID_SDK_ROOT=\$ANDROID_HOME
export PATH="$FLUTTER_DIR/bin:\$ANDROID_HOME/cmdline-tools/latest/bin:\$ANDROID_HOME/platform-tools:\$JAVA_HOME/bin:\$PATH"
EOF
  echo "==> Added Flutter/Android PATH exports to ~/.bashrc"
fi

echo "==> flutter doctor"
flutter doctor -v

echo
echo "Done. Open a new terminal (or: source ~/.bashrc), then:"
echo "  flutter create my_app && cd my_app && flutter run"
echo "  flutter build apk"
