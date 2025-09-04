# Production Deployment Guide

This guide covers deploying the 3DCart to NetSuite integration system in a production environment.

## Pre-Deployment Checklist

### 1. System Requirements

- [ ] PHP 7.4+ with required extensions (curl, json, openssl)
- [ ] Web server (Apache/Nginx) with HTTPS support
- [ ] SSL certificate installed and configured
- [ ] Composer installed for dependency management
- [ ] Sufficient disk space for logs and uploads
- [ ] Backup strategy in place

### 2. Security Requirements

- [ ] HTTPS enforced for all endpoints
- [ ] Firewall configured to restrict access
- [ ] Strong passwords and API keys
- [ ] File permissions properly set
- [ ] Sensitive directories protected
- [ ] Error reporting disabled in production

### 3. API Access

- [ ] 3DCart API credentials tested
- [ ] NetSuite API credentials tested
- [ ] SendGrid API credentials tested
- [ ] All API endpoints accessible from production server
- [ ] Rate limits understood and configured

## Deployment Steps

### 1. Server Preparation

#### Update System Packages

```bash
# Ubuntu/Debian
sudo apt update && sudo apt upgrade -y

# CentOS/RHEL
sudo yum update -y
```

#### Install Required Software

```bash
# Ubuntu/Debian
sudo apt install -y php7.4 php7.4-curl php7.4-json php7.4-mbstring php7.4-xml php7.4-zip
sudo apt install -y apache2 # or nginx
sudo apt install -y composer

# CentOS/RHEL
sudo yum install -y php php-curl php-json php-mbstring php-xml php-zip
sudo yum install -y httpd # or nginx
```

### 2. Application Deployment

#### Clone/Upload Application

```bash
# Option 1: Git clone (if using version control)
cd /var/www/html
sudo git clone https://github.com/yourorg/3dcart-netsuite-integration.git integration
cd integration

# Option 2: Upload files via FTP/SCP
# Upload to /var/www/html/integration/
```

#### Install Dependencies

```bash
cd /var/www/html/integration
sudo composer install --no-dev --optimize-autoloader
```

#### Set File Permissions

```bash
# Set ownership
sudo chown -R www-data:www-data /var/www/html/integration

# Set directory permissions
sudo find /var/www/html/integration -type d -exec chmod 755 {} \;

# Set file permissions
sudo find /var/www/html/integration -type f -exec chmod 644 {} \;

# Make specific directories writable
sudo chmod 755 /var/www/html/integration/logs
sudo chmod 755 /var/www/html/integration/uploads

# Protect sensitive files
sudo chmod 600 /var/www/html/integration/config/credentials.php
```

### 3. Configuration

#### Copy and Configure Credentials

```bash
cd /var/www/html/integration
sudo cp config/credentials.example.php config/credentials.php
sudo nano config/credentials.php
```

#### Environment-Specific Configuration

Create production configuration:

```bash
sudo nano config/config.php
```

Update for production:

```php
return [
    'app' => [
        'debug' => false, // IMPORTANT: Disable debug in production
        'timezone' => 'America/New_York',
    ],
    
    'logging' => [
        'enabled' => true,
        'level' => 'info', // Don't log debug messages in production
        'max_files' => 30,
    ],
    
    'notifications' => [
        'enabled' => true,
        'to_emails' => ['admin@yourdomain.com', 'manager@yourdomain.com'],
    ],
    
    // ... other settings
];
```

### 4. Web Server Configuration

#### Apache Configuration

Create virtual host:

```bash
sudo nano /etc/apache2/sites-available/integration.conf
```

