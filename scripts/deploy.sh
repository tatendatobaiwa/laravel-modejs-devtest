#!/bin/bash

# Production Deployment Script for Salary Management System
# This script automates the deployment process with safety checks and rollback capabilities

set -e

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
BACKUP_DIR="$PROJECT_ROOT/backups"
LOG_FILE="$PROJECT_ROOT/deployment.log"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging function
log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1" | tee -a "$LOG_FILE"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1" | tee -a "$LOG_FILE"
    exit 1
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1" | tee -a "$LOG_FILE"
}

success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1" | tee -a "$LOG_FILE"
}

# Check prerequisites
check_prerequisites() {
    log "Checking prerequisites..."
    
    # Check if Docker is installed and running
    if ! command -v docker &> /dev/null; then
        error "Docker is not installed. Please install Docker first."
    fi
    
    if ! docker info &> /dev/null; then
        error "Docker is not running. Please start Docker service."
    fi
    
    # Check if Docker Compose is installed
    if ! command -v docker-compose &> /dev/null; then
        error "Docker Compose is not installed. Please install Docker Compose first."
    fi
    
    # Check if Git is installed
    if ! command -v git &> /dev/null; then
        error "Git is not installed. Please install Git first."
    fi
    
    # Check if we're in the project root
    if [[ ! -f "$PROJECT_ROOT/backend/docker-compose.prod.yml" ]]; then
        error "Backend docker-compose.prod.yml not found. Are you in the project root?"
    fi
    
    if [[ ! -f "$PROJECT_ROOT/frontend/docker-compose.prod.yml" ]]; then
        error "Frontend docker-compose.prod.yml not found. Are you in the project root?"
    fi
    
    success "Prerequisites check passed"
}

# Create backup before deployment
create_backup() {
    log "Creating backup before deployment..."
    
    mkdir -p "$BACKUP_DIR"
    
    # Backup database
    if docker-compose -f "$PROJECT_ROOT/backend/docker-compose.prod.yml" ps | grep -q "salary-db-prod"; then
        log "Backing up database..."
        docker-compose -f "$PROJECT_ROOT/backend/docker-compose.prod.yml" exec -T db mysqldump -u root -p"${DB_ROOT_PASSWORD}" "${DB_DATABASE}" > "$BACKUP_DIR/db_backup_${TIMESTAMP}.sql"
        gzip "$BACKUP_DIR/db_backup_${TIMESTAMP}.sql"
        success "Database backup created: db_backup_${TIMESTAMP}.sql.gz"
    else
        warning "Database container not running, skipping database backup"
    fi
    
    # Backup application files
    log "Backing up application files..."
    tar -czf "$BACKUP_DIR/app_backup_${TIMESTAMP}.tar.gz" -C "$PROJECT_ROOT" --exclude=node_modules --exclude=vendor --exclude=.git .
    success "Application backup created: app_backup_${TIMESTAMP}.tar.gz"
}

# Pull latest code
pull_code() {
    log "Pulling latest code from repository..."
    
    cd "$PROJECT_ROOT"
    
    # Stash any local changes
    if [[ -n $(git status --porcelain) ]]; then
        warning "Local changes detected, stashing them..."
        git stash push -m "Auto-stash before deployment ${TIMESTAMP}"
    fi
    
    # Pull latest changes
    git pull origin main || error "Failed to pull latest code"
    
    success "Code updated successfully"
}

# Build and deploy backend
deploy_backend() {
    log "Deploying backend..."
    
    cd "$PROJECT_ROOT/backend"
    
    # Check if .env file exists
    if [[ ! -f ".env" ]]; then
        error "Backend .env file not found. Please create it from .env.production template."
    fi
    
    # Build and start services
    log "Building and starting backend services..."
    docker-compose -f docker-compose.prod.yml pull
    docker-compose -f docker-compose.prod.yml build --no-cache
    docker-compose -f docker-compose.prod.yml up -d
    
    # Wait for services to be ready
    log "Waiting for backend services to be ready..."
    sleep 30
    
    # Run database migrations
    log "Running database migrations..."
    docker-compose -f docker-compose.prod.yml exec -T app php artisan migrate --force || error "Database migration failed"
    
    # Clear and cache configuration
    log "Optimizing backend application..."
    docker-compose -f docker-compose.prod.yml exec -T app php artisan config:cache
    docker-compose -f docker-compose.prod.yml exec -T app php artisan route:cache
    docker-compose -f docker-compose.prod.yml exec -T app php artisan view:cache
    
    success "Backend deployed successfully"
}

