#!/usr/bin/env bash
set -euo pipefail

if (($# < 1)); then
    echo "Uso: $0 /ruta/al/backup_dir|backup.zip" >&2
    exit 1
fi

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SOURCE_PATH="$1"
DB_PATH="$(php -r 'require "'"${ROOT_DIR}"'/bootstrap/app.php"; echo config("database.connections.sqlite.database");')"
RESTORE_TS="$(date +"%Y%m%d_%H%M%S")"
TEMP_DIR=""

cleanup() {
    if [[ -n "${TEMP_DIR}" && -d "${TEMP_DIR}" ]]; then
        rm -rf "${TEMP_DIR}"
    fi
}
trap cleanup EXIT

if [[ -f "${SOURCE_PATH}" && "${SOURCE_PATH}" == *.zip ]]; then
    TEMP_DIR="$(mktemp -d)"
    unzip -q "${SOURCE_PATH}" -d "${TEMP_DIR}"
    SOURCE_PATH="$(find "${TEMP_DIR}" -mindepth 1 -maxdepth 1 -type d | head -n 1)"
fi

if [[ ! -d "${SOURCE_PATH}" || ! -f "${SOURCE_PATH}/app.sqlite" ]]; then
    echo "Backup inválido. Se esperaba un directorio con app.sqlite." >&2
    exit 1
fi

mkdir -p "$(dirname "${DB_PATH}")"

if [[ -f "${DB_PATH}" ]]; then
    cp "${DB_PATH}" "${DB_PATH}.before_restore_${RESTORE_TS}"
fi

cp "${SOURCE_PATH}/app.sqlite" "${DB_PATH}"

for folder in exports prints; do
    if [[ -d "${SOURCE_PATH}/storage/${folder}" ]]; then
        if [[ -d "${ROOT_DIR}/storage/${folder}" ]]; then
            mv "${ROOT_DIR}/storage/${folder}" "${ROOT_DIR}/storage/${folder}.before_restore_${RESTORE_TS}"
        fi

        mkdir -p "${ROOT_DIR}/storage/${folder}"
        cp -R "${SOURCE_PATH}/storage/${folder}/." "${ROOT_DIR}/storage/${folder}/" 2>/dev/null || true
    fi
done

echo "Restore completo desde ${SOURCE_PATH}"
