#!/bin/bash

# Vegas Shop Database Backup Script
# Creates automated backups of the database

# Configuration
DB_HOST=${DB_HOST:-localhost}
DB_NAME=${DB_NAME:-vegas_shop}
DB_USER=${DB_USER:-root}
BACKUP_DIR="/var/backups/vegasshop/database"
RETENTION_DAYS=30

# Create backup directory if it doesn't exist
mkdir -p $BACKUP_DIR

# Generate backup filename with timestamp
BACKUP_FILE="$BACKUP_DIR/vegasshop_$(date +%Y%m%d_%H%M%S).sql"

# Create database backup
echo "Creating database backup: $BACKUP_FILE"
mysqldump -h $DB_HOST -u $DB_USER -p$DB_PASS $DB_NAME > $BACKUP_FILE

# Compress the backup
echo "Compressing backup..."
gzip $BACKUP_FILE
BACKUP_FILE="$BACKUP_FILE.gz"

# Verify backup was created successfully
if [ -f "$BACKUP_FILE" ]; then
    echo "✓ Backup created successfully: $BACKUP_FILE"
    echo "Backup size: $(du -h $BACKUP_FILE | cut -f1)"
else
    echo "✗ Backup failed!"
    exit 1
fi

# Clean up old backups
echo "Cleaning up old backups (older than $RETENTION_DAYS days)..."
find $BACKUP_DIR -name "vegasshop_*.sql.gz" -mtime +$RETENTION_DAYS -delete

echo "Backup process completed!"
