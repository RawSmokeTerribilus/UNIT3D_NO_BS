#!/bin/bash

################################################################################
# UNIT3D Disaster Recovery Script
# Senior DevOps Engineer - Docker/Permissions Fix
################################################################################

set -e

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${PROJECT_ROOT}/.env"
COMPOSE_FILE="${PROJECT_ROOT}/docker-compose.yml"
LOG_FILE="${PROJECT_ROOT}/recovery.log"

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

################################################################################
# Logging
################################################################################

log() {
    echo -e "${BLUE}[$(date '+%Y-%m-%d %H:%M:%S')]${NC} $1" | tee -a "$LOG_FILE"
}

log_success() {
    echo -e "${GREEN}[✓]${NC} $1" | tee -a "$LOG_FILE"
}

log_error() {
    echo -e "${RED}[✗]${NC} $1" | tee -a "$LOG_FILE"
}

log_warn() {
    echo -e "${YELLOW}[!]${NC} $1" | tee -a "$LOG_FILE"
}

################################################################################
# PHASE 0: Validation
################################################################################

phase_validation() {
    log "═══════════════════════════════════════════════════════════════"
    log "PHASE 0: Validating environment..."
    log "═══════════════════════════════════════════════════════════════"
    
    if [[ ! -f "$COMPOSE_FILE" ]]; then
        log_error "docker-compose.yml not found at: $COMPOSE_FILE"
        exit 1
    fi
    log_success "docker-compose.yml found"
    
    if [[ ! -f "$ENV_FILE" ]]; then
        log_error ".env not found at: $ENV_FILE"
        exit 1
    fi
    log_success ".env found"
    
    if ! command -v docker &> /dev/null; then
        log_error "Docker CLI not found"
        exit 1
    fi
    log_success "Docker CLI available"
    
    # Quick non-blocking check
    if ! timeout 5 docker ps &> /dev/null; then
        log_error "Cannot connect to Docker daemon (sudo needed?)"
        exit 1
    fi
    log_success "Docker daemon accessible"
}

################################################################################
# PHASE 1: Detect DB Service Name
################################################################################

phase_detect_db_service() {
    log ""
    log "═══════════════════════════════════════════════════════════════"
    log "PHASE 1: Detecting database service..."
    log "═══════════════════════════════════════════════════════════════"
    
    # Extract service name from docker-compose.yml (first service with mysql/mariadb image)
    DB_SERVICE=$(grep -B 5 "image.*mysql\|image.*mariadb" "$COMPOSE_FILE" | grep "^\s\s[a-z]" | head -1 | xargs)
    
    if [[ -z "$DB_SERVICE" ]]; then
        log_error "Could not auto-detect database service from docker-compose.yml"
        log_warn "Falling back to 'db' as service name"
        DB_SERVICE="db"
    fi
    
    log_success "Database service detected: ${YELLOW}${DB_SERVICE}${NC}"
    
    # Parse env to get current DB_HOST
    CURRENT_DB_HOST=$(grep "^DB_HOST=" "$ENV_FILE" | cut -d'=' -f2 | tr -d '"')
    log "Current DB_HOST in .env: ${YELLOW}${CURRENT_DB_HOST}${NC}"
    
    # Update .env if needed
    if [[ "$CURRENT_DB_HOST" != "$DB_SERVICE" ]]; then
        log_warn "DB_HOST mismatch! Updating .env..."
        sed -i.bak "s/^DB_HOST=.*/DB_HOST=$DB_SERVICE/" "$ENV_FILE"
        log_success "Updated DB_HOST to: ${YELLOW}${DB_SERVICE}${NC}"
    else
        log_success "DB_HOST is correct"
    fi
}

################################################################################
# PHASE 2: Detect and Fix Volume Permissions
################################################################################

