#!/usr/bin/env bash
# Convenience wrapper: single-command local install from project tree
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
exec bash "${ROOT}/install.sh"
