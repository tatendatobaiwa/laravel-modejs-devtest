#!/bin/bash

# Health Check Script for Salary Management System
# Comprehensive system health monitoring

set -e

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

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
    echo -e "${GREEN}[✓]${NC} $1"
}

error() {
    echo -e "${RED}[✗]${NC} $1"
}

warning() {
    echo -e "${YELLOW}[!]${NC} $1"
}

# Health check results
HEALTH_ISSUES=0

# Function to increment health issues
add_issue() {
    ((HEALTH_ISSUES++))
}

# 1. System Health Checks
log "=== System Health Checks ==="

# Check disk space
DISK_USAGE=$(df / | awk 'NR==2 {print $5}' | sed 's/%//')
if [[ $DISK_USAGE -lt 80 ]]; then
    success "Disk usage: ${DISK_USAGE}% (OK)"
else
    error "Disk usage: ${DISK_USAGE}% (HIGH - Consider cleanup)"
    add_issue
fi

# Check memory usage
MEMORY_USAGE=$(free | awk 'NR==2{printf "%.0f", $3*100/$2}')
if [[ $MEMORY_USAGE -lt 80 ]]; then
    success "Memory usage: ${MEMORY_USAGE}% (OK)"
else
    warning "Memory usage: ${MEMORY_USAGE}% (HIGH)"
fi

# Check load average
LOAD_AVG=$(uptime | awk -F'load average:' '{print $2}' | awk '{print $1}' | sed 's/,//')
LOAD_THRESHOLD=2.0
if (( $(echo "$LOAD_AVG < $LOAD_THRESHOLD" | bc -l) )); then
    success "Load average: $LOAD_AVG (OK)"
else
    warning "Load average: $LOAD_AVG (HIGH)"
fi

# 2. Docker Health Checks
log "=== Docker Health Checks ==="

# Check if Docker is running
if systemctl is-active --quiet docker; then
    success "Docker service is running"
else
    error "Docker service is not running"
    add_issue
fi

# Check Docker daemon
if docker info &>/dev/null; then
    success "Docker daemon is accessible"
else
    error "Docker daemon is not accessible"
    add_issue
fi

# 3. Container Health Checks
log "=== Container Health Checks ==="

# Backend containers
BACKEND_CONTAINERS=("salary-app-prod" "salary-nginx-prod" "salary-db-prod" "salary-redis-prod")
for container in "${BACKEND_CONTAINERS[@]}"; do
    if docker ps --format "table {{.Names}}" | grep -q "$container"; then
        # Check container health
        HEALTH_STATUS=$(docker inspect --format='{{.State.Health.Status}}' "$container" 2>/dev/null || echo "no-health-check")
        if [[ "$HEALTH_STATUS" == "healthy" ]]; then
            success "Container $container is healthy"
        elif [[ "$HEALTH_STATUS" == "no-health-check" ]]; then
            # Check if container is running
            if docker ps --format "table {{.Names}}" | grep -q "$container"; then
                success "Container $container is running (no health check)"
            else
                error "Container $container is not running"
                add_issue
            fi
        else
            error "Container $container health status: $HEALTH_STATUS"
            add_issue
        fi
    else
        error "Container $container is not running"
        add_issue
    fi
done

# Frontend containers
FRONTEND_CONTAINERS=("salary-frontend-prod" "salary-frontend-nginx-prod")
for container in "${FRONTEND_CONTAINERS[@]}"; do
    if docker ps --format "table {{.Names}}" | grep -q "$container"; then
        HEALTH_STATUS=$(docker inspect --format='{{.State.Health.Status}}' "$container" 2>/dev/null || echo "no-health-check")
        if [[ "$HEALTH_STATUS" == "healthy" ]]; then
            success "Container $container is healthy"
        elif [[ "$HEALTH_STATUS" == "no-health-check" ]]; then
            if docker ps --format "table {{.Names}}" | grep -q "$container"; then
                success "Container $container is running (no health check)"
            else
                error "Container $container is not running"
                add_issue
            fi
        else
            error "Container $container health status: $HEALTH_STATUS"
            add_issue
        fi
    else
        warning "Container $container is not running (frontend may not be deployed)"
    fi
done

# 4. Application Health Checks
log "=== Application Health Checks ==="

# Backend API health check
log "Checking backend API health..."
if curl -f -s -k "https://localhost/api/health" >/dev/null 2>&1; then
    success "Backend API is responding"
    
    # Get detailed health info
    BACKEND_HEALTH=$(curl -s -k "https://localhost/api/health" | jq -r '.status' 2>/dev/null || echo "unknown")
    if [[ "$BACKEND_HEALTH" == "healthy" ]]; then
        success "Backend API reports healthy status"
    else
        warning "Backend API status: $BACKEND_HEALTH"
    fi
