# Setup Guide

This guide will walk you through setting up the 3DCart to NetSuite integration system.

## Prerequisites

- PHP 7.4 or higher
- Composer (PHP dependency manager)
- Web server (Apache, Nginx, or similar)
- Access to 3DCart store admin
- NetSuite account with SuiteTalk permissions
- SendGrid account for email notifications

## Installation Steps

### 1. Download and Install

1. Clone or download the project to your web server directory
2. Navigate to the project directory
3. Install dependencies using Composer:

```bash
composer install
```

### 2. Configure Credentials

1. Copy the example credentials file:
```bash
cp config/credentials.example.php config/credentials.php
```

2. Edit `config/credentials.php` with your actual API credentials:
   - 3DCart API credentials
   - NetSuite API credentials
   - SendGrid API key

### 3. Set Directory Permissions

Ensure the following directories are writable by the web server:

```bash
chmod 755 logs/
chmod 755 uploads/
```

### 4. Configure Web Server

#### Apache Configuration

Create or update your `.htaccess` file in the `public/` directory:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# Security headers
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"

# Deny access to sensitive files
<Files "*.php">
    <RequireAll>
        Require all denied
        Require local
    </RequireAll>
</Files>

<Files "index.php">
    Require all granted
</Files>

<Files "webhook.php">
    Require all granted
</Files>

<Files "status.php">
    Require all granted
</Files>

<Files "upload.php">
    Require all granted
</Files>
```

#### Nginx Configuration

Add this to your Nginx server block:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
    fastcgi_index index.php;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;
}

# Security
location ~ /\. {
    deny all;
}

location ~* \.(log|sql|conf)$ {
    deny all;
}
```

### 5. Test Installation

1. Visit your installation URL (e.g., `https://yourdomain.com/integration/`)
2. Check the status page to verify all connections are working
3. Review any configuration issues shown on the dashboard

## Configuration Options

### Main Configuration (`config/config.php`)

Key settings you may want to adjust:

```php
// Application Settings
'app' => [
    'timezone' => 'America/New_York', // Your timezone
    'debug' => false, // Set to true for development
],

// Email Notifications
'notifications' => [
    'enabled' => true,
    'to_emails' => ['admin@yourdomain.com'], // Notification recipients
],

// Order Processing
'order_processing' => [
    'auto_create_customers' => true, // Auto-create customers in NetSuite
    'retry_attempts' => 3, // Number of retry attempts for failed orders
],
```

### Environment Variables

You can also use environment variables by creating a `.env` file:

```env
APP_DEBUG=false
DB_HOST=localhost
DB_NAME=integration_db
DB_USER=username
DB_PASS=password
NOTIFICATION_TO_EMAILS=admin@yourdomain.com,manager@yourdomain.com
WEBHOOK_SECRET=your-webhook-secret-key
```

## Security Considerations

### 1. HTTPS Configuration

Always use HTTPS in production:

```apache
# Force HTTPS
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

### 2. IP Restrictions

Restrict access to sensitive endpoints:

```apache
<Location "/status.php">
    Require ip 192.168.1.0/24
    Require ip 10.0.0.0/8
</Location>
```

### 3. Webhook Security

Configure a webhook secret key:

```php
'webhook' => [
    'secret_key' => 'your-strong-secret-key-here',
],
```

### 4. File Permissions

Secure file permissions:

```bash
# Application files
find . -type f -name "*.php" -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;

# Writable directories
chmod 755 logs/
chmod 755 uploads/

# Protect sensitive files
chmod 600 config/credentials.php
```

## Monitoring and Maintenance

### 1. Log Rotation

Set up log rotation to prevent log files from growing too large:

```bash
# Add to /etc/logrotate.d/integration
/path/to/integration/logs/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
}
```

### 2. Automated Health Checks

Set up a cron job to monitor system health:

```bash
# Add to crontab
*/5 * * * * curl -s https://yourdomain.com/integration/status.php?format=json > /dev/null
```

### 3. Backup Strategy

Regular backups should include:
- Configuration files
- Log files (for audit purposes)
- Any custom modifications

## Troubleshooting

### Common Issues

1. **Permission Denied Errors**
   - Check directory permissions
   - Ensure web server user can write to logs/ and uploads/

2. **API Connection Failures**
   - Verify credentials in config/credentials.php
   - Check firewall settings
   - Confirm API endpoints are accessible

3. **Memory Limit Errors**
   - Increase PHP memory limit in php.ini
   - Optimize large file processing

4. **Webhook Not Receiving Data**
   - Verify webhook URL is accessible from internet
   - Check 3DCart webhook configuration
   - Review web server logs

### Debug Mode

Enable debug mode for detailed error information:

```php
'app' => [
    'debug' => true,
],
```

### Log Analysis

Monitor logs for issues:

```bash
# View recent errors
tail -f logs/app.log | grep ERROR

# Search for specific order
grep "Order 12345" logs/app.log
```

## Performance Optimization

### 1. PHP Configuration

Optimize PHP settings:

```ini
; php.ini optimizations
memory_limit = 256M
max_execution_time = 300
upload_max_filesize = 10M
post_max_size = 10M
```

### 2. Database Optimization

If using database logging:

```sql
-- Add indexes for better performance
CREATE INDEX idx_created_at ON integration_logs(created_at);
CREATE INDEX idx_level ON integration_logs(level);
```

### 3. Caching

Consider implementing caching for:
- API responses
- Customer lookups
- Configuration data

## Next Steps

After successful installation:

1. [Configure API Credentials](API_CREDENTIALS.md)
2. Set up 3DCart webhooks
3. Test with sample orders
4. Configure monitoring and alerts
5. Plan for production deployment

## Support

For additional support:
- Check the logs for detailed error messages
- Review the status dashboard for system health
- Consult the API documentation for each service
- Contact your system administrator for server-related issues