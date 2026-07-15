#!/usr/bin/env bash
# AU Labs IT Management — one-command Linux installer
# Usage:
#   curl -fsSL https://.../install.sh | bash
#   OR:  bash install.sh
#   OR:  ./install.sh
set -euo pipefail

APP_NAME="AU Labs IT Management"
APP_SLUG="aulabs"
VERSION="1.0.0"
INSTALL_ROOT="${AULABS_INSTALL_ROOT:-/opt/aulabs}"
DATA_ROOT="${AULABS_DATA_ROOT:-/opt/aulabs/data}"
SERVICE_USER="${AULABS_SERVICE_USER:-aulabs}"
HOST="${AULABS_HOST:-127.0.0.1}"
PORT="${AULABS_PORT:-8787}"
ADMIN_USER="${AULABS_ADMIN_USER:-admin}"
ADMIN_PASS="${AULABS_ADMIN_PASS:-aulabs-admin}"

RED='\033[0;31m'
GRN='\033[0;32m'
CYN='\033[0;36m'
YLW='\033[1;33m'
NC='\033[0m'

log()  { echo -e "${CYN}[aulabs]${NC} $*"; }
ok()   { echo -e "${GRN}[ok]${NC} $*"; }
warn() { echo -e "${YLW}[warn]${NC} $*"; }
die()  { echo -e "${RED}[error]${NC} $*" >&2; exit 1; }

banner() {
  cat <<'EOF'

   █████╗ ██╗   ██╗    ██╗      █████╗ ██████╗ ███████╗
  ██╔══██╗██║   ██║    ██║     ██╔══██╗██╔══██╗██╔════╝
  ███████║██║   ██║    ██║     ███████║██████╔╝███████╗
  ██╔══██║██║   ██║    ██║     ██╔══██║██╔══██╗╚════██║
  ██║  ██║╚██████╔╝    ███████╗██║  ██║██████╔╝███████║
  ╚═╝  ╚═╝ ╚═════╝     ╚══════╝╚═╝  ╚═╝╚═════╝ ╚══════╝
           IT Management — Linux hosting panel

EOF
  log "Installing ${APP_NAME} v${VERSION}"
}

require_linux() {
  [[ "$(uname -s)" == "Linux" ]] || die "This software only runs on Linux."
}

detect_pkg() {
  if command -v apt-get >/dev/null 2>&1; then echo apt
  elif command -v dnf >/dev/null 2>&1; then echo dnf
  elif command -v yum >/dev/null 2>&1; then echo yum
  elif command -v pacman >/dev/null 2>&1; then echo pacman
  elif command -v zypper >/dev/null 2>&1; then echo zypper
  else echo none
  fi
}

install_system_deps() {
  local mgr
  mgr="$(detect_pkg)"
  log "Installing system packages via ${mgr}..."
  case "$mgr" in
    apt)
      export DEBIAN_FRONTEND=noninteractive
      apt-get update -y
      apt-get install -y python3 python3-venv python3-pip curl ca-certificates
      ;;
    dnf)
      dnf install -y python3 python3-pip python3-virtualenv curl ca-certificates
      ;;
    yum)
      yum install -y python3 python3-pip curl ca-certificates
      ;;
    pacman)
      pacman -Sy --noconfirm python python-pip curl ca-certificates
      ;;
    zypper)
      zypper --non-interactive install python3 python3-pip curl ca-certificates
      ;;
    *)
      command -v python3 >/dev/null 2>&1 || die "python3 is required but no package manager was found"
      warn "No supported package manager; assuming python3 is already installed"
      ;;
  esac
  ok "System dependencies ready"
}

resolve_source() {
  # Prefer the directory containing this script (local clone / project tree)
  local script_dir
  script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
  if [[ -f "${script_dir}/aulabs/__init__.py" ]]; then
    SOURCE_DIR="$script_dir"
    return
  fi
  if [[ -f "${script_dir}/../aulabs/__init__.py" ]]; then
    SOURCE_DIR="$(cd "${script_dir}/.." && pwd)"
    return
  fi
  die "Cannot find AU Labs source tree. Run install.sh from the project root."
}