else
    error "Backend API is not responding"
    add_issue
fi

# Frontend health check
log "Checking frontend health..."
if curl -f -s -k "https://localhost/health" >/dev/null 2>&1; then
    success "Frontend is responding"
    
    # Get detailed health info
    FRONTEND_HEALTH=$(curl -s -k "https://localhost/health" | jq -r '.status' 2>/dev/null || echo "unknown")
    if [[ "$FRONTEND_HEALTH" == "healthy" ]]; then
        success "Frontend reports healthy status"
    else
        warning "Frontend status: $FRONTEND_HEALTH"
    fi
else
    error "Frontend is not responding"
    add_issue
fi

# 5. Database Health Checks
log "=== Database Health Checks ==="

if docker ps --format "table {{.Names}}" | grep -q "salary-db-prod"; then
    # Check database connectivity
    if docker-compose -f "$PROJECT_ROOT/backend/docker-compose.prod.yml" exec -T db mysql -u root -p"${DB_ROOT_PASSWORD:-root}" -e "SELECT 1;" &>/dev/null; then
        success "Database is accessible"
        
        # Check database size
        DB_SIZE=$(docker-compose -f "$PROJECT_ROOT/backend/docker-compose.prod.yml" exec -T db mysql -u root -p"${DB_ROOT_PASSWORD:-root}" -e "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS 'DB Size in MB' FROM information_schema.tables WHERE table_schema='${DB_DATABASE:-salary_management_prod}';" 2>/dev/null | tail -n 1)
        success "Database size: ${DB_SIZE} MB"
        
        # Check for slow queries
        SLOW_QUERIES=$(docker-compose -f "$PROJECT_ROOT/backend/docker-compose.prod.yml" exec -T db mysql -u root -p"${DB_ROOT_PASSWORD:-root}" -e "SHOW GLOBAL STATUS LIKE 'Slow_queries';" 2>/dev/null | tail -n 1 | awk '{print $2}')
        if [[ $SLOW_QUERIES -gt 10 ]]; then
            warning "Slow queries detected: $SLOW_QUERIES"
        else
            success "Slow queries: $SLOW_QUERIES (OK)"
        fi
    else
        error "Database is not accessible"
        add_issue
    fi
else
    error "Database container is not running"
    add_issue
fi

# 6. Redis Health Checks
log "=== Redis Health Checks ==="

if docker ps --format "table {{.Names}}" | grep -q "salary-redis-prod"; then
    # Check Redis connectivity
    if docker-compose -f "$PROJECT_ROOT/backend/docker-compose.prod.yml" exec -T redis redis-cli ping | grep -q "PONG"; then
        success "Redis is accessible"
        
        # Check Redis memory usage
        REDIS_MEMORY=$(docker-compose -f "$PROJECT_ROOT/backend/docker-compose.prod.yml" exec -T redis redis-cli info memory | grep "used_memory_human" | cut -d: -f2 | tr -d '\r')
        success "Redis memory usage: $REDIS_MEMORY"
        
        # Check Redis connected clients
        REDIS_CLIENTS=$(docker-compose -f "$PROJECT_ROOT/backend/docker-compose.prod.yml" exec -T redis redis-cli info clients | grep "connected_clients" | cut -d: -f2 | tr -d '\r')
        success "Redis connected clients: $REDIS_CLIENTS"
    else
        error "Redis is not accessible"
        add_issue
    fi
else
    error "Redis container is not running"
    add_issue
fi

# 7. SSL Certificate Checks
log "=== SSL Certificate Checks ==="

# Check SSL certificate expiration
if command -v openssl &> /dev/null; then
    CERT_EXPIRY=$(echo | openssl s_client -servername localhost -connect localhost:443 2>/dev/null | openssl x509 -noout -dates | grep notAfter | cut -d= -f2)
    if [[ -n "$CERT_EXPIRY" ]]; then
        EXPIRY_DATE=$(date -d "$CERT_EXPIRY" +%s)
        CURRENT_DATE=$(date +%s)
        DAYS_UNTIL_EXPIRY=$(( (EXPIRY_DATE - CURRENT_DATE) / 86400 ))
        
        if [[ $DAYS_UNTIL_EXPIRY -gt 30 ]]; then
            success "SSL certificate expires in $DAYS_UNTIL_EXPIRY days"
        elif [[ $DAYS_UNTIL_EXPIRY -gt 7 ]]; then
            warning "SSL certificate expires in $DAYS_UNTIL_EXPIRY days (renewal recommended)"
        else
            error "SSL certificate expires in $DAYS_UNTIL_EXPIRY days (urgent renewal required)"
            add_issue
        fi
    else
        warning "Could not check SSL certificate expiration"
    fi
