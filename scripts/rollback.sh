#!/bin/bash
# ==============================================================================
# eConstruction Supply - Emergency Rollback Engine
# ==============================================================================
set -euo pipefail

APP_DIR="/root/eConstructionSite"
DEPLOY_LOG="${APP_DIR}/deployments.log"
TARGET_COMMIT="${1:-}"

cd "${APP_DIR}"

log() {
    echo "[$(date +'%Y-%m-%d %H:%M:%S')] $1" | tee -a "${DEPLOY_LOG}"
}

log "=========================================================="
log "INITIATING EMERGENCY ROLLBACK"
log "=========================================================="

CURRENT_COMMIT=$(git rev-parse HEAD)
log "Current commit: ${CURRENT_COMMIT}"

if [ -z "${TARGET_COMMIT}" ]; then
    # Parse previous version from deployment log or git reflog
    PREV_COMMIT=$(git rev-parse HEAD~1)
    log "No target specified. Defaulting to previous commit: ${PREV_COMMIT}"
    TARGET_COMMIT="${PREV_COMMIT}"
fi

log "Rolling back codebase to: ${TARGET_COMMIT}..."
git checkout "${TARGET_COMMIT}"

# Health check
HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:9090/ || echo "000")
log "Rollback health check (port 9090): ${HTTP_STATUS}"

if [ "${HTTP_STATUS}" = "200" ] || [ "${HTTP_STATUS}" = "302" ]; then
    log "SUCCESS: Rollback to ${TARGET_COMMIT} verified and operational."
else
    log "WARNING: Rollback completed but health check returned status: ${HTTP_STATUS}"
fi
log "=========================================================="