# Build and deploy frontend
deploy_frontend() {
    log "Deploying frontend..."
    
    cd "$PROJECT_ROOT/frontend"
    
    # Check if .env.local file exists
    if [[ ! -f ".env.local" ]]; then
        error "Frontend .env.local file not found. Please create it from .env.production template."
    fi
    
    # Create network if it doesn't exist
    docker network create salary-network 2>/dev/null || true
    
    # Build and start services
    log "Building and starting frontend services..."
    docker-compose -f docker-compose.prod.yml pull
    docker-compose -f docker-compose.prod.yml build --no-cache
    docker-compose -f docker-compose.prod.yml up -d
    
    success "Frontend deployed successfully"
}

# Health check
health_check() {
    log "Performing health checks..."
    
    # Wait for services to be fully ready
    sleep 60
    
    # Check backend health
    log "Checking backend health..."
    for i in {1..10}; do
        if curl -f -k "https://localhost/api/health" &>/dev/null; then
            success "Backend health check passed"
            break
        elif [[ $i -eq 10 ]]; then
            error "Backend health check failed after 10 attempts"
        else
            log "Backend health check attempt $i failed, retrying in 10 seconds..."
            sleep 10
        fi
    done
    
    # Check frontend health
    log "Checking frontend health..."
    for i in {1..10}; do
        if curl -f -k "https://localhost/health" &>/dev/null; then
            success "Frontend health check passed"
            break
        elif [[ $i -eq 10 ]]; then
            error "Frontend health check failed after 10 attempts"
        else
            log "Frontend health check attempt $i failed, retrying in 10 seconds..."
            sleep 10
        fi
    done
    
    success "All health checks passed"
}

# Rollback function
rollback() {
    local backup_timestamp=$1
    
    if [[ -z "$backup_timestamp" ]]; then
        error "Backup timestamp required for rollback"
    fi
    
    warning "Starting rollback to backup: $backup_timestamp"
    
    # Stop current services
    log "Stopping current services..."
    docker-compose -f "$PROJECT_ROOT/backend/docker-compose.prod.yml" down
    docker-compose -f "$PROJECT_ROOT/frontend/docker-compose.prod.yml" down
    
    # Restore database
    if [[ -f "$BACKUP_DIR/db_backup_${backup_timestamp}.sql.gz" ]]; then
        log "Restoring database..."
        gunzip -c "$BACKUP_DIR/db_backup_${backup_timestamp}.sql.gz" | docker-compose -f "$PROJECT_ROOT/backend/docker-compose.prod.yml" exec -T db mysql -u root -p"${DB_ROOT_PASSWORD}" "${DB_DATABASE}"
        success "Database restored"
    fi
    
    # Restore application files
    if [[ -f "$BACKUP_DIR/app_backup_${backup_timestamp}.tar.gz" ]]; then
        log "Restoring application files..."
        cd "$PROJECT_ROOT"
        tar -xzf "$BACKUP_DIR/app_backup_${backup_timestamp}.tar.gz"
        success "Application files restored"
    fi
    
    # Restart services
    log "Restarting services..."
    deploy_backend
    deploy_frontend
    
    success "Rollback completed successfully"
}

# Cleanup old backups
cleanup_backups() {
    log "Cleaning up old backups..."
    
    # Keep last 10 backups
    find "$BACKUP_DIR" -name "db_backup_*.sql.gz" -type f | sort -r | tail -n +11 | xargs -r rm
    find "$BACKUP_DIR" -name "app_backup_*.tar.gz" -type f | sort -r | tail -n +11 | xargs -r rm
    
    success "Old backups cleaned up"
}

# Main deployment function
deploy() {
    log "Starting deployment process..."
    
    check_prerequisites
    create_backup
    pull_code
    deploy_backend
    deploy_frontend
    health_check
    cleanup_backups
    
    success "Deployment completed successfully!"
    log "Deployment log saved to: $LOG_FILE"
    log "Backup created: $TIMESTAMP"
}

# Parse command line arguments
case "${1:-deploy}" in
    "deploy")
        deploy
        ;;
    "rollback")
        if [[ -z "$2" ]]; then
            echo "Usage: $0 rollback <backup_timestamp>"
            echo "Available backups:"
            ls -la "$BACKUP_DIR" | grep backup
            exit 1
        fi
        rollback "$2"
        ;;
    "health")
        health_check
        ;;
    "backup")
        create_backup
        ;;
    *)
        echo "Usage: $0 {deploy|rollback <timestamp>|health|backup}"
        echo ""
        echo "Commands:"
        echo "  deploy              - Full deployment process"
        echo "  rollback <timestamp> - Rollback to specific backup"
        echo "  health              - Run health checks"
        echo "  backup              - Create backup only"
        exit 1
        ;;
esac