```apache
<VirtualHost *:80>
    ServerName integration.yourdomain.com
    DocumentRoot /var/www/html/integration/public
    
    # Redirect to HTTPS
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</VirtualHost>

<VirtualHost *:443>
    ServerName integration.yourdomain.com
    DocumentRoot /var/www/html/integration/public
    
    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /path/to/your/certificate.crt
    SSLCertificateKeyFile /path/to/your/private.key
    SSLCertificateChainFile /path/to/your/chain.crt
    
    # Security Headers
    Header always set Strict-Transport-Security "max-age=63072000; includeSubDomains; preload"
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    
    # Directory Configuration
    <Directory /var/www/html/integration/public>
        AllowOverride All
        Require all granted
        
        # PHP Configuration
        php_value upload_max_filesize 10M
        php_value post_max_size 10M
        php_value memory_limit 256M
        php_value max_execution_time 300
    </Directory>
    
    # Deny access to sensitive directories
    <Directory /var/www/html/integration/config>
        Require all denied
    </Directory>
    
    <Directory /var/www/html/integration/logs>
        Require all denied
    </Directory>
    
    <Directory /var/www/html/integration/vendor>
        Require all denied
    </Directory>
    
    # Error and Access Logs
    ErrorLog ${APACHE_LOG_DIR}/integration_error.log
    CustomLog ${APACHE_LOG_DIR}/integration_access.log combined
</VirtualHost>
```

Enable the site:

```bash
sudo a2ensite integration.conf
sudo a2enmod rewrite ssl headers
sudo systemctl reload apache2
```

#### Nginx Configuration

```bash
sudo nano /etc/nginx/sites-available/integration
```

```nginx
# Redirect HTTP to HTTPS
server {
    listen 80;
    server_name integration.yourdomain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name integration.yourdomain.com;
    root /var/www/html/integration/public;
    index index.php;
    
    # SSL Configuration
    ssl_certificate /path/to/your/certificate.crt;
    ssl_certificate_key /path/to/your/private.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512:ECDHE-RSA-AES256-GCM-SHA384:DHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;
    
    # Security Headers
    add_header Strict-Transport-Security "max-age=63072000; includeSubDomains; preload" always;
    add_header X-Content-Type-Options nosniff always;
    add_header X-Frame-Options DENY always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    
    # Main location
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    # PHP handling
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        
        # PHP settings
        fastcgi_param PHP_VALUE "upload_max_filesize=10M
                                 post_max_size=10M
                                 memory_limit=256M
                                 max_execution_time=300";
    }
    
    # Deny access to sensitive files
    location ~ /\. {
        deny all;
    }
    
    location ~* \.(log|sql|conf)$ {
        deny all;
    }
    
    location ~ ^/(config|logs|vendor)/ {
        deny all;
    }
    
    # Logging
    access_log /var/log/nginx/integration_access.log;
    error_log /var/log/nginx/integration_error.log;
}
```

Enable the site:

```bash
sudo ln -s /etc/nginx/sites-available/integration /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 5. SSL Certificate Setup

#### Using Let's Encrypt (Recommended)

```bash
# Install Certbot
sudo apt install certbot python3-certbot-apache # For Apache
# OR
sudo apt install certbot python3-certbot-nginx # For Nginx

# Obtain certificate
sudo certbot --apache -d integration.yourdomain.com # For Apache
# OR
sudo certbot --nginx -d integration.yourdomain.com # For Nginx

# Set up auto-renewal
sudo crontab -e
# Add: 0 12 * * * /usr/bin/certbot renew --quiet
```

### 6. Firewall Configuration

#### UFW (Ubuntu)

```bash
sudo ufw allow 22/tcp    # SSH
sudo ufw allow 80/tcp    # HTTP
sudo ufw allow 443/tcp   # HTTPS
sudo ufw enable
```

#### iptables

```bash
# Allow SSH, HTTP, HTTPS
sudo iptables -A INPUT -p tcp --dport 22 -j ACCEPT
sudo iptables -A INPUT -p tcp --dport 80 -j ACCEPT
sudo iptables -A INPUT -p tcp --dport 443 -j ACCEPT