else
    warning "OpenSSL not available for certificate checks"
fi

# 8. Log Health Checks
log "=== Log Health Checks ==="

# Check log file sizes
LOG_DIRS=("$PROJECT_ROOT/backend/storage/logs" "/var/log/nginx")
for log_dir in "${LOG_DIRS[@]}"; do
    if [[ -d "$log_dir" ]]; then
        LOG_SIZE=$(du -sh "$log_dir" 2>/dev/null | cut -f1)
        success "Log directory $log_dir size: $LOG_SIZE"
        
        # Check for large log files (>100MB)
        LARGE_LOGS=$(find "$log_dir" -name "*.log" -size +100M 2>/dev/null | wc -l)
        if [[ $LARGE_LOGS -gt 0 ]]; then
            warning "Found $LARGE_LOGS log files larger than 100MB in $log_dir"
        fi
    fi
done

# Check for recent errors in application logs
if [[ -d "$PROJECT_ROOT/backend/storage/logs" ]]; then
    RECENT_ERRORS=$(find "$PROJECT_ROOT/backend/storage/logs" -name "*.log" -mtime -1 -exec grep -l "ERROR\|CRITICAL" {} \; 2>/dev/null | wc -l)
    if [[ $RECENT_ERRORS -gt 0 ]]; then
        warning "Found recent errors in $RECENT_ERRORS log files"
    else
        success "No recent errors found in application logs"
    fi
fi

# 9. Backup Health Checks
log "=== Backup Health Checks ==="

BACKUP_DIR="$PROJECT_ROOT/backups"
if [[ -d "$BACKUP_DIR" ]]; then
    # Check for recent backups
    RECENT_BACKUPS=$(find "$BACKUP_DIR" -name "db_backup_*.sql.gz" -mtime -1 | wc -l)
    if [[ $RECENT_BACKUPS -gt 0 ]]; then
        success "Found $RECENT_BACKUPS recent database backups"
    else
        warning "No recent database backups found (last 24 hours)"
    fi
    
    # Check backup directory size
    BACKUP_SIZE=$(du -sh "$BACKUP_DIR" 2>/dev/null | cut -f1)
    success "Backup directory size: $BACKUP_SIZE"
else
    warning "Backup directory not found"
fi

# 10. Performance Checks
log "=== Performance Checks ==="

# Check response times
BACKEND_RESPONSE_TIME=$(curl -o /dev/null -s -w '%{time_total}' -k "https://localhost/api/health" 2>/dev/null || echo "0")
if (( $(echo "$BACKEND_RESPONSE_TIME < 2.0" | bc -l) )); then
    success "Backend response time: ${BACKEND_RESPONSE_TIME}s (OK)"
else
    warning "Backend response time: ${BACKEND_RESPONSE_TIME}s (SLOW)"
fi

FRONTEND_RESPONSE_TIME=$(curl -o /dev/null -s -w '%{time_total}' -k "https://localhost/health" 2>/dev/null || echo "0")
if (( $(echo "$FRONTEND_RESPONSE_TIME < 2.0" | bc -l) )); then
    success "Frontend response time: ${FRONTEND_RESPONSE_TIME}s (OK)"
else
    warning "Frontend response time: ${FRONTEND_RESPONSE_TIME}s (SLOW)"
fi

# 11. Security Checks
log "=== Security Checks ==="

# Check for security headers
SECURITY_HEADERS=("X-Frame-Options" "X-Content-Type-Options" "X-XSS-Protection" "Strict-Transport-Security")
for header in "${SECURITY_HEADERS[@]}"; do
    if curl -s -I -k "https://localhost" | grep -qi "$header"; then
        success "Security header $header is present"
    else
        warning "Security header $header is missing"
    fi
done

# Check for exposed sensitive files
SENSITIVE_PATHS=("/.env" "/config" "/.git")
for path in "${SENSITIVE_PATHS[@]}"; do
    if curl -s -k "https://localhost$path" | grep -q "404\|403\|Not Found\|Forbidden"; then
        success "Sensitive path $path is protected"
    else
        error "Sensitive path $path may be exposed"
        add_issue
    fi
done

# 12. Summary
log "=== Health Check Summary ==="

if [[ $HEALTH_ISSUES -eq 0 ]]; then
    success "All health checks passed! System is healthy."
    exit 0
else
    error "Found $HEALTH_ISSUES health issues that need attention."
    echo ""
    echo "Recommendations:"
    echo "1. Review the errors and warnings above"
    echo "2. Check application logs for more details"
    echo "3. Monitor system resources"
    echo "4. Consider running maintenance tasks"
    echo ""
    echo "For detailed troubleshooting, see: DEPLOYMENT.md#troubleshooting"
    exit 1
fi