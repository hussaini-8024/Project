#!/usr/bin/env bash
#
# Register the MS Office 2016 Ansible GitHub project in AWX and create a job
# template for automatic LAN deployment.
#
# Creates (or updates):
#   1. AWX Project  -> GitHub SCM link in Projects menu
#   2. Job Template -> playbook playbooks/install-office2016.yml
#   3. Schedule     -> optional recurring automatic runs
#
# Requirements: curl, jq (recommended)
#
# Usage:
#   export AWX_URL="https://awx.example.com"
#   export AWX_TOKEN="your-token"
#   export AWX_ORGANIZATION_ID="1"
#   export AWX_INVENTORY_ID="5"
#   ./scripts/add-awx-office2016-project.sh

set -euo pipefail

# ---------------------------------------------------------------------------
# Defaults
# ---------------------------------------------------------------------------
AWX_URL="${AWX_URL:-}"
AWX_TOKEN="${AWX_TOKEN:-}"
AWX_USERNAME="${AWX_USERNAME:-}"
AWX_PASSWORD="${AWX_PASSWORD:-}"
AWX_VERIFY_SSL="${AWX_VERIFY_SSL:-true}"

AWX_ORGANIZATION_ID="${AWX_ORGANIZATION_ID:-}"
AWX_INVENTORY_ID="${AWX_INVENTORY_ID:-}"
AWX_EXECUTION_ENVIRONMENT_ID="${AWX_EXECUTION_ENVIRONMENT_ID:-}"

PROJECT_NAME="${PROJECT_NAME:-MS Office 2016 LAN Install}"
PROJECT_SCM_URL="${PROJECT_SCM_URL:-https://github.com/hussaini-8024/Project.git}"
PROJECT_SCM_BRANCH="${PROJECT_SCM_BRANCH:-main}"

JOB_TEMPLATE_NAME="${JOB_TEMPLATE_NAME:-Install MS Office 2016}"
JOB_PLAYBOOK="${JOB_PLAYBOOK:-playbooks/install-office2016.yml}"

# Cron schedule for automatic LAN runs (empty = skip schedule creation)
AWX_SCHEDULE_CRON="${AWX_SCHEDULE_CRON:-0 2 * * 0}"
AWX_SCHEDULE_NAME="${AWX_SCHEDULE_NAME:-Weekly Office 2016 LAN Install}"
AWX_SCHEDULE_ENABLED="${AWX_SCHEDULE_ENABLED:-true}"

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------
usage() {
  cat <<EOF
Register MS Office 2016 GitHub project in Ansible AWX.

Usage:
  add-awx-office2016-project.sh

Required environment:
  AWX_URL                 AWX base URL
  AWX_ORGANIZATION_ID     AWX organization ID
  AWX_INVENTORY_ID        Inventory with Windows LAN hosts

Authentication (choose one):
  AWX_TOKEN               OAuth2 bearer token (recommended)
  AWX_USERNAME            Username for token login
  AWX_PASSWORD            Password for token login

Optional:
  PROJECT_SCM_URL         GitHub repo URL (default: ${PROJECT_SCM_URL})
  PROJECT_SCM_BRANCH      Git branch (default: main)
  AWX_EXECUTION_ENVIRONMENT_ID   Execution environment ID
  AWX_SCHEDULE_CRON       Cron for auto runs (default: 0 2 * * 0 = Sunday 2 AM)
                          Set empty to skip schedule creation
  AWX_VERIFY_SSL          Set to "false" to skip TLS verification

Example:
  export AWX_URL="https://awx.example.com"
  export AWX_TOKEN="abc123"
  export AWX_ORGANIZATION_ID="1"
  export AWX_INVENTORY_ID="5"
  ./scripts/add-awx-office2016-project.sh
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

json_get() {
  local json="$1"
  local filter="$2"
  if command -v jq >/dev/null 2>&1; then
    echo "$json" | jq -r "$filter // empty"
  else
    die "jq is required for this script"
  fi
}

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

get_awx_token() {
  [[ -n "$AWX_USERNAME" && -n "$AWX_PASSWORD" ]] || \
    die "Set AWX_TOKEN or both AWX_USERNAME and AWX_PASSWORD"

  local response token
  response="$(curl -sS "${curl_common_args[@]}" \
    -X POST \
    -H "Content-Type: application/json" \
    -d "{\"username\":\"${AWX_USERNAME}\",\"password\":\"${AWX_PASSWORD}\"}" \
    "${AWX_URL%/}/api/v2/tokens/")"

  token="$(json_get "$response" ".token")"
  [[ -n "$token" ]] || die "Failed to obtain AWX token: $(json_get "$response" ".detail // .")"
  AWX_TOKEN="$token"
}

find_by_name() {
  local endpoint="$1"
  local name="$2"
  local encoded response
  encoded="$(python3 -c "import urllib.parse, sys; print(urllib.parse.quote(sys.argv[1]))" "$name")"
  response="$(api_request GET "${endpoint}?name=${encoded}")"
  json_get "$response" ".results[0].id // empty"
}

create_or_update_project() {
  local existing_id payload response project_id

  existing_id="$(find_by_name "/api/v2/projects/" "$PROJECT_NAME")"

  payload="$(jq -n \
    --arg name "$PROJECT_NAME" \
    --argjson org "$AWX_ORGANIZATION_ID" \
    --arg url "$PROJECT_SCM_URL" \
    --arg branch "$PROJECT_SCM_BRANCH" \
    '{
      name: $name,
      organization: $org,
      scm_type: "git",
      scm_url: $url,
      scm_branch: $branch,
      scm_clean: true,
      scm_delete_on_update: false,
      scm_update_on_launch: true,
      allow_override: true,
      description: "GitHub project: silent MS Office 2016 install over LAN via WinRM"
    }')"

  if [[ -n "$existing_id" ]]; then
    echo "Updating existing AWX project '${PROJECT_NAME}' (ID: ${existing_id})..." >&2
    response="$(api_request PATCH "/api/v2/projects/${existing_id}/" "$payload")"
  else
    echo "Creating AWX project '${PROJECT_NAME}'..." >&2
    response="$(api_request POST "/api/v2/projects/" "$payload")"
  fi

  project_id="$(json_get "$response" ".id")"
  [[ -n "$project_id" ]] || die "Failed to create/update project: $(json_get "$response" ".detail // .")"

  echo "Syncing project from GitHub..." >&2
  api_request POST "/api/v2/projects/${project_id}/update/" "{}" >/dev/null || true

  echo "$project_id"
}

