#!/usr/bin/env bash
#
# Add a Windows host to an Ansible AWX inventory.
#
# Intended for Windows environments with Bash available:
#   - Git Bash (https://git-scm.com/download/win)
#   - WSL (Windows Subsystem for Linux)
#   - Cygwin / MSYS2
#
# Requirements: curl, jq (recommended)
#
# Usage:
#   export AWX_URL="https://awx.example.com"
#   export AWX_TOKEN="your-oauth-token"
#   export AWX_INVENTORY_ID="5"
#   ./add-awx-windows-host.sh win-host01 192.168.1.50

set -euo pipefail

# ---------------------------------------------------------------------------
# CONFIGURATION (override with environment variables)
# ---------------------------------------------------------------------------
AWX_URL="${AWX_URL:-}"
AWX_TOKEN="${AWX_TOKEN:-}"
AWX_USERNAME="${AWX_USERNAME:-}"
AWX_PASSWORD="${AWX_PASSWORD:-}"
AWX_INVENTORY_ID="${AWX_INVENTORY_ID:-}"
AWX_VERIFY_SSL="${AWX_VERIFY_SSL:-true}"

# Windows / WinRM defaults
WINRM_USER="${WINRM_USER:-Administrator}"
WINRM_PASSWORD="${WINRM_PASSWORD:-}"
WINRM_PORT="${WINRM_PORT:-5985}"
WINRM_TRANSPORT="${WINRM_TRANSPORT:-ntlm}"   # ntlm | kerberos | credssp | basic
WINRM_SCHEME="${WINRM_SCHEME:-http}"         # http | https
WINRM_SERVER_CERT_VALIDATION="${WINRM_SERVER_CERT_VALIDATION:-ignore}"

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------
usage() {
  cat <<'EOF'
Add a Windows host to an Ansible AWX inventory.

Usage:
  add-awx-windows-host.sh <host_name> <ansible_host> [inventory_id]

Arguments:
  host_name      Name of the host record in AWX
  ansible_host   IP address or DNS name AWX/Ansible will connect to
  inventory_id   Optional AWX inventory ID (overrides AWX_INVENTORY_ID)

Required environment:
  AWX_URL              Base URL of AWX (e.g. https://awx.example.com)
  AWX_INVENTORY_ID     Target inventory ID (unless passed as 3rd argument)

Authentication (choose one):
  AWX_TOKEN            OAuth2 bearer token (recommended)
  AWX_USERNAME         Username for token login
  AWX_PASSWORD         Password for token login

Windows / WinRM environment (optional):
  WINRM_USER                       Default: Administrator
  WINRM_PASSWORD                   WinRM password
  WINRM_PORT                       Default: 5985
  WINRM_TRANSPORT                  Default: ntlm
  WINRM_SCHEME                     Default: http
  WINRM_SERVER_CERT_VALIDATION     Default: ignore

Other:
  AWX_VERIFY_SSL       Set to "false" to skip TLS verification (default: true)

Examples:
  export AWX_URL="https://awx.example.com"
  export AWX_TOKEN="abc123"
  export AWX_INVENTORY_ID="5"
  export WINRM_PASSWORD="Secret123!"
  ./add-awx-windows-host.sh dc01 10.0.0.10

  export AWX_USERNAME="admin"
  export AWX_PASSWORD="awxpass"
  ./add-awx-windows-host.sh web01 win-web01.corp.local 7
EOF
}

die() {
  echo "ERROR: $*" >&2
  exit 1
}

need_cmd() {
  command -v "$1" >/dev/null 2>&1 || die "Required command not found: $1"
}

curl_common_args=()
if [[ "${AWX_VERIFY_SSL}" == "false" ]]; then
  curl_common_args+=(-k)
fi

api_request() {
  local method="$1"
  local path="$2"
  local data="${3:-}"
  local url="${AWX_URL%/}${path}"

  if [[ -n "$data" ]]; then
    curl -sS "${curl_common_args[@]}" -X "$method" \
      -H "Content-Type: application/json" \
      -H "Authorization: Bearer ${AWX_TOKEN}" \
      -d "$data" "$url"
  else
    curl -sS "${curl_common_args[@]}" -X "$method" \
      -H "Content-Type: application/json" \
      -H "Authorization: Bearer ${AWX_TOKEN}" \
      "$url"
  fi
}

json_get() {
  local json="$1"
  local key="$2"

  if command -v jq >/dev/null 2>&1; then
    echo "$json" | jq -r "$key // empty"
  else
    echo "$json" | sed -n "s/.*\"${key//./\\.}\"[[:space:]]*:[[:space:]]*\"\\([^\"]*\\)\".*/\\1/p" | head -n1
  fi
}

get_awx_token() {
  [[ -n "$AWX_USERNAME" && -n "$AWX_PASSWORD" ]] || \
    die "Set AWX_TOKEN or both AWX_USERNAME and AWX_PASSWORD"

  local response token
  response="$(
    curl -sS "${curl_common_args[@]}" \
      -X POST \
      -H "Content-Type: application/json" \
      -d "{\"username\":\"${AWX_USERNAME}\",\"password\":\"${AWX_PASSWORD}\"}" \
      "${AWX_URL%/}/api/v2/tokens/"
  )"

  token="$(json_get "$response" "token")"
  [[ -n "$token" ]] || die "Failed to obtain AWX token: $response"
  AWX_TOKEN="$token"
}