# Save rules
sudo iptables-save > /etc/iptables/rules.v4
```

## Post-Deployment Configuration

### 1. Test Installation

Visit your integration URL and verify:

- [ ] Dashboard loads correctly
- [ ] Status page shows all services as connected
- [ ] No PHP errors in logs
- [ ] HTTPS is working and enforced

### 2. Configure 3DCart Webhooks

1. Log in to 3DCart admin
2. Go to Settings → General → Store Settings → Advanced
3. In the Webhooks section, add:
   - **URL**: `https://integration.yourdomain.com/webhook.php`
   - **Event**: Order Created/Updated
   - **Method**: POST
   - **Format**: JSON

### 3. Test Order Processing

1. Create a test order in 3DCart
2. Verify webhook is received
3. Check that order is created in NetSuite
4. Confirm email notification is sent

## Monitoring and Maintenance

### 1. Log Monitoring

Set up log rotation:

```bash
sudo nano /etc/logrotate.d/integration
```

```
/var/www/html/integration/logs/*.log {
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
```

### 2. Health Monitoring

Create a monitoring script:

```bash
sudo nano /usr/local/bin/check-integration.sh
```

```bash
#!/bin/bash

URL="https://integration.yourdomain.com/status.php?format=json"
RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" "$URL")

if [ "$RESPONSE" != "200" ]; then
    echo "Integration health check failed: HTTP $RESPONSE"
    # Send alert email or notification
    echo "Integration system is down" | mail -s "Integration Alert" admin@yourdomain.com
fi
```

```bash
sudo chmod +x /usr/local/bin/check-integration.sh

# Add to crontab
sudo crontab -e
# Add: */5 * * * * /usr/local/bin/check-integration.sh
```

### 3. Backup Strategy

Create backup script:

```bash
sudo nano /usr/local/bin/backup-integration.sh
```

```bash
#!/bin/bash

BACKUP_DIR="/backups/integration"
APP_DIR="/var/www/html/integration"
DATE=$(date +%Y%m%d_%H%M%S)

mkdir -p "$BACKUP_DIR"

# Backup application files (excluding logs and uploads)
tar -czf "$BACKUP_DIR/integration_$DATE.tar.gz" \
    --exclude="$APP_DIR/logs/*" \
    --exclude="$APP_DIR/uploads/*" \
    --exclude="$APP_DIR/vendor" \
    "$APP_DIR"

# Backup configuration separately
cp "$APP_DIR/config/credentials.php" "$BACKUP_DIR/credentials_$DATE.php"

# Keep only last 7 days of backups
find "$BACKUP_DIR" -name "integration_*.tar.gz" -mtime +7 -delete
find "$BACKUP_DIR" -name "credentials_*.php" -mtime +7 -delete

echo "Backup completed: integration_$DATE.tar.gz"
```

```bash
sudo chmod +x /usr/local/bin/backup-integration.sh

# Add to crontab for daily backups
sudo crontab -e
# Add: 0 2 * * * /usr/local/bin/backup-integration.sh
```

## Performance Optimization

### 1. PHP Optimization

Update PHP configuration:

```bash
sudo nano /etc/php/7.4/apache2/php.ini
```

```ini
; Production optimizations
memory_limit = 256M
max_execution_time = 300
upload_max_filesize = 10M
post_max_size = 10M

; Security
expose_php = Off
display_errors = Off
log_errors = On
error_log = /var/log/php_errors.log

; Performance
opcache.enable = 1
opcache.memory_consumption = 128
opcache.max_accelerated_files = 4000
opcache.revalidate_freq = 60
```

### 2. Web Server Optimization

#### Apache

```apache
# Enable compression
LoadModule deflate_module modules/mod_deflate.so

<Location />
    SetOutputFilter DEFLATE
    SetEnvIfNoCase Request_URI \
        \.(?:gif|jpe?g|png)$ no-gzip dont-vary
    SetEnvIfNoCase Request_URI \
        \.(?:exe|t?gz|zip|bz2|sit|rar)$ no-gzip dont-vary
</Location>

# Enable caching
LoadModule expires_module modules/mod_expires.so

<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType image/png "access plus 1 month"
    ExpiresByType image/jpg "access plus 1 month"
    ExpiresByType image/jpeg "access plus 1 month"
</IfModule>
```

