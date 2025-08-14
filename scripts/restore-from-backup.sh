#!/bin/bash

# Restore Script for Salary Management System
# Restores system from comprehensive backups

set -e

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
BACKUP_DIR="$PROJECT_ROOT/backups"

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
}

success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1"
    exit 1
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

# Check if backup timestamp is provided
if [[ -z "$1" ]]; then
    echo "Usage: $0 <backup_timestamp>"
    echo ""
    echo "Available backups:"
    ls -la "$BACKUP_DIR" | grep backup | awk '{print $9}' | grep -E '_[0-9]{8}_[0-9]{6}' | sed 's/.*_\([0-9]\{8\}_[0-9]\{6\}\).*/\1/' | sort -u
    exit 1
fi

BACKUP_TIMESTAMP="$1"

# Verify backup files exist
log "Verifying backup files for timestamp: $BACKUP_TIMESTAMP"

REQUIRED_FILES=(
    "db_backup_${BACKUP_TIMESTAMP}.sql.gz"
    "app_backup_${BACKUP_TIMESTAMP}.tar.gz"
    "manifest_${BACKUP_TIMESTAMP}.txt"
)

for file in "${REQUIRED_FILES[@]}"; do
    if [[ ! -f "$BACKUP_DIR/$file" ]]; then
        error "Required backup file not found: $file"
    fi
done

success "All required backup files found"

# Display backup information
log "Backup Information:"
echo "=================="
cat "$BACKUP_DIR/manifest_${BACKUP_TIMESTAMP}.txt"
echo "=================="

# Confirmation prompt
warning "This will restore the system to the state from backup: $BACKUP_TIMESTAMP"
warning "Current data will be OVERWRITTEN and LOST!"
echo ""
read -p "Are you sure you want to continue? (yes/no): " -r
if [[ ! $REPLY =~ ^[Yy][Ee][Ss]$ ]]; then
    log "Restore cancelled by user"
    exit 0
fi

# Create a backup of current state before restore
CURRENT_TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
log "Creating backup of current state before restore..."
"$SCRIPT_DIR/backup-before-update.sh" || warning "Failed to create current state backup"

# Stop all services
log "Stopping all services..."
docker-compose -f "$PROJECT_ROOT/backend/docker-compose.prod.yml" down 2>/dev/null || true
docker-compose -f "$PROJECT_ROOT/frontend/docker-compose.prod.yml" down 2>/dev/null || true

# Wait for services to stop
sleep 10

# 1. Restore application files
log "Restoring application files..."
cd "$PROJECT_ROOT"

# Create backup of current files
if [[ -d ".git" ]]; then
    log "Stashing current changes..."
    git stash push -m "Auto-stash before restore ${CURRENT_TIMESTAMP}" 2>/dev/null || true
fi

# Extract application backup
tar -xzf "$BACKUP_DIR/app_backup_${BACKUP_TIMESTAMP}.tar.gz"
success "Application files restored"

