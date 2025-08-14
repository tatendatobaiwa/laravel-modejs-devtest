#!/bin/bash

# Production Backup Script for Salary Management System
# This script creates backups of the database and application files

set -e

# Configuration
BACKUP_DIR="/backup"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
RETENTION_DAYS=${BACKUP_RETENTION_DAYS:-30}

# Database backup
echo "Starting database backup..."
mysqldump -h ${DB_HOST} -u ${DB_USERNAME} -p${DB_PASSWORD} ${DB_DATABASE} > ${BACKUP_DIR}/db_backup_${TIMESTAMP}.sql

# Compress database backup
gzip ${BACKUP_DIR}/db_backup_${TIMESTAMP}.sql

# Application files backup
echo "Starting application files backup..."
tar -czf ${BACKUP_DIR}/storage_backup_${TIMESTAMP}.tar.gz -C /backup/storage .

# Clean up old backups
echo "Cleaning up old backups..."
find ${BACKUP_DIR} -name "db_backup_*.sql.gz" -mtime +${RETENTION_DAYS} -delete
find ${BACKUP_DIR} -name "storage_backup_*.tar.gz" -mtime +${RETENTION_DAYS} -delete

# Log backup completion
echo "Backup completed successfully at $(date)"
echo "Database backup: db_backup_${TIMESTAMP}.sql.gz"
echo "Storage backup: storage_backup_${TIMESTAMP}.tar.gz"

# Optional: Upload to cloud storage (uncomment and configure as needed)
# aws s3 cp ${BACKUP_DIR}/db_backup_${TIMESTAMP}.sql.gz s3://your-backup-bucket/
# aws s3 cp ${BACKUP_DIR}/storage_backup_${TIMESTAMP}.tar.gz s3://your-backup-bucket/