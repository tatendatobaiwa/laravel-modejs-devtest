#!/bin/bash

# Backup Script for Salary Management System
# Creates comprehensive backups before updates

set -e

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
BACKUP_DIR="$PROJECT_ROOT/backups"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
RED='\033[0;31m'
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

# Create backup directory
mkdir -p "$BACKUP_DIR"

log "Starting comprehensive backup process..."

# Load environment variables
if [[ -f "$PROJECT_ROOT/backend/.env" ]]; then
    source "$PROJECT_ROOT/backend/.env"
else
    error "Backend .env file not found"
fi

# 1. Database backup
log "Creating database backup..."
if docker-compose -f "$PROJECT_ROOT/backend/docker-compose.prod.yml" ps | grep -q "salary-db-prod"; then
    docker-compose -f "$PROJECT_ROOT/backend/docker-compose.prod.yml" exec -T db mysqldump \
        -u root -p"${DB_ROOT_PASSWORD}" \
        --single-transaction \
        --routines \
        --triggers \
        "${DB_DATABASE}" > "$BACKUP_DIR/db_backup_${TIMESTAMP}.sql"
    
    gzip "$BACKUP_DIR/db_backup_${TIMESTAMP}.sql"
    success "Database backup created: db_backup_${TIMESTAMP}.sql.gz"
else
    log "Database container not running, skipping database backup"
fi

# 2. Application files backup
log "Creating application files backup..."
tar -czf "$BACKUP_DIR/app_backup_${TIMESTAMP}.tar.gz" \
    -C "$PROJECT_ROOT" \
    --exclude=node_modules \
    --exclude=vendor \
    --exclude=.git \
    --exclude=backups \
    --exclude=storage/logs \
    --exclude=.next \
    .

success "Application files backup created: app_backup_${TIMESTAMP}.tar.gz"

# 3. Docker volumes backup
log "Creating Docker volumes backup..."
if docker volume ls | grep -q "salary"; then
    mkdir -p "$BACKUP_DIR/volumes_${TIMESTAMP}"
    
    # Backup database volume
    if docker volume ls | grep -q "backend_dbdata"; then
        docker run --rm -v backend_dbdata:/data -v "$BACKUP_DIR/volumes_${TIMESTAMP}":/backup alpine \
            tar -czf /backup/dbdata.tar.gz -C /data .
        success "Database volume backed up"
    fi
    
    # Backup Redis volume
    if docker volume ls | grep -q "backend_redisdata"; then
        docker run --rm -v backend_redisdata:/data -v "$BACKUP_DIR/volumes_${TIMESTAMP}":/backup alpine \
            tar -czf /backup/redisdata.tar.gz -C /data .
        success "Redis volume backed up"
    fi
    
    # Backup application storage volume
    if docker volume ls | grep -q "backend_app-storage"; then
        docker run --rm -v backend_app-storage:/data -v "$BACKUP_DIR/volumes_${TIMESTAMP}":/backup alpine \
            tar -czf /backup/app-storage.tar.gz -C /data .
        success "Application storage volume backed up"
    fi
fi

# 4. Configuration backup
log "Creating configuration backup..."
mkdir -p "$BACKUP_DIR/config_${TIMESTAMP}"

# Copy environment files
cp "$PROJECT_ROOT/backend/.env" "$BACKUP_DIR/config_${TIMESTAMP}/backend.env" 2>/dev/null || true
cp "$PROJECT_ROOT/frontend/.env.local" "$BACKUP_DIR/config_${TIMESTAMP}/frontend.env" 2>/dev/null || true

# Copy Docker configurations
cp -r "$PROJECT_ROOT/backend/docker" "$BACKUP_DIR/config_${TIMESTAMP}/" 2>/dev/null || true
cp -r "$PROJECT_ROOT/frontend/docker" "$BACKUP_DIR/config_${TIMESTAMP}/frontend-docker" 2>/dev/null || true

success "Configuration backup created"

# 5. SSL certificates backup (if they exist)
log "Backing up SSL certificates..."
if [[ -d "/etc/letsencrypt/live" ]]; then
    sudo tar -czf "$BACKUP_DIR/ssl_backup_${TIMESTAMP}.tar.gz" -C /etc/letsencrypt .
    success "SSL certificates backed up"
else
    log "No SSL certificates found to backup"
fi

