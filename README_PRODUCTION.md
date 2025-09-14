# Vegas Shop - Production Deployment Guide

## Overview
This guide covers the production deployment and maintenance of the Vegas Shop e-commerce application.

## Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- Composer (for dependency management)
- SSL certificate (for production)

## Installation

### 1. Clone and Setup
```bash
git clone <repository-url> vegas_shop
cd vegas_shop
```

### 2. Environment Configuration
```bash
# Copy environment file
cp env.example .env

# Edit production settings
nano .env
```

Update the following in `.env`:
- Database credentials
- Security keys
- Email configuration
- Environment = production

### 3. Install Dependencies
```bash
composer install --no-dev --optimize-autoloader
```

### 4. Database Setup
```bash
# Run migrations
php cli.php migrate

# Seed with sample data (optional)
php cli.php seed
```

### 5. Web Server Configuration

#### Apache
```bash
# Copy production .htaccess
cp public/.htaccess.production public/.htaccess

# Set proper permissions
chown -R www-data:www-data /var/www/vegas_shop
chmod -R 755 /var/www/vegas_shop
chmod 600 .env
chmod 777 logs/
chmod 777 public/uploads/
```

#### Nginx
```nginx
server {
    listen 80;
    server_name your-domain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name your-domain.com;
    root /var/www/vegas_shop/public;
    index index.php;

    ssl_certificate /path/to/certificate.crt;
    ssl_certificate_key /path/to/private.key;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(ht|env) {
        deny all;
    }
}
```

## Security Features

### 1. Security Headers
- X-Frame-Options: SAMEORIGIN
- X-Content-Type-Options: nosniff
- X-XSS-Protection: 1; mode=block
- Content-Security-Policy: Strict policy
- HSTS: Enabled for HTTPS

### 2. Input Validation
- Comprehensive input sanitization
- SQL injection prevention
- XSS protection
- File upload validation

### 3. Error Handling
- Centralized error logging
- User-friendly error pages
- Detailed errors in development only

## Monitoring and Maintenance

### 1. Log Management
```bash
# View logs
tail -f logs/app.log

# Clear logs
php cli.php logs:clear

# Log rotation (automatic)
# Configured in /etc/logrotate.d/vegasshop
```

### 2. Cache Management
```bash
# Clear cache
php cli.php cache:clear

# Cache is automatically managed
# File-based caching with TTL
```

### 3. Database Maintenance
```bash
# Run migrations
php cli.php migrate

# Rollback migrations
php cli.php rollback

# Check migration status
php cli.php status
```

### 4. Backup Strategy
```bash
# Manual database backup
./scripts/backup_database.sh

# Automated backups (cron)
0 2 * * * /path/to/vegasshop/scripts/backup_database.sh
```

## Performance Optimization

### 1. Caching
- File-based caching system
- Database query caching
- Static asset caching

### 2. Database Optimization
- Proper indexing
- Query optimization
- Connection pooling

### 3. Web Server Optimization
- Gzip compression
- Browser caching
- Static file serving

## Troubleshooting

### Common Issues

1. **Database Connection Failed**
   - Check database credentials in `.env`
   - Verify database server is running
   - Check network connectivity

2. **Permission Denied**
   - Verify file permissions
   - Check web server user ownership
   - Ensure logs directory is writable

3. **SSL Certificate Issues**
   - Verify certificate validity
   - Check certificate chain
   - Ensure proper file permissions

### Debug Mode
```bash
# Enable debug mode
echo "ENVIRONMENT=development" >> .env
echo "LOG_LEVEL=debug" >> .env
```

## Security Checklist

- [ ] Environment variables properly configured
- [ ] Database credentials secured
- [ ] SSL certificate installed and valid
- [ ] Security headers enabled
- [ ] File permissions set correctly
- [ ] Error logging configured
- [ ] Backup strategy implemented
- [ ] Monitoring in place
- [ ] Regular security updates

## Support

For production support and issues:
- Check logs: `tail -f logs/app.log`
- Run health check: `curl -f http://your-domain.com/`
- Database status: `php cli.php status`

## Version Information
- Application Version: 1.0.0
- PHP Version: 7.4+
- Database: MySQL 5.7+
- Last Updated: $(date)