build_host_variables() {
  local ansible_host="$1"

  cat <<EOF
ansible_connection: winrm
ansible_host: ${ansible_host}
ansible_user: ${WINRM_USER}
ansible_port: ${WINRM_PORT}
ansible_winrm_transport: ${WINRM_TRANSPORT}
ansible_winrm_scheme: ${WINRM_SCHEME}
ansible_winrm_server_cert_validation: ${WINRM_SERVER_CERT_VALIDATION}
EOF

  if [[ -n "$WINRM_PASSWORD" ]]; then
    printf 'ansible_password: "%s"\n' "${WINRM_PASSWORD//\"/\\\"}"
  fi
}

build_create_payload() {
  local host_name="$1"
  local variables="$2"

  if command -v jq >/dev/null 2>&1; then
    jq -n \
      --arg name "$host_name" \
      --arg variables "$variables" \
      '{name: $name, variables: $variables, enabled: true}'
    return
  fi

  for py in python3 python py; do
    if command -v "$py" >/dev/null 2>&1; then
      HOST_NAME="$host_name" HOST_VARS="$variables" "$py" - <<'PY'
import json, os
print(json.dumps({
    "name": os.environ["HOST_NAME"],
    "variables": os.environ["HOST_VARS"],
    "enabled": True,
}))
PY
      return
    fi
  done

  die "Install jq or Python to build the AWX API payload"
}

host_exists() {
  local inventory_id="$1"
  local host_name="$2"
  local response count

  response="$(api_request GET "/api/v2/inventories/${inventory_id}/hosts/?name=${host_name}")"

  if command -v jq >/dev/null 2>&1; then
    count="$(echo "$response" | jq -r '.count // 0')"
    [[ "$count" -gt 0 ]]
  else
    echo "$response" | grep -q "\"name\"[[:space:]]*:[[:space:]]*\"${host_name}\""
  fi
}

create_host() {
  local inventory_id="$1"
  local host_name="$2"
  local ansible_host="$3"
  local variables payload response host_id

  variables="$(build_host_variables "$ansible_host")"
  payload="$(build_create_payload "$host_name" "$variables")"
  response="$(api_request POST "/api/v2/inventories/${inventory_id}/hosts/" "$payload")"

  host_id="$(json_get "$response" "id")"
  if [[ -z "$host_id" ]]; then
    if command -v jq >/dev/null 2>&1; then
      die "AWX API error: $(echo "$response" | jq -r '.detail // .')"
    fi
    die "AWX API error: $response"
  fi

  echo "Created host '${host_name}' (ID: ${host_id}) in inventory ${inventory_id}"
}

# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------
main() {
  if [[ "${1:-}" == "-h" || "${1:-}" == "--help" ]]; then
    usage
    exit 0
  fi

  local host_name="${1:-}"
  local ansible_host="${2:-}"
  local inventory_id="${3:-$AWX_INVENTORY_ID}"

  [[ -n "$host_name" && -n "$ansible_host" ]] || { usage; exit 1; }
  [[ -n "$AWX_URL" ]] || die "AWX_URL is required"
  [[ -n "$inventory_id" ]] || die "AWX_INVENTORY_ID or inventory_id argument is required"

  need_cmd curl

  if [[ -z "$AWX_TOKEN" ]]; then
    get_awx_token
  fi

  if host_exists "$inventory_id" "$host_name"; then
    die "Host '${host_name}' already exists in inventory ${inventory_id}"
  fi

  create_host "$inventory_id" "$host_name" "$ansible_host"
}

main "$@"