phase_fix_volume_perms() {
    log ""
    log "═══════════════════════════════════════════════════════════════"
    log "PHASE 2: Detecting and fixing volume permissions..."
    log "═══════════════════════════════════════════════════════════════"
    
    # MySQL permissions
    log "Processing MySQL volumes..."
    MYSQL_VOLUMES=$(grep -A 20 "^\s*db:" "$COMPOSE_FILE" 2>/dev/null | grep "volumes:" -A 5 | grep -E "\s+\-\s+\." | head -1 | sed 's/.*\.\///' | sed 's/:.*//g' || echo "")
    
    if [[ -z "$MYSQL_VOLUMES" ]]; then
        MYSQL_VOLUMES=".docker/data/mysql"
    fi
    
    MYSQL_PATH="${PROJECT_ROOT}/${MYSQL_VOLUMES}"
    
    if [[ -d "$MYSQL_PATH" ]]; then
        log "MySQL data directory: ${YELLOW}${MYSQL_PATH}${NC}"
        if [[ -n "$(ls -A "$MYSQL_PATH" 2>/dev/null)" ]]; then
            log "Current permissions:"
            ls -lnd "$MYSQL_PATH" 2>&1 | tee -a "$LOG_FILE"
            
            log "Applying MySQL permissions (27:27)..."
            sudo chown -R 27:27 "$MYSQL_PATH" 2>&1 | head -5 >> "$LOG_FILE" || log_error "Failed to chown MySQL directory"
            log_success "MySQL permissions fixed"
        else
            log_warn "MySQL directory exists but is empty"
        fi
    else
        log_warn "MySQL data directory not found: ${MYSQL_PATH}"
    fi
    
    # Redis permissions
    log ""
    log "Processing Redis volumes..."
    REDIS_VOLUMES=$(grep -A 20 "^\s*redis:" "$COMPOSE_FILE" 2>/dev/null | grep "volumes:" -A 3 | grep -E "\s+\-\s+\." | head -1 | sed 's/.*\.\///' | sed 's/:.*//g' || echo "")
    
    if [[ -z "$REDIS_VOLUMES" ]]; then
        REDIS_VOLUMES=".docker/data/redis"
    fi
    
    REDIS_PATH="${PROJECT_ROOT}/${REDIS_VOLUMES}"
    
    if [[ -d "$REDIS_PATH" ]]; then
        log "Redis data directory: ${YELLOW}${REDIS_PATH}${NC}"
        if [[ -n "$(ls -A "$REDIS_PATH" 2>/dev/null)" ]]; then
            log "Current permissions:"
            ls -lnd "$REDIS_PATH" 2>&1 | tee -a "$LOG_FILE"
            
            log "Applying Redis permissions (999:999)..."
            sudo chown -R 999:999 "$REDIS_PATH" 2>&1 | head -5 >> "$LOG_FILE" || log_error "Failed to chown Redis directory"
            log_success "Redis permissions fixed"
        else
            log_warn "Redis directory exists but is empty"
        fi
    else
        log_warn "Redis data directory not found: ${REDIS_PATH}"
    fi
    
    # Laravel directories
    log ""
    log "Processing Laravel directories..."
    
    log "Fixing storage permissions..."
    if [[ -d "${PROJECT_ROOT}/storage" ]]; then
        sudo chown -R 33:33 "${PROJECT_ROOT}/storage" 2>&1 | head -3 >> "$LOG_FILE" || sudo chmod -R 755 "${PROJECT_ROOT}/storage" 2>&1 | head -3 >> "$LOG_FILE"
        sudo chmod -R 775 "${PROJECT_ROOT}/storage" 2>&1 | head -3 >> "$LOG_FILE"
        log_success "Storage permissions fixed"
    fi
    
    log "Fixing bootstrap/cache permissions..."
    if [[ -d "${PROJECT_ROOT}/bootstrap/cache" ]]; then
        sudo chown -R 33:33 "${PROJECT_ROOT}/bootstrap/cache" 2>&1 | head -3 >> "$LOG_FILE" || sudo chmod -R 755 "${PROJECT_ROOT}/bootstrap/cache" 2>&1 | head -3 >> "$LOG_FILE"
        sudo chmod -R 775 "${PROJECT_ROOT}/bootstrap/cache" 2>&1 | head -3 >> "$LOG_FILE"
        log_success "Bootstrap cache permissions fixed"
    fi
}

################################################################################
# PHASE 3: Clean Laravel Cache and Views
################################################################################

