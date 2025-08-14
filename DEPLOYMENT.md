# Production Deployment Guide

This guide provides comprehensive instructions for deploying the Salary Management System to production.

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Environment Setup](#environment-setup)
3. [SSL Certificate Configuration](#ssl-certificate-configuration)
4. [Database Setup](#database-setup)
5. [Application Deployment](#application-deployment)
6. [Monitoring Setup](#monitoring-setup)
7. [Backup Configuration](#backup-configuration)
8. [Troubleshooting](#troubleshooting)
9. [Maintenance](#maintenance)

## Prerequisites

### System Requirements

- **Operating System**: Ubuntu 20.04 LTS or later / CentOS 8 or later
- **RAM**: Minimum 4GB, Recommended 8GB+
- **Storage**: Minimum 50GB SSD
- **CPU**: Minimum 2 cores, Recommended 4+ cores
- **Network**: Static IP address with ports 80, 443, 22 open

### Software Requirements

- Docker Engine 20.10+
- Docker Compose 2.0+
- Git
- OpenSSL (for SSL certificates)

### Installation Commands

```bash
# Ubuntu/Debian
sudo apt update
sudo apt install -y docker.io docker-compose git openssl curl

# CentOS/RHEL
sudo yum install -y docker docker-compose git openssl curl

# Start Docker service
sudo systemctl start docker
sudo systemctl enable docker

# Add user to docker group
sudo usermod -aG docker $USER
```

## Environment Setup

### 1. Clone the Repository

```bash
git clone https://github.com/your-org/salary-management-system.git
cd salary-management-system
```

### 2. Configure Environment Variables

#### Backend Configuration

```bash
cd backend
cp .env.production .env

# Edit the production environment file
nano .env
```

**Required Environment Variables:**

```bash
# Application
APP_NAME="Salary Management System"
APP_ENV=production
APP_KEY=base64:YOUR_32_CHARACTER_SECRET_KEY
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database
DB_DATABASE=salary_management_prod
DB_USERNAME=salary_user_prod
DB_PASSWORD=YOUR_SECURE_DATABASE_PASSWORD
DB_ROOT_PASSWORD=YOUR_SECURE_ROOT_PASSWORD

# Redis
REDIS_PASSWORD=YOUR_SECURE_REDIS_PASSWORD

# Mail
MAIL_HOST=your-smtp-server.com
MAIL_USERNAME=your-smtp-username
MAIL_PASSWORD=your-smtp-password

# Security
SANCTUM_STATEFUL_DOMAINS=your-domain.com
SESSION_DOMAIN=your-domain.com
CORS_ALLOWED_ORIGINS=https://your-domain.com

# Admin
ADMIN_EMAILS="admin@your-domain.com"
```

#### Frontend Configuration

```bash
cd ../frontend
cp .env.production .env.local

# Edit the frontend environment file
nano .env.local
```

**Required Environment Variables:**

```bash
NEXT_PUBLIC_API_URL=https://your-domain.com/api
NEXT_PUBLIC_APP_NAME=SalaryPro
```

### 3. Generate Application Key

```bash
cd backend
docker run --rm -v $(pwd):/app -w /app php:8.2-cli php artisan key:generate --show
```

Copy the generated key to your `.env` file.

## SSL Certificate Configuration

### Option 1: Let's Encrypt (Recommended)

```bash
# Install Certbot
sudo apt install certbot

# Generate certificates
sudo certbot certonly --standalone -d your-domain.com -d www.your-domain.com

# Certificates will be stored in:
# /etc/letsencrypt/live/your-domain.com/fullchain.pem
# /etc/letsencrypt/live/your-domain.com/privkey.pem
```

### Option 2: Self-Signed Certificates (Development Only)

```bash
# Create SSL directory
mkdir -p ssl

# Generate self-signed certificate
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout ssl/your-domain.key \
  -out ssl/your-domain.crt \
  -subj "/C=US/ST=State/L=City/O=Organization/CN=your-domain.com"
```

### Update Docker Compose Configuration

Edit `backend/docker-compose.prod.yml` and `frontend/docker-compose.prod.yml` to mount your SSL certificates:

```yaml
volumes:
  - /etc/letsencrypt/live/your-domain.com/fullchain.pem:/etc/ssl/certs/your-domain.crt:ro
  - /etc/letsencrypt/live/your-domain.com/privkey.pem:/etc/ssl/private/your-domain.key:ro
```

## Database Setup

### 1. Initialize Database

```bash
cd backend

# Start database service
docker-compose -f docker-compose.prod.yml up -d db redis

# Wait for database to be ready
sleep 30

# Run migrations
docker-compose -f docker-compose.prod.yml exec app php artisan migrate --force

# Seed initial data (optional)
docker-compose -f docker-compose.prod.yml exec app php artisan db:seed --force
```

### 2. Create Admin User

```bash
# Create admin user
docker-compose -f docker-compose.prod.yml exec app php artisan tinker

# In the Tinker console:
User::create([
    'name' => 'Admin User',
    'email' => 'admin@your-domain.com',
    'password' => Hash::make('your-secure-password'),
    'email_verified_at' => now(),
]);
```

## Application Deployment

### 1. Build and Deploy Backend

```bash
cd backend

# Build and start all services
docker-compose -f docker-compose.prod.yml up -d --build

# Verify services are running
docker-compose -f docker-compose.prod.yml ps

# Check logs
docker-compose -f docker-compose.prod.yml logs -f app
```

### 2. Build and Deploy Frontend

```bash
cd ../frontend

# Create external network (if not exists)
docker network create salary-network

# Build and start frontend
docker-compose -f docker-compose.prod.yml up -d --build

# Verify frontend is running
docker-compose -f docker-compose.prod.yml ps

# Check logs
docker-compose -f docker-compose.prod.yml logs -f frontend
```

### 3. Verify Deployment

```bash
# Check application health
curl -k https://your-domain.com/health
curl -k https://your-domain.com/api/health

# Check SSL certificate
openssl s_client -connect your-domain.com:443 -servername your-domain.com
```

## Monitoring Setup

### 1. Access Monitoring Dashboard

- **Prometheus**: http://your-domain.com:9090
- **Application Logs**: `docker-compose logs -f`

### 2. Set Up Log Rotation

```bash
# Create logrotate configuration
sudo tee /etc/logrotate.d/docker-salary-app << EOF
/var/lib/docker/containers/*/*-json.log {
    daily
    rotate 7
    compress
    delaycompress
    missingok
    notifempty
    create 0644 root root
    postrotate
        docker kill --signal=USR1 \$(docker ps -q) 2>/dev/null || true
    endscript
}
EOF
```

### 3. Set Up System Monitoring

```bash
# Install system monitoring tools
sudo apt install -y htop iotop nethogs

# Set up disk space monitoring
echo "0 */6 * * * root df -h | mail -s 'Disk Space Report' admin@your-domain.com" | sudo tee -a /etc/crontab
```

## Backup Configuration

### 1. Automated Database Backups

```bash
# Make backup script executable
chmod +x backend/docker/backup/backup.sh

# Set up cron job for daily backups
echo "0 2 * * * cd /path/to/salary-management-system/backend && docker-compose -f docker-compose.prod.yml run --rm backup" | sudo tee -a /etc/crontab
```

### 2. File System Backups

```bash
# Create backup directory
sudo mkdir -p /backup/salary-app

# Set up file system backup script
sudo tee /backup/salary-app/backup-files.sh << 'EOF'
#!/bin/bash
BACKUP_DIR="/backup/salary-app"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
APP_DIR="/path/to/salary-management-system"

# Backup application files
tar -czf ${BACKUP_DIR}/app_backup_${TIMESTAMP}.tar.gz -C ${APP_DIR} .

# Clean up old backups (keep 30 days)
find ${BACKUP_DIR} -name "app_backup_*.tar.gz" -mtime +30 -delete

echo "File backup completed: app_backup_${TIMESTAMP}.tar.gz"
EOF

chmod +x /backup/salary-app/backup-files.sh

# Add to cron
echo "0 3 * * * /backup/salary-app/backup-files.sh" | sudo tee -a /etc/crontab
```

## Troubleshooting

### Common Issues

#### 1. Application Won't Start

```bash
# Check Docker service
sudo systemctl status docker

# Check container logs
docker-compose -f docker-compose.prod.yml logs app

# Check disk space
df -h

# Check memory usage
free -h
```

#### 2. Database Connection Issues

```bash
# Check database container
docker-compose -f docker-compose.prod.yml logs db

# Test database connection
docker-compose -f docker-compose.prod.yml exec db mysql -u root -p

# Check database configuration
docker-compose -f docker-compose.prod.yml exec app php artisan config:show database
```

#### 3. SSL Certificate Issues

```bash
# Check certificate validity
openssl x509 -in /etc/letsencrypt/live/your-domain.com/fullchain.pem -text -noout

# Renew Let's Encrypt certificate
sudo certbot renew

# Restart nginx after certificate renewal
docker-compose -f docker-compose.prod.yml restart webserver
```

#### 4. Performance Issues

```bash
# Check system resources
htop
iotop
docker stats

# Check application performance
docker-compose -f docker-compose.prod.yml exec app php artisan optimize
docker-compose -f docker-compose.prod.yml exec app php artisan config:cache
docker-compose -f docker-compose.prod.yml exec app php artisan route:cache
docker-compose -f docker-compose.prod.yml exec app php artisan view:cache
```

### Log Locations

- **Application Logs**: `backend/storage/logs/`
- **Nginx Logs**: `/var/log/nginx/`
- **Docker Logs**: `docker-compose logs [service]`
- **System Logs**: `/var/log/syslog`

## Maintenance

### Regular Maintenance Tasks

#### Daily
- Monitor application health endpoints
- Check disk space and memory usage
- Review error logs

#### Weekly
- Update system packages
- Review security logs
- Test backup restoration

#### Monthly
- Update Docker images
- Review and rotate logs
- Performance optimization review

### Update Procedures

#### 1. Application Updates

```bash
# Backup current version
./scripts/backup-before-update.sh

# Pull latest changes
git pull origin main

# Update backend
cd backend
docker-compose -f docker-compose.prod.yml pull
docker-compose -f docker-compose.prod.yml up -d --build

# Run migrations if needed
docker-compose -f docker-compose.prod.yml exec app php artisan migrate --force

# Update frontend
cd ../frontend
docker-compose -f docker-compose.prod.yml pull
docker-compose -f docker-compose.prod.yml up -d --build

# Verify update
curl -k https://your-domain.com/health
```

#### 2. System Updates

```bash
# Update system packages
sudo apt update && sudo apt upgrade -y

# Update Docker
sudo apt install docker.io docker-compose

# Restart services if needed
sudo systemctl restart docker
```

### Security Maintenance

#### 1. SSL Certificate Renewal

```bash
# Set up automatic renewal
echo "0 12 * * * /usr/bin/certbot renew --quiet && docker-compose -f /path/to/backend/docker-compose.prod.yml restart webserver" | sudo tee -a /etc/crontab
```

#### 2. Security Updates

```bash
# Check for security updates
sudo apt list --upgradable | grep -i security

# Apply security updates
sudo unattended-upgrades
```

### Rollback Procedures

#### 1. Application Rollback

```bash
# Stop current version
docker-compose -f docker-compose.prod.yml down

# Restore from backup
./scripts/restore-from-backup.sh [backup-timestamp]

# Start previous version
docker-compose -f docker-compose.prod.yml up -d
```

#### 2. Database Rollback

```bash
# Restore database from backup
docker-compose -f docker-compose.prod.yml exec db mysql -u root -p[password] [database] < backup_file.sql
```

## Support and Contacts

- **Technical Support**: support@your-domain.com
- **Emergency Contact**: +1-XXX-XXX-XXXX
- **Documentation**: https://docs.your-domain.com
- **Status Page**: https://status.your-domain.com

---

**Last Updated**: $(date)
**Version**: 1.0.0