#!/bin/bash

# Vegas Shop Production Deployment Script
# This script handles the deployment process for production

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
PROJECT_NAME="vegas_shop"
BACKUP_DIR="/var/backups/vegasshop"
LOG_FILE="/var/log/vegasshop/deploy.log"

# Functions
log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')] $1${NC}" | tee -a $LOG_FILE
}

error() {
    echo -e "${RED}[$(date +'%Y-%m-%d %H:%M:%S')] ERROR: $1${NC}" | tee -a $LOG_FILE
    exit 1
}

warning() {
    echo -e "${YELLOW}[$(date +'%Y-%m-%d %H:%M:%S')] WARNING: $1${NC}" | tee -a $LOG_FILE
}

# Check if running as root
if [ "$EUID" -eq 0 ]; then
    error "Please do not run this script as root"
fi

# Create necessary directories
log "Creating necessary directories..."
mkdir -p $BACKUP_DIR
mkdir -p /var/log/vegasshop
mkdir -p logs

# Backup current deployment
log "Creating backup of current deployment..."
if [ -d "/var/www/$PROJECT_NAME" ]; then
    BACKUP_NAME="backup_$(date +%Y%m%d_%H%M%S)"
    tar -czf "$BACKUP_DIR/$BACKUP_NAME.tar.gz" -C /var/www $PROJECT_NAME
    log "Backup created: $BACKUP_DIR/$BACKUP_NAME.tar.gz"
fi

# Copy files to production directory
log "Copying files to production directory..."
sudo mkdir -p /var/www/$PROJECT_NAME
sudo cp -r . /var/www/$PROJECT_NAME/
sudo chown -R www-data:www-data /var/www/$PROJECT_NAME
sudo chmod -R 755 /var/www/$PROJECT_NAME

# Set proper permissions
log "Setting proper permissions..."
sudo chmod 600 /var/www/$PROJECT_NAME/.env
sudo chmod 755 /var/www/$PROJECT_NAME/cli.php
sudo chmod -R 777 /var/www/$PROJECT_NAME/logs
sudo chmod -R 777 /var/www/$PROJECT_NAME/public/uploads

# Install dependencies (if composer.json exists)
if [ -f "composer.json" ]; then
    log "Installing PHP dependencies..."
    cd /var/www/$PROJECT_NAME
    composer install --no-dev --optimize-autoloader
fi

# Run database migrations
log "Running database migrations..."
cd /var/www/$PROJECT_NAME
php cli.php migrate

# Clear caches
log "Clearing application caches..."
php cli.php cache:clear

# Update Apache/Nginx configuration
log "Updating web server configuration..."
if command -v apache2 &> /dev/null; then
    # Apache configuration
    sudo cp public/.htaccess.production /var/www/$PROJECT_NAME/public/.htaccess
    sudo systemctl reload apache2
    log "Apache configuration updated"
elif command -v nginx &> /dev/null; then
    # Nginx configuration would go here
    warning "Nginx configuration not implemented yet"
fi

# Set up log rotation
log "Setting up log rotation..."
sudo tee /etc/logrotate.d/vegasshop > /dev/null <<EOF
/var/www/$PROJECT_NAME/logs/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
    postrotate
        systemctl reload apache2 > /dev/null 2>&1 || true
    endscript
}
EOF

# Set up cron jobs
log "Setting up cron jobs..."
(crontab -l 2>/dev/null; echo "0 2 * * * cd /var/www/$PROJECT_NAME && php cli.php cache:clear") | crontab -
(crontab -l 2>/dev/null; echo "0 3 * * * cd /var/www/$PROJECT_NAME && php cli.php logs:clear") | crontab -

# Health check
log "Performing health check..."
if curl -f -s http://localhost/ > /dev/null; then
    log "Health check passed"
else
    error "Health check failed"
fi

# Final cleanup
log "Cleaning up temporary files..."
rm -f /tmp/vegasshop_*

log "Deployment completed successfully!"
log "Application is available at: http://$(hostname -I | awk '{print $1}')/"

# Display useful information
echo ""
echo "=== Deployment Summary ==="
echo "Project: $PROJECT_NAME"
echo "Location: /var/www/$PROJECT_NAME"
echo "Backup: $BACKUP_DIR/"
echo "Logs: /var/log/vegasshop/"
echo "CLI Tool: php /var/www/$PROJECT_NAME/cli.php help"
echo ""
echo "=== Next Steps ==="
echo "1. Update .env file with production settings"
echo "2. Configure SSL certificate"
echo "3. Set up monitoring"
echo "4. Configure backup strategy"
echo ""