create_service_user() {
  if [[ "$(id -u)" -ne 0 ]]; then
    SERVICE_USER="$(id -un)"
    warn "Not root — installing for current user: ${SERVICE_USER}"
    INSTALL_ROOT="${HOME}/.local/share/aulabs"
    DATA_ROOT="${HOME}/.local/share/aulabs/data"
    return
  fi
  if ! id -u "$SERVICE_USER" >/dev/null 2>&1; then
    useradd --system --home-dir "$INSTALL_ROOT" --shell /usr/sbin/nologin "$SERVICE_USER" || true
  fi
  ok "Service user: ${SERVICE_USER}"
}

copy_app() {
  log "Installing application into ${INSTALL_ROOT}"
  mkdir -p "$INSTALL_ROOT" "$DATA_ROOT" "$DATA_ROOT/users" "$DATA_ROOT/sessions"
  rsync -a --delete \
    --exclude '.git' \
    --exclude 'data' \
    --exclude '.venv' \
    --exclude '__pycache__' \
    --exclude '*.pyc' \
    "${SOURCE_DIR}/" "${INSTALL_ROOT}/" 2>/dev/null || {
      # Fallback without rsync
      mkdir -p "${INSTALL_ROOT}"
      cp -a "${SOURCE_DIR}/aulabs" "${INSTALL_ROOT}/"
      cp -a "${SOURCE_DIR}/requirements.txt" "${INSTALL_ROOT}/"
      cp -a "${SOURCE_DIR}/install.sh" "${INSTALL_ROOT}/" 2>/dev/null || true
      cp -a "${SOURCE_DIR}/systemd" "${INSTALL_ROOT}/" 2>/dev/null || true
      cp -a "${SOURCE_DIR}/scripts" "${INSTALL_ROOT}/" 2>/dev/null || true
      cp -a "${SOURCE_DIR}/README.md" "${INSTALL_ROOT}/" 2>/dev/null || true
    }
  if [[ "$(id -u)" -eq 0 ]]; then
    chown -R "${SERVICE_USER}:${SERVICE_USER}" "$INSTALL_ROOT" "$DATA_ROOT"
  fi
  ok "Application files installed"
}

setup_venv() {
  log "Creating Python virtual environment"
  python3 -m venv "${INSTALL_ROOT}/.venv"
  # shellcheck disable=SC1091
  source "${INSTALL_ROOT}/.venv/bin/activate"
  pip install --upgrade pip wheel setuptools >/dev/null
  pip install -r "${INSTALL_ROOT}/requirements.txt"
  ok "Python dependencies installed"
}

write_env() {
  local secret
  secret="$(python3 - <<'PY'
import secrets
print(secrets.token_hex(32))
PY
)"
  cat > "${INSTALL_ROOT}/aulabs.env" <<EOF
AULABS_HOST=${HOST}
AULABS_PORT=${PORT}
AULABS_DATA_ROOT=${DATA_ROOT}
AULABS_ADMIN_USER=${ADMIN_USER}
AULABS_ADMIN_PASS=${ADMIN_PASS}
AULABS_SECRET_KEY=${secret}
AULABS_DEFAULT_STORAGE_MB=1024
EOF
  chmod 600 "${INSTALL_ROOT}/aulabs.env"
  if [[ "$(id -u)" -eq 0 ]]; then
    chown "${SERVICE_USER}:${SERVICE_USER}" "${INSTALL_ROOT}/aulabs.env"
  fi
  ok "Environment file written"
}

write_launcher() {
  mkdir -p "${INSTALL_ROOT}/bin"
  cat > "${INSTALL_ROOT}/bin/aulabs" <<EOF
#!/usr/bin/env bash
set -euo pipefail
ROOT="${INSTALL_ROOT}"
# shellcheck disable=SC1091
set -a
source "\${ROOT}/aulabs.env"
set +a
# shellcheck disable=SC1091
source "\${ROOT}/.venv/bin/activate"
cd "\${ROOT}"
exec python -m aulabs "\$@"
EOF
  chmod +x "${INSTALL_ROOT}/bin/aulabs"

  if [[ "$(id -u)" -eq 0 ]]; then
    ln -sfn "${INSTALL_ROOT}/bin/aulabs" /usr/local/bin/aulabs
  else
    mkdir -p "${HOME}/.local/bin"
    ln -sfn "${INSTALL_ROOT}/bin/aulabs" "${HOME}/.local/bin/aulabs"
  fi
  ok "Launcher ready: aulabs"
}