create_or_update_job_template() {
  local project_id="$1"
  local existing_id payload response template_id

  existing_id="$(find_by_name "/api/v2/job_templates/" "$JOB_TEMPLATE_NAME")"

  payload="$(jq -n \
    --arg name "$JOB_TEMPLATE_NAME" \
    --argjson org "$AWX_ORGANIZATION_ID" \
    --argjson inventory "$AWX_INVENTORY_ID" \
    --argjson project "$project_id" \
    --arg playbook "$JOB_PLAYBOOK" \
    --argjson ee "${AWX_EXECUTION_ENVIRONMENT_ID:-null}" \
    '{
      name: $name,
      description: "Deploy MS Office 2016 to Windows LAN hosts automatically",
      job_type: "run",
      organization: $org,
      inventory: $inventory,
      project: $project,
      playbook: $playbook,
      verbosity: 1,
      ask_variables_on_launch: true,
      ask_credential_on_launch: true,
      extra_vars: "office2016_lan_source_path: \\\\fileserver\\software\\Office2016"
    } + (if $ee != null then {execution_environment: $ee} else {} end)')"

  if [[ -n "$existing_id" ]]; then
    echo "Updating job template '${JOB_TEMPLATE_NAME}' (ID: ${existing_id})..." >&2
    response="$(api_request PATCH "/api/v2/job_templates/${existing_id}/" "$payload")"
  else
    echo "Creating job template '${JOB_TEMPLATE_NAME}'..." >&2
    response="$(api_request POST "/api/v2/job_templates/" "$payload")"
  fi

  template_id="$(json_get "$response" ".id")"
  [[ -n "$template_id" ]] || die "Failed to create/update job template: $(json_get "$response" ".detail // .")"
  echo "$template_id"
}

create_schedule() {
  local template_id="$1"
  local existing_id payload response schedule_id

  [[ -n "$AWX_SCHEDULE_CRON" ]] || return 0

  existing_id="$(find_by_name "/api/v2/job_templates/${template_id}/schedules/" "$AWX_SCHEDULE_NAME")"

  payload="$(jq -n \
    --arg name "$AWX_SCHEDULE_NAME" \
    --argjson enabled "$( [[ "$AWX_SCHEDULE_ENABLED" == "true" ]] && echo true || echo false )" \
    '{
      name: $name,
      description: "Automatic recurring Office 2016 LAN deployment",
      enabled: $enabled,
      rrule: "DTSTART:20260101T020000Z RRULE:FREQ=WEEKLY;BYDAY=SU",
      extra_data: {}
    }')"

  if [[ -n "$existing_id" ]]; then
    echo "Updating schedule '${AWX_SCHEDULE_NAME}'..." >&2
    response="$(api_request PATCH "/api/v2/schedules/${existing_id}/" "$payload")"
  else
    echo "Creating automatic schedule '${AWX_SCHEDULE_NAME}'..." >&2
    response="$(api_request POST "/api/v2/job_templates/${template_id}/schedules/" "$payload")"
  fi

  schedule_id="$(json_get "$response" ".id")"
  if [[ -n "$schedule_id" ]]; then
    echo "Schedule created/updated (ID: ${schedule_id})" >&2
  else
    echo "WARNING: Could not create schedule. Add manually in AWX UI if needed." >&2
  fi
}

# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------
main() {
  if [[ "${1:-}" == "-h" || "${1:-}" == "--help" ]]; then
    usage
    exit 0
  fi

  [[ -n "$AWX_URL" ]] || die "AWX_URL is required"
  [[ -n "$AWX_ORGANIZATION_ID" ]] || die "AWX_ORGANIZATION_ID is required"
  [[ -n "$AWX_INVENTORY_ID" ]] || die "AWX_INVENTORY_ID is required"

  need_cmd curl
  need_cmd jq
  need_cmd python3

  if [[ -z "$AWX_TOKEN" ]]; then
    get_awx_token
  fi

  local project_id template_id
  project_id="$(create_or_update_project)"
  template_id="$(create_or_update_job_template "$project_id")"
  create_schedule "$template_id"

  cat <<EOF

AWX setup complete.

  Project menu entry : ${PROJECT_NAME}
  GitHub SCM URL     : ${PROJECT_SCM_URL}
  Branch             : ${PROJECT_SCM_BRANCH}
  Project ID         : ${project_id}
  Job Template       : ${JOB_TEMPLATE_NAME}
  Job Template ID    : ${template_id}
  Playbook           : ${JOB_PLAYBOOK}

Open AWX -> Resources -> Projects to see the GitHub project.
Launch the job template to install Office 2016 on LAN Windows hosts.

EOF
}

main "$@"
