#!/bin/bash
# ==============================================================================
# eConstruction Supply - Controlled Production Deployment Engine
# ==============================================================================
set -euo pipefail

APP_DIR="/root/eConstructionSite"
DEPLOY_LOG="${APP_DIR}/deployments.log"
BACKUP_DIR="/root/backups/econstructionsite/pre_deploy"
TIMESTAMP=$(date +'%Y%m%d_%H%M%S')
TARGET_REF="${1:-origin/master}"

mkdir -p "${BACKUP_DIR}"

log() {
    echo "[$(date +'%Y-%m-%d %H:%M:%S')] $1" | tee -a "${DEPLOY_LOG}"
}

log "=========================================================="
log "STARTING PRODUCTION DEPLOYMENT: Target ref: ${TARGET_REF}"
log "=========================================================="

cd "${APP_DIR}"

# 1. Pre-flight container health check
log "Step 1: Checking Docker container health..."
for container in econstructionsite-web econstructionsite-db hubit-web; do
    if ! docker ps --format '{{.Names}}' | grep -q "^${container}$"; then
        log "FATAL: Container ${container} is not running! Aborting deployment."
        exit 1
    fi
done
log "All required Docker containers are active."

# 2. Record current state & create pre-deployment backup
PREV_COMMIT=$(git rev-parse HEAD)
log "Step 2: Previous commit: ${PREV_COMMIT}"
log "Creating pre-deployment database backup..."
DB_BACKUP_FILE="${BACKUP_DIR}/ecomDB_pre_deploy_${TIMESTAMP}_${PREV_COMMIT:0:7}.sql.gz"
docker exec econstructionsite-db pg_dump -U ecom_admin ecomDB | gzip > "${DB_BACKUP_FILE}"
log "Pre-deployment database snapshot saved to: ${DB_BACKUP_FILE}"

# 3. Fetch latest changes from Git
log "Step 3: Fetching updates from origin..."
git fetch --tags origin

# Resolve target commit
TARGET_COMMIT=$(git rev-parse "${TARGET_REF}")
log "Resolved target commit: ${TARGET_COMMIT}"

if [ "${PREV_COMMIT}" = "${TARGET_COMMIT}" ]; then
    log "Notice: Production is already at target commit ${TARGET_COMMIT}. Proceeding with verification..."
else
    log "Checking out target commit ${TARGET_COMMIT}..."
    git checkout "${TARGET_COMMIT}"
fi

# 4. Run automated database migrations if migrations directory exists
log "Step 4: Checking for database migrations..."
MIGRATION_DIR="${APP_DIR}/database/migrations"
if [ -d "${MIGRATION_DIR}" ]; then
    # Create migrations table if not exists
    docker exec -i econstructionsite-db psql -U ecom_admin -d ecomDB -c "
        CREATE TABLE IF NOT EXISTS tbl_migrations (
            id SERIAL PRIMARY KEY,
            migration VARCHAR(255) UNIQUE NOT NULL,
            applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    " >/dev/null

    for migration_file in $(ls -1 "${MIGRATION_DIR}"/*.sql 2>/dev/null | sort); do
        M_NAME=$(basename "${migration_file}")
        APPLIED=$(docker exec -i econstructionsite-db psql -U ecom_admin -d ecomDB -t -A -c "SELECT count(*) FROM tbl_migrations WHERE migration = '${M_NAME}';")
        if [ "${APPLIED}" = "0" ]; then
            log "Applying migration: ${M_NAME}..."
            docker exec -i econstructionsite-db psql -U ecom_admin -d ecomDB < "${migration_file}"
            docker exec -i econstructionsite-db psql -U ecom_admin -d ecomDB -c "INSERT INTO tbl_migrations (migration) VALUES ('${M_NAME}');"
            log "Migration ${M_NAME} applied successfully."
        fi
    done
else
    log "No migrations directory found. Skipping migrations."
fi

# 5. Verify filesystem permissions
log "Step 5: Verifying file and upload permissions..."
chmod -R 775 "${APP_DIR}/assets/uploads" 2>/dev/null || true

# 6. Post-deployment Health Check
log "Step 6: Running health checks..."
sleep 2

HTTP_LOCAL=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:9090/ || echo "000")
HTTP_PUBLIC=$(curl -s -k -o /dev/null -w "%{http_code}" https://econstruction-supply.site/ || echo "000")

log "Health check response: Local port 9090: [${HTTP_LOCAL}], Public domain: [${HTTP_PUBLIC}]"

if [ "${HTTP_LOCAL}" != "200" ] && [ "${HTTP_LOCAL}" != "302" ]; then
    log "HEALTH CHECK FAILED on local port 9090 (Status: ${HTTP_LOCAL})! INITIATING AUTOMATIC ROLLBACK..."
    git checkout "${PREV_COMMIT}"
    log "Rolled back codebase to: ${PREV_COMMIT}"
    exit 1
fi

log "SUCCESS: Deployment completed successfully."
log "Current Version: ${TARGET_COMMIT}"
log "Previous Version: ${PREV_COMMIT}"
log "=========================================================="
