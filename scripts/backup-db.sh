#!/bin/bash
# ==============================================================================
# eConstruction Supply - Automated Database Backup Script
# ==============================================================================
set -euo pipefail

BACKUP_DIR="/root/backups/econstructionsite/daily"
LOG_FILE="/root/backups/econstructionsite/backup.log"
TIMESTAMP=$(date +'%Y%m%d_%H%M%S')
BACKUP_FILE="${BACKUP_DIR}/ecomDB_daily_${TIMESTAMP}.sql.gz"

mkdir -p "${BACKUP_DIR}"

log() {
    echo "[$(date +'%Y-%m-%d %H:%M:%S')] $1" | tee -a "${LOG_FILE}"
}

log "Starting automated database backup..."

if ! docker ps --format '{{.Names}}' | grep -q '^econstructionsite-db$'; then
    log "ERROR: Container econstructionsite-db is not running!"
    exit 1
fi

docker exec econstructionsite-db pg_dump -U ecom_admin ecomDB | gzip > "${BACKUP_FILE}"

FILE_SIZE=$(stat -c%s "${BACKUP_FILE}" 2>/dev/null || stat -f%z "${BACKUP_FILE}" 2>/dev/null || wc -c < "${BACKUP_FILE}")
if [ "${FILE_SIZE}" -lt 1000 ]; then
    log "ERROR: Backup file ${BACKUP_FILE} is suspiciously small (${FILE_SIZE} bytes)!"
    exit 1
fi

log "SUCCESS: Backup created: ${BACKUP_FILE} (${FILE_SIZE} bytes)"

# Retention: Delete daily backups older than 14 days
DELETED=$(find "${BACKUP_DIR}" -type f -name 'ecomDB_daily_*.sql.gz' -mtime +14 -print -delete | wc -l)
log "Retention policy applied: removed ${DELETED} backups older than 14 days."
