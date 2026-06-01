#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKUP_ROOT="${ROOT_DIR}/storage/backups"
COMPRESS=0

while (($# > 0)); do
    case "$1" in
        --zip)
            COMPRESS=1
            shift
            ;;
        --dest)
            BACKUP_ROOT="$2"
            shift 2
            ;;
        *)
            echo "Uso: $0 [--zip] [--dest /ruta/backups]" >&2
            exit 1
            ;;
    esac
done

DB_PATH="$(php -r 'require "'"${ROOT_DIR}"'/bootstrap/app.php"; echo config("database.connections.sqlite.database");')"
TIMESTAMP="$(date +"%Y%m%d_%H%M%S")"
BUNDLE_DIR="${BACKUP_ROOT}/${TIMESTAMP}"

mkdir -p "${BUNDLE_DIR}/storage"
cp "${DB_PATH}" "${BUNDLE_DIR}/app.sqlite"

for folder in exports prints; do
    if [[ -d "${ROOT_DIR}/storage/${folder}" ]]; then
        mkdir -p "${BUNDLE_DIR}/storage/${folder}"
        cp -R "${ROOT_DIR}/storage/${folder}/." "${BUNDLE_DIR}/storage/${folder}/" 2>/dev/null || true
    fi
done

cat > "${BUNDLE_DIR}/manifest.txt" <<EOF
timestamp=${TIMESTAMP}
db_path=${DB_PATH}
app_name=Industria Feris CRM
includes=app.sqlite,storage/exports,storage/prints
EOF

if [[ "${COMPRESS}" -eq 1 ]]; then
    (
        cd "${BACKUP_ROOT}"
        zip -qr "${TIMESTAMP}.zip" "${TIMESTAMP}"
    )
    rm -rf "${BUNDLE_DIR}"
    echo "${BACKUP_ROOT}/${TIMESTAMP}.zip"
    exit 0
fi

echo "${BUNDLE_DIR}"
