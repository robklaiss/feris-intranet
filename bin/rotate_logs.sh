#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
LOG_FILE="${ROOT_DIR}/storage/logs/app.log"
MAX_SIZE_MB="${1:-10}"
KEEP_ARCHIVES="${KEEP_ARCHIVES:-7}"

mkdir -p "$(dirname "${LOG_FILE}")"
touch "${LOG_FILE}"

CURRENT_SIZE_MB="$(du -m "${LOG_FILE}" | cut -f1)"

if (( CURRENT_SIZE_MB < MAX_SIZE_MB )); then
    echo "Sin rotación. Tamaño actual: ${CURRENT_SIZE_MB}MB"
    exit 0
fi

STAMP="$(date +"%Y%m%d_%H%M%S")"
ROTATED="${ROOT_DIR}/storage/logs/app-${STAMP}.log"

mv "${LOG_FILE}" "${ROTATED}"
touch "${LOG_FILE}"

mapfile -t OLD_LOGS < <(find "${ROOT_DIR}/storage/logs" -maxdepth 1 -name 'app-*.log' -type f | sort -r)

if ((${#OLD_LOGS[@]} > KEEP_ARCHIVES)); then
    for old_log in "${OLD_LOGS[@]:KEEP_ARCHIVES}"; do
        rm -f "${old_log}"
    done
fi

echo "Rotado en ${ROTATED}"
