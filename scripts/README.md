# Deployment Scripts

This directory contains automated deployment and maintenance scripts for the Salary Management System.

## Scripts Overview

### 1. `deploy.sh` - Main Deployment Script

Automates the complete deployment process with safety checks and rollback capabilities.

**Usage:**
```bash
./scripts/deploy.sh [command]
```

**Commands:**
- `deploy` (default) - Full deployment process
- `rollback <timestamp>` - Rollback to specific backup
- `health` - Run health checks only
- `backup` - Create backup only

**Features:**
- Prerequisites checking
- Automatic backup before deployment
- Code pulling from repository
- Backend and frontend deployment
- Health checks verification
- Rollback capabilities
- Cleanup of old backups

### 2. `backup-before-update.sh` - Comprehensive Backup Script

Creates complete system backups before updates or maintenance.

**Usage:**
```bash
./scripts/backup-before-update.sh
```

**What it backs up:**
- Database with full schema and data
- Application files (excluding node_modules, vendor, .git)
- Docker volumes (database, Redis, application storage)
- Configuration files (.env files, Docker configs)
- SSL certificates (if available)
- System metadata and manifest

**Features:**
- Integrity verification of backups
- Automatic cleanup of old backups
- Detailed backup manifest
- Compression for space efficiency

### 3. `restore-from-backup.sh` - System Restoration Script

Restores the complete system from backups with verification.

**Usage:**
```bash
./scripts/restore-from-backup.sh <backup_timestamp>
```

**Example:**
```bash
./scripts/restore-from-backup.sh 20241214_143022
```

**Features:**
- Backup verification before restoration
- Current state backup before restore
- Complete system restoration (database, files, volumes, configs)
- Health checks after restoration
- Rollback instructions if issues occur

### 4. `health-check.sh` - Comprehensive Health Monitoring

Performs detailed system health checks and monitoring.

**Usage:**
```bash
./scripts/health-check.sh
```

**Health Checks Include:**
- System resources (disk, memory, CPU, load)
- Docker service and daemon status
- Container health and status
- Application endpoints (backend API, frontend)
- Database connectivity and performance
- Redis connectivity and metrics
- SSL certificate expiration
- Log file sizes and recent errors
- Backup status and recency
- Response times and performance
- Security headers and protection

**Exit Codes:**
- `0` - All checks passed
- `1` - Issues found that need attention

## Prerequisites

### System Requirements
- Docker Engine 20.10+
- Docker Compose 2.0+
- Git
- curl
- jq (for JSON parsing)
- bc (for calculations)
- OpenSSL (for SSL checks)

### Permissions
Scripts need to be executable. On Unix systems:
```bash
chmod +x scripts/*.sh
chmod +x backend/docker/backup/backup.sh
```

### Environment Variables
Ensure the following environment variables are set in your `.env` files:
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`
- `DB_ROOT_PASSWORD`

## Usage Examples

### Initial Deployment
```bash
# 1. Clone repository and configure environment
git clone https://github.com/your-org/salary-management-system.git
cd salary-management-system

# 2. Configure environment files
cp backend/.env.production backend/.env
cp frontend/.env.production frontend/.env.local
# Edit the files with your configuration

# 3. Deploy
./scripts/deploy.sh
```

### Regular Updates
```bash
# 1. Create backup
./scripts/backup-before-update.sh

# 2. Deploy updates
./scripts/deploy.sh

# 3. Verify deployment
./scripts/health-check.sh
```

### Emergency Rollback
```bash
# 1. List available backups
ls -la backups/ | grep backup

# 2. Rollback to specific backup
./scripts/deploy.sh rollback 20241214_143022

# 3. Verify rollback
./scripts/health-check.sh
```

### Monitoring and Maintenance
```bash
# Daily health check
./scripts/health-check.sh

# Weekly backup
./scripts/backup-before-update.sh

# Check specific backup
./scripts/restore-from-backup.sh 20241214_143022 --dry-run
```

## Automation with Cron

### Daily Health Checks
```bash
# Add to crontab
0 8 * * * /path/to/salary-management-system/scripts/health-check.sh >> /var/log/salary-health.log 2>&1
```

### Weekly Backups
```bash
# Add to crontab
0 2 * * 0 /path/to/salary-management-system/scripts/backup-before-update.sh >> /var/log/salary-backup.log 2>&1
```

### SSL Certificate Monitoring
```bash
# Add to crontab - check SSL certificate daily
0 6 * * * /path/to/salary-management-system/scripts/health-check.sh | grep -i ssl >> /var/log/ssl-check.log 2>&1
```

## Logging

All scripts log their activities:
- **Deployment logs**: `deployment.log` in project root
- **Backup logs**: Console output (redirect to file if needed)
- **Health check logs**: Console output with color coding
- **System logs**: Check `/var/log/` for system-level logs

## Troubleshooting

### Common Issues

#### 1. Permission Denied
```bash
# Make scripts executable
chmod +x scripts/*.sh
```

#### 2. Docker Not Running
```bash
# Start Docker service
sudo systemctl start docker
sudo systemctl enable docker
```

#### 3. Environment Variables Missing
```bash
# Check environment files exist and are configured
ls -la backend/.env frontend/.env.local
```

#### 4. Network Issues
```bash
# Check Docker networks
docker network ls
docker network create salary-network
```

#### 5. Port Conflicts
```bash
# Check what's using ports 80, 443, 3000, 8000
netstat -tulpn | grep -E ':(80|443|3000|8000)'
```

### Debug Mode

Enable debug mode by setting environment variable:
```bash
export DEBUG=1
./scripts/deploy.sh
```

### Log Analysis

Check recent deployment logs:
```bash
tail -f deployment.log
```

Check container logs:
```bash
docker-compose -f backend/docker-compose.prod.yml logs -f
docker-compose -f frontend/docker-compose.prod.yml logs -f
```

## Security Considerations

### Script Security
- Scripts validate inputs and check prerequisites
- Sensitive data is not logged or displayed
- Backups are created with appropriate permissions
- SSL certificates are handled securely

### Backup Security
- Backups contain sensitive data - secure storage location
- Consider encrypting backups for long-term storage
- Implement backup retention policies
- Test backup restoration regularly

### Access Control
- Limit script execution to authorized users
- Use proper file permissions (750 for scripts)
- Consider using sudo for system-level operations
- Audit script usage and modifications

## Monitoring Integration

### Prometheus Metrics
Scripts can be integrated with Prometheus for monitoring:
- Health check results as metrics
- Backup success/failure metrics
- Deployment timing metrics

### Alerting
Configure alerts for:
- Failed deployments
- Health check failures
- Backup failures
- SSL certificate expiration

### Grafana Dashboards
Use the provided Grafana dashboard configuration in `monitoring/grafana-dashboard.json` for visualization.

## Support

For issues with deployment scripts:
1. Check the troubleshooting section above
2. Review logs for error details
3. Verify prerequisites are met
4. Check the main DEPLOYMENT.md guide
5. Contact technical support if needed

## Contributing

When modifying scripts:
1. Test thoroughly in development environment
2. Update documentation
3. Follow existing code style and patterns
4. Add appropriate error handling
5. Update version information