phase_clean_laravel() {
    log ""
    log "═══════════════════════════════════════════════════════════════"
    log "PHASE 3: Cleaning Laravel cache and views..."
    log "═══════════════════════════════════════════════════════════════"
    
    CACHE_DIR="${PROJECT_ROOT}/storage/framework/cache/data"
    VIEWS_DIR="${PROJECT_ROOT}/storage/framework/views"
    
    if [[ -d "$CACHE_DIR" ]]; then
        log "Clearing cache directory: ${YELLOW}${CACHE_DIR}${NC}"
        sudo rm -rf "${CACHE_DIR}"/* 2>/dev/null || log_warn "Could not fully clear cache"
        log_success "Cache cleared"
    else
        log_warn "Cache directory not found"
    fi
    
    if [[ -d "$VIEWS_DIR" ]]; then
        log "Clearing compiled views: ${YELLOW}${VIEWS_DIR}${NC}"
        sudo rm -rf "${VIEWS_DIR}"/*.php 2>/dev/null || log_warn "Could not fully clear views"
        log_success "Views cleared"
    else
        log_warn "Views directory not found"
    fi
}

################################################################################
# PHASE 4: Reset Docker Network
################################################################################

phase_docker_reset() {
    log ""
    log "═══════════════════════════════════════════════════════════════"
    log "PHASE 4: Resetting Docker environment..."
    log "═══════════════════════════════════════════════════════════════"
    
    cd "$PROJECT_ROOT"
    
    log "Stopping containers..."
    timeout 60 docker compose down --remove-orphans 2>&1 | tee -a "$LOG_FILE"
    local down_status=$?
    if [[ $down_status -eq 0 ]] || [[ $down_status -eq 124 ]]; then
        log_success "Containers stopped"
    else
        log_warn "docker compose down had issues (status: $down_status)"
    fi
    
    log "Waiting 2 seconds before rebuild..."
    sleep 2
    
    log "Building and starting containers..."
    timeout 120 docker compose up -d 2>&1 | tee -a "$LOG_FILE"
    local up_status=$?
    if [[ $up_status -eq 0 ]]; then
        log_success "Containers started"
    elif [[ $up_status -eq 124 ]]; then
        log_error "docker compose up timed out after 120 seconds"
        return 1
    else
        log_error "docker compose up failed with status: $up_status"
        return 1
    fi
    
    log "Waiting 3 seconds for services to initialize..."
    sleep 3
    
    log "Checking container health..."
    docker compose ps 2>&1 | tee -a "$LOG_FILE"
}

################################################################################
# PHASE 5: Git Cleanup
################################################################################

phase_git_cleanup() {
    log ""
    log "═══════════════════════════════════════════════════════════════"
    log "PHASE 5: Cleaning Git state..."
    log "═══════════════════════════════════════════════════════════════"
    
    cd "$PROJECT_ROOT"
    
    # Disable fileMode changes
    log "Disabling Git fileMode tracking..."
    git config core.fileMode false
    log_success "core.fileMode set to false"
    
    # Ensure vendor is ignored
    if [[ -f ".gitignore" ]]; then
        if ! grep -q "^vendor/" .gitignore; then
            log "Adding vendor/ to .gitignore..."
            echo "vendor/" >> .gitignore
            log_success "vendor/ added to .gitignore"
        fi
        
        if ! grep -q "^public/vendor/" .gitignore; then
            log "Adding public/vendor/ to .gitignore..."
            echo "public/vendor/" >> .gitignore
            log_success "public/vendor/ added to .gitignore"
        fi
    fi
    
    # Remove files tracking fileMode changes
    log "Removing fileMode noise from Git index..."
    git diff --name-only --diff-filter=M | xargs -r git update-index --chmod=-x 2>/dev/null || true
    
    log "Current Git status:"
    git status --short | head -20 | tee -a "$LOG_FILE"
}

################################################################################
# PHASE 6: Verification
################################################################################

phase_verify() {
    log ""
    log "═══════════════════════════════════════════════════════════════"
    log "PHASE 6: Verification..."
    log "═══════════════════════════════════════════════════════════════"
    
    cd "$PROJECT_ROOT"
    
    # Check containers
    log "Checking container status..."
    timeout 10 docker compose ps 2>&1 | tee -a "$LOG_FILE"
    
    # Check DB connectivity with timeout
    log ""
    log "Testing database connectivity..."
    timeout 15 docker compose exec -T db mysqladmin ping -p'* ***REDACTED*** *' 2>&1 | tee -a "$LOG_FILE" && \
        log_success "Database is responding" || \
        log_warn "Database connectivity check failed or timed out"
    
    # Check Redis connectivity with timeout
    log ""
    log "Testing Redis connectivity..."
    timeout 15 docker compose exec -T redis redis-cli ping 2>&1 | tee -a "$LOG_FILE" && \
        log_success "Redis is responding" || \
        log_warn "Redis connectivity check failed or timed out"
    
    # Check Laravel with timeout
    log ""
    log "Testing Laravel artisan..."
    timeout 15 docker compose exec -T app php artisan --version 2>&1 | head -3 | tee -a "$LOG_FILE" || \
        log_warn "Artisan test had issues or timed out"
}

################################################################################
# Main Execution
################################################################################

main() {
    log ""
    log "╔════════════════════════════════════════════════════════════════╗"
    log "║         UNIT3D DISASTER RECOVERY - Senior DevOps Mode         ║"
    log "║                   Starting at $(date '+%Y-%m-%d %H:%M:%S')                       ║"
    log "╚════════════════════════════════════════════════════════════════╝"
    log ""
    log "Project Root: ${YELLOW}${PROJECT_ROOT}${NC}"
    log "Log File: ${YELLOW}${LOG_FILE}${NC}"
    log ""
    
    phase_validation
    phase_detect_db_service
    phase_fix_volume_perms
    phase_clean_laravel
    phase_docker_reset
    phase_git_cleanup
    phase_verify
    
    log ""
    log "╔════════════════════════════════════════════════════════════════╗"
    log "║                    RECOVERY COMPLETE ✓                         ║"
    log "║                 All critical systems restored                  ║"
    log "╚════════════════════════════════════════════════════════════════╝"
    log ""
    log_success "Recovery script finished successfully"
    log "Review complete log at: ${YELLOW}${LOG_FILE}${NC}"
}

main "$@"