# 6. Create backup manifest
log "Creating backup manifest..."
cat > "$BACKUP_DIR/manifest_${TIMESTAMP}.txt" << EOF
Backup Manifest
===============
Timestamp: ${TIMESTAMP}
Date: $(date)
Git Commit: $(git rev-parse HEAD 2>/dev/null || echo "N/A")
Git Branch: $(git branch --show-current 2>/dev/null || echo "N/A")

Files Created:
- db_backup_${TIMESTAMP}.sql.gz (Database backup)
- app_backup_${TIMESTAMP}.tar.gz (Application files)
- volumes_${TIMESTAMP}/ (Docker volumes)
- config_${TIMESTAMP}/ (Configuration files)
- ssl_backup_${TIMESTAMP}.tar.gz (SSL certificates)

Backup Size:
$(du -sh "$BACKUP_DIR"/*${TIMESTAMP}* 2>/dev/null || echo "Size calculation failed")

System Information:
- OS: $(uname -a)
- Docker Version: $(docker --version)
- Docker Compose Version: $(docker-compose --version)
- Disk Space: $(df -h /)

Environment Variables (Backend):
$(grep -E '^(APP_|DB_|REDIS_)' "$PROJECT_ROOT/backend/.env" 2>/dev/null | sed 's/=.*/=***/' || echo "Could not read environment")
EOF

success "Backup manifest created: manifest_${TIMESTAMP}.txt"

# 7. Verify backup integrity
log "Verifying backup integrity..."

# Check if backup files exist and are not empty
for file in "db_backup_${TIMESTAMP}.sql.gz" "app_backup_${TIMESTAMP}.tar.gz"; do
    if [[ -f "$BACKUP_DIR/$file" && -s "$BACKUP_DIR/$file" ]]; then
        success "✓ $file is valid"
    else
        error "✗ $file is missing or empty"
    fi
done

# Test database backup integrity
if [[ -f "$BACKUP_DIR/db_backup_${TIMESTAMP}.sql.gz" ]]; then
    if gunzip -t "$BACKUP_DIR/db_backup_${TIMESTAMP}.sql.gz" 2>/dev/null; then
        success "✓ Database backup integrity verified"
    else
        error "✗ Database backup is corrupted"
    fi
fi

# Test application backup integrity
if [[ -f "$BACKUP_DIR/app_backup_${TIMESTAMP}.tar.gz" ]]; then
    if tar -tzf "$BACKUP_DIR/app_backup_${TIMESTAMP}.tar.gz" >/dev/null 2>&1; then
        success "✓ Application backup integrity verified"
    else
        error "✗ Application backup is corrupted"
    fi
fi

# 8. Clean up old backups (keep last 10)
log "Cleaning up old backups..."
find "$BACKUP_DIR" -name "db_backup_*.sql.gz" -type f | sort -r | tail -n +11 | xargs -r rm
find "$BACKUP_DIR" -name "app_backup_*.tar.gz" -type f | sort -r | tail -n +11 | xargs -r rm
find "$BACKUP_DIR" -name "ssl_backup_*.tar.gz" -type f | sort -r | tail -n +11 | xargs -r rm
find "$BACKUP_DIR" -maxdepth 1 -name "volumes_*" -type d | sort -r | tail -n +11 | xargs -r rm -rf
find "$BACKUP_DIR" -maxdepth 1 -name "config_*" -type d | sort -r | tail -n +11 | xargs -r rm -rf
find "$BACKUP_DIR" -name "manifest_*.txt" -type f | sort -r | tail -n +11 | xargs -r rm

success "Old backups cleaned up (keeping last 10)"

# 9. Display backup summary
log "Backup Summary:"
echo "==============="
echo "Backup Timestamp: $TIMESTAMP"
echo "Backup Location: $BACKUP_DIR"
echo "Total Backup Size: $(du -sh "$BACKUP_DIR" | cut -f1)"
echo ""
echo "Created Files:"
ls -lah "$BACKUP_DIR"/*${TIMESTAMP}* 2>/dev/null || echo "No files found"
echo ""
echo "Available Backups:"
ls -la "$BACKUP_DIR" | grep backup | tail -5

success "Comprehensive backup completed successfully!"
echo ""
echo "To restore from this backup, use:"
echo "  ./scripts/restore-from-backup.sh $TIMESTAMP"