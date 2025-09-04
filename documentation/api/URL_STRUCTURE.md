# URL Structure Guide

With the new .htaccess configuration, your integration system now serves the `public/` directory as the document root for better security.

## 🌐 How URLs Work Now

### Root Directory Access
The root `.htaccess` file automatically redirects all requests to the `public/` directory:

| **What you type** | **What actually loads** | **Description** |
|-------------------|-------------------------|-----------------|
| `http://localhost/laguna_3dcart_netsuite/` | `public/index.php` | Main dashboard |
| `http://localhost/laguna_3dcart_netsuite/status.php` | `public/status.php` | Status monitoring |
| `http://localhost/laguna_3dcart_netsuite/webhook.php` | `public/webhook.php` | Webhook endpoint |
| `http://localhost/laguna_3dcart_netsuite/upload.php` | `public/upload.php` | Manual upload |

### Direct Public Access
You can also access files directly through the public directory:

| **Direct URL** | **Description** |
|----------------|-----------------|
| `http://localhost/laguna_3dcart_netsuite/public/` | Main dashboard |
| `http://localhost/laguna_3dcart_netsuite/public/status.php` | Status monitoring |
| `http://localhost/laguna_3dcart_netsuite/public/webhook.php` | Webhook endpoint |
| `http://localhost/laguna_3dcart_netsuite/public/upload.php` | Manual upload |

## 🔒 Security Benefits

### Protected Directories
The following directories are now **completely inaccessible** from the web:

- ❌ `/config/` - Configuration files with sensitive data
- ❌ `/src/` - Application source code
- ❌ `/vendor/` - Composer dependencies
- ❌ `/logs/` - Log files
- ❌ `/uploads/` - Temporary upload files
- ❌ `/tests/` - Test files
- ❌ `/documentation/` - Documentation files

### Protected Files
These sensitive files are blocked:

- ❌ `composer.json` / `composer.lock`
- ❌ `*.md` files (documentation)
- ❌ `.env` files
- ❌ `*.log` files
- ❌ Any file starting with `.` (hidden files)

### Allowed Access
Only these files are accessible:

- ✅ `test.php` (for installation testing - remove in production)
- ✅ Files in the `public/` directory

## 🚀 Production URLs

When you deploy to production, your URLs will be:

### For 3DCart Webhook Configuration
```
https://yourdomain.com/webhook.php
```

### For Manual Access
- **Dashboard**: `https://yourdomain.com/`
- **Status Page**: `https://yourdomain.com/status.php`
- **Upload Interface**: `https://yourdomain.com/upload.php`

## 🛠️ Development vs Production

### Development (Current Setup)
```
https://myratetool.com/laguna/netsuite/
https://myratetool.com/laguna/netsuite/status.php
https://myratetool.com/laguna/netsuite/webhook.php
https://myratetool.com/laguna/netsuite/upload.php
```

### Direct Access (If .htaccess Issues)
```
https://myratetool.com/laguna/netsuite/public/
https://myratetool.com/laguna/netsuite/public/status.php
https://myratetool.com/laguna/netsuite/public/webhook.php
https://myratetool.com/laguna/netsuite/public/upload.php
```

### Production (Recommended)
Set your web server document root to the `public/` directory:

```
https://integration.yourdomain.com/
https://integration.yourdomain.com/status.php
https://integration.yourdomain.com/webhook.php
https://integration.yourdomain.com/upload.php
```

## 🔧 Apache Virtual Host Example

For production, create a virtual host pointing directly to the public directory:

```apache
<VirtualHost *:443>
    ServerName integration.yourdomain.com
    DocumentRoot /path/to/laguna_3dcart_netsuite/public
    
    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /path/to/certificate.crt
    SSLCertificateKeyFile /path/to/private.key
    
    # Security headers
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
    
    # Directory permissions
    <Directory /path/to/laguna_3dcart_netsuite/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

## 🧪 Testing the New Structure

### Test Installation
```bash
# This should still work
php test.php
```

### Test Web Access
Visit these URLs to verify everything works:

1. **Main Dashboard**: `http://localhost/laguna_3dcart_netsuite/`
2. **Status Page**: `http://localhost/laguna_3dcart_netsuite/status.php`
3. **Upload Page**: `http://localhost/laguna_3dcart_netsuite/upload.php`

### Test Security
Try accessing these URLs - they should be **blocked**:

- `http://localhost/laguna_3dcart_netsuite/config/credentials.php` ❌
- `http://localhost/laguna_3dcart_netsuite/src/` ❌
- `http://localhost/laguna_3dcart_netsuite/logs/` ❌
- `http://localhost/laguna_3dcart_netsuite/composer.json` ❌

## 🚨 Important Notes

### For 3DCart Webhook
When configuring your 3DCart webhook, use:
```
https://yourdomain.com/webhook.php
```
**NOT**: `https://yourdomain.com/public/webhook.php`

### For Production Deployment
1. **Remove test.php access** from root .htaccess
2. **Set up proper SSL certificate**
3. **Configure firewall rules**
4. **Set up monitoring**

### File Permissions
Ensure proper permissions:
```bash
# Application files
chmod 644 *.php
chmod 755 public/

# Writable directories
chmod 755 logs/ uploads/

# Sensitive files
chmod 600 config/credentials.php
```

## ✅ Benefits of This Structure

1. **Security**: Application code is not web-accessible
2. **Clean URLs**: No need to include `/public/` in URLs
3. **Standard Practice**: Follows modern PHP application conventions
4. **Easy Deployment**: Simple to configure in production
5. **Flexibility**: Works with both subdirectory and domain setups

## 🚨 Troubleshooting 403 Errors

### Quick Diagnosis
1. **Test direct access first**: Try `https://myratetool.com/laguna/netsuite/public/test-direct.php`
2. **Check debug info**: Try `https://myratetool.com/laguna/netsuite/debug.php`
3. **Test installation**: Try `https://myratetool.com/laguna/netsuite/test.php`

### Common Solutions

#### Solution 1: Use Direct Public Access
If .htaccess is causing issues, access files directly:
- Dashboard: `https://myratetool.com/laguna/netsuite/public/`
- Status: `https://myratetool.com/laguna/netsuite/public/status.php`

#### Solution 2: Disable .htaccess Temporarily
```bash
# Rename .htaccess to disable it
mv .htaccess .htaccess.disabled
```

#### Solution 3: Use Simple .htaccess
```bash
# Use the simpler version
mv .htaccess.simple .htaccess
```

#### Solution 4: Check Apache Configuration
Ensure these modules are enabled:
- mod_rewrite
- mod_headers

### For 3DCart Webhook
If using direct access, configure webhook as:
```
https://myratetool.com/laguna/netsuite/public/webhook.php
```

---

Your integration system is now configured with industry-standard security practices! 🎉