install_systemd() {
  if [[ "$(id -u)" -ne 0 ]]; then
    warn "Skipping systemd unit (not root). Start manually with: aulabs serve"
    return
  fi
  if ! command -v systemctl >/dev/null 2>&1; then
    warn "systemd not available — skipping service install"
    return
  fi
  cat > /etc/systemd/system/aulabs.service <<EOF
[Unit]
Description=AU Labs IT Management
After=network.target

[Service]
Type=simple
User=${SERVICE_USER}
Group=${SERVICE_USER}
WorkingDirectory=${INSTALL_ROOT}
EnvironmentFile=${INSTALL_ROOT}/aulabs.env
ExecStart=${INSTALL_ROOT}/.venv/bin/python -m aulabs serve
Restart=on-failure
RestartSec=3
LimitNOFILE=65535

[Install]
WantedBy=multi-user.target
EOF
  systemctl daemon-reload
  systemctl enable aulabs.service
  systemctl restart aulabs.service
  ok "systemd service enabled and started"
}

init_app() {
  log "Initializing database and admin account"
  # shellcheck disable=SC1091
  set -a
  source "${INSTALL_ROOT}/aulabs.env"
  set +a
  # shellcheck disable=SC1091
  source "${INSTALL_ROOT}/.venv/bin/activate"
  cd "${INSTALL_ROOT}"
  if [[ "$(id -u)" -eq 0 ]]; then
    sudo -u "${SERVICE_USER}" env \
      AULABS_DATA_ROOT="$DATA_ROOT" \
      AULABS_ADMIN_USER="$ADMIN_USER" \
      AULABS_ADMIN_PASS="$ADMIN_PASS" \
      "${INSTALL_ROOT}/.venv/bin/python" -m aulabs init
  else
    python -m aulabs init
  fi
  ok "Panel initialized"
}

verify() {
  log "Verifying installation"
  # shellcheck disable=SC1091
  source "${INSTALL_ROOT}/.venv/bin/activate"
  python - <<'PY'
from aulabs import __app_name__, __version__
print(f"{__app_name__} {__version__} OK")
PY
  ok "Verification passed"
}

start_user_mode() {
  if [[ "$(id -u)" -eq 0 ]]; then
    return
  fi
  log "Starting panel in background (user mode)"
  nohup "${INSTALL_ROOT}/bin/aulabs" serve >/tmp/aulabs.log 2>&1 &
  echo $! > /tmp/aulabs.pid
  sleep 2
  ok "Panel started (pid $(cat /tmp/aulabs.pid)) — logs: /tmp/aulabs.log"
}

finish() {
  echo
  ok "${APP_NAME} installation complete"
  echo
  echo "  Panel URL : http://${HOST}:${PORT}"
  echo "  Admin user: ${ADMIN_USER}"
  echo "  Admin pass: ${ADMIN_PASS}"
  echo "  Data root : ${DATA_ROOT}"
  echo "  Install   : ${INSTALL_ROOT}"
  echo
  echo "  Commands:"
  echo "    aulabs serve     # start panel"
  echo "    aulabs init      # re-init data"
  echo "    aulabs version   # show version"
  if [[ "$(id -u)" -eq 0 ]]; then
    echo "    systemctl status aulabs"
  fi
  echo
  echo "  Change the admin password after first login."
  echo
}

main() {
  banner
  require_linux
  resolve_source
  if [[ "$(id -u)" -eq 0 ]]; then
    install_system_deps
  else
    command -v python3 >/dev/null 2>&1 || die "python3 is required. Re-run as root for auto package install, or install python3 first."
  fi
  create_service_user
  copy_app
  setup_venv
  write_env
  write_launcher
  init_app
  install_systemd
  verify
  start_user_mode
  finish
}

main "$@"