#### Nginx

```nginx
# Enable gzip compression
gzip on;
gzip_vary on;
gzip_min_length 1024;
gzip_types text/plain text/css application/json application/javascript text/xml application/xml application/xml+rss text/javascript;

# Enable caching
location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg)$ {
    expires 1M;
    add_header Cache-Control "public, immutable";
}
```

## Security Hardening

### 1. Additional Security Measures

```bash
# Install fail2ban to prevent brute force attacks
sudo apt install fail2ban

# Configure fail2ban for Apache/Nginx
sudo nano /etc/fail2ban/jail.local
```

```ini
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 5

[apache-auth]
enabled = true

[apache-badbots]
enabled = true

[apache-noscript]
enabled = true

[apache-overflows]
enabled = true
```

### 2. File Integrity Monitoring

```bash
# Install AIDE
sudo apt install aide

# Initialize database
sudo aideinit

# Create monitoring script
sudo nano /usr/local/bin/check-integrity.sh
```

```bash
#!/bin/bash
/usr/bin/aide --check
if [ $? -ne 0 ]; then
    echo "File integrity check failed" | mail -s "Security Alert" admin@yourdomain.com
fi
```

## Troubleshooting

### Common Production Issues

1. **502 Bad Gateway**
   - Check PHP-FPM status: `sudo systemctl status php7.4-fpm`
   - Check web server error logs

2. **Permission Denied**
   - Verify file ownership: `ls -la /var/www/html/integration`
   - Check SELinux status: `sestatus`

3. **SSL Certificate Issues**
   - Verify certificate: `openssl x509 -in certificate.crt -text -noout`
   - Check certificate chain: `openssl verify -CAfile chain.crt certificate.crt`

4. **High Memory Usage**
   - Monitor with: `htop` or `ps aux --sort=-%mem`
   - Check PHP memory limit and usage

### Emergency Procedures

1. **Service Down**
   ```bash
   # Check service status
   sudo systemctl status apache2  # or nginx
   sudo systemctl status php7.4-fpm
   
   # Restart services
   sudo systemctl restart apache2
   sudo systemctl restart php7.4-fpm
   ```

2. **Disk Space Full**
   ```bash
   # Check disk usage
   df -h
   
   # Clean old logs
   sudo find /var/log -name "*.log" -mtime +30 -delete
   sudo find /var/www/html/integration/logs -name "*.log" -mtime +7 -delete
   ```

3. **Database Issues** (if using database logging)
   ```bash
   # Check database status
   sudo systemctl status mysql
   
   # Clean old log entries
   mysql -u root -p -e "DELETE FROM integration_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);"
   ```

## Maintenance Schedule

### Daily
- [ ] Check system status
- [ ] Review error logs
- [ ] Verify backup completion

### Weekly
- [ ] Review performance metrics
- [ ] Check disk space usage
- [ ] Update system packages

### Monthly
- [ ] Review and rotate API keys
- [ ] Audit user access
- [ ] Performance optimization review
- [ ] Security updates

### Quarterly
- [ ] Full security audit
- [ ] Disaster recovery test
- [ ] Documentation updates
- [ ] Capacity planning review

## Support and Escalation

### Contact Information
- **System Administrator**: admin@yourdomain.com
- **Development Team**: dev@yourdomain.com
- **Emergency Contact**: +1-555-0123

### Escalation Procedures
1. **Level 1**: Check logs and restart services
2. **Level 2**: Contact system administrator
3. **Level 3**: Contact development team
4. **Level 4**: Emergency contact for critical issues

### Documentation
- Keep this deployment guide updated
- Document any custom modifications
- Maintain change log for all updates
- Record all configuration changes