# 2. Restore configuration files
log "Restoring configuration files..."
if [[ -d "$BACKUP_DIR/config_${BACKUP_TIMESTAMP}" ]]; then
    # Restore backend environment
    if [[ -f "$BACKUP_DIR/config_${BACKUP_TIMESTAMP}/backend.env" ]]; then
        cp "$BACKUP_DIR/config_${BACKUP_TIMESTAMP}/backend.env" "$PROJECT_ROOT/backend/.env"
        success "Backend configuration restored"
    fi
    
    # Restore frontend environment
    if [[ -f "$BACKUP_DIR/config_${BACKUP_TIMESTAMP}/frontend.env" ]]; then
        cp "$BACKUP_DIR/config_${BACKUP_TIMESTAMP}/frontend.env" "$PROJECT_ROOT/frontend/.env.local"
        success "Frontend configuration restored"
    fi
    
    # Restore Docker configurations
    if [[ -d "$BACKUP_DIR/config_${BACKUP_TIMESTAMP}/docker" ]]; then
        cp -r "$BACKUP_DIR/config_${BACKUP_TIMESTAMP}/docker"/* "$PROJECT_ROOT/backend/docker/" 2>/dev/null || true
        success "Docker configurations restored"
    fi
fi

# 3. Start database service for restoration
log "Starting database service for restoration..."
cd "$PROJECT_ROOT/backend"
docker-compose -f docker-compose.prod.yml up -d db redis

# Wait for database to be ready
log "Waiting for database to be ready..."
sleep 30

# Check if database is accessible
for i in {1..10}; do
    if docker-compose -f docker-compose.prod.yml exec -T db mysql -u root -p"${DB_ROOT_PASSWORD:-root}" -e "SELECT 1;" &>/dev/null; then
        success "Database is ready"
        break
    elif [[ $i -eq 10 ]]; then
        error "Database failed to start after 10 attempts"
    else
        log "Database not ready, attempt $i/10, waiting 10 seconds..."
        sleep 10
    fi
done

# 4. Restore database
log "Restoring database..."
if [[ -f "$BACKUP_DIR/db_backup_${BACKUP_TIMESTAMP}.sql.gz" ]]; then
    # Drop and recreate database
    docker-compose -f docker-compose.prod.yml exec -T db mysql -u root -p"${DB_ROOT_PASSWORD:-root}" -e "DROP DATABASE IF EXISTS ${DB_DATABASE:-salary_management_prod};"
    docker-compose -f docker-compose.prod.yml exec -T db mysql -u root -p"${DB_ROOT_PASSWORD:-root}" -e "CREATE DATABASE ${DB_DATABASE:-salary_management_prod};"
    
    # Restore database from backup
    gunzip -c "$BACKUP_DIR/db_backup_${BACKUP_TIMESTAMP}.sql.gz" | \
        docker-compose -f docker-compose.prod.yml exec -T db mysql -u root -p"${DB_ROOT_PASSWORD:-root}" "${DB_DATABASE:-salary_management_prod}"
    
    success "Database restored successfully"
else
    warning "Database backup not found, skipping database restore"
fi

# 5. Restore Docker volumes
log "Restoring Docker volumes..."
if [[ -d "$BACKUP_DIR/volumes_${BACKUP_TIMESTAMP}" ]]; then
    # Stop services to restore volumes
    docker-compose -f docker-compose.prod.yml down
    
    # Restore database volume
    if [[ -f "$BACKUP_DIR/volumes_${BACKUP_TIMESTAMP}/dbdata.tar.gz" ]]; then
        docker run --rm -v backend_dbdata:/data -v "$BACKUP_DIR/volumes_${BACKUP_TIMESTAMP}":/backup alpine \
            sh -c "cd /data && tar -xzf /backup/dbdata.tar.gz"
        success "Database volume restored"
    fi
    
    # Restore Redis volume
    if [[ -f "$BACKUP_DIR/volumes_${BACKUP_TIMESTAMP}/redisdata.tar.gz" ]]; then
        docker run --rm -v backend_redisdata:/data -v "$BACKUP_DIR/volumes_${BACKUP_TIMESTAMP}":/backup alpine \
            sh -c "cd /data && tar -xzf /backup/redisdata.tar.gz"
        success "Redis volume restored"
    fi
    
    # Restore application storage volume
    if [[ -f "$BACKUP_DIR/volumes_${BACKUP_TIMESTAMP}/app-storage.tar.gz" ]]; then
        docker run --rm -v backend_app-storage:/data -v "$BACKUP_DIR/volumes_${BACKUP_TIMESTAMP}":/backup alpine \
            sh -c "cd /data && tar -xzf /backup/app-storage.tar.gz"
        success "Application storage volume restored"
    fi
fi

# 6. Restore SSL certificates
log "Restoring SSL certificates..."
if [[ -f "$BACKUP_DIR/ssl_backup_${BACKUP_TIMESTAMP}.tar.gz" ]]; then
    sudo tar -xzf "$BACKUP_DIR/ssl_backup_${BACKUP_TIMESTAMP}.tar.gz" -C /etc/letsencrypt/
    success "SSL certificates restored"
else
    warning "SSL certificates backup not found, skipping SSL restore"
fi

# 7. Start all services
log "Starting all services..."

# Start backend services
cd "$PROJECT_ROOT/backend"
docker-compose -f docker-compose.prod.yml up -d

# Wait for backend to be ready
sleep 30

# Start frontend services
cd "$PROJECT_ROOT/frontend"
docker network create salary-network 2>/dev/null || true
docker-compose -f docker-compose.prod.yml up -d

# 8. Verify restoration
log "Verifying restoration..."
sleep 60

# Check backend health
log "Checking backend health..."
for i in {1..10}; do
    if curl -f -k "https://localhost/api/health" &>/dev/null; then
        success "Backend is healthy"
        break
    elif [[ $i -eq 10 ]]; then
        error "Backend health check failed after restoration"
    else
        log "Backend health check attempt $i failed, retrying in 10 seconds..."
        sleep 10
    fi
done

# Check frontend health
log "Checking frontend health..."
for i in {1..10}; do
    if curl -f -k "https://localhost/health" &>/dev/null; then
        success "Frontend is healthy"
        break
    elif [[ $i -eq 10 ]]; then
        error "Frontend health check failed after restoration"
    else
        log "Frontend health check attempt $i failed, retrying in 10 seconds..."
        sleep 10
    fi
done

# 9. Run post-restoration tasks
log "Running post-restoration tasks..."

# Clear application caches
docker-compose -f "$PROJECT_ROOT/backend/docker-compose.prod.yml" exec -T app php artisan config:cache
docker-compose -f "$PROJECT_ROOT/backend/docker-compose.prod.yml" exec -T app php artisan route:cache
docker-compose -f "$PROJECT_ROOT/backend/docker-compose.prod.yml" exec -T app php artisan view:cache

success "Post-restoration tasks completed"

# 10. Display restoration summary
log "Restoration Summary:"
echo "==================="
echo "Restored from backup: $BACKUP_TIMESTAMP"
echo "Restoration completed at: $(date)"
echo "Current state backup created: $CURRENT_TIMESTAMP"
echo ""
echo "Services Status:"
docker-compose -f "$PROJECT_ROOT/backend/docker-compose.prod.yml" ps
echo ""
docker-compose -f "$PROJECT_ROOT/frontend/docker-compose.prod.yml" ps
echo ""

success "System restoration completed successfully!"
echo ""
echo "Please verify that all functionality is working correctly."
echo "If issues are found, you can restore to the pre-restoration state using:"
echo "  ./scripts/restore-from-backup.sh $CURRENT_TIMESTAMP"