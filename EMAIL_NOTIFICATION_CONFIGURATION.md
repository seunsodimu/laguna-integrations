# Email Notification Configuration Guide

## Overview

The system sends email notifications to administrators for order processing events and errors. Currently, notifications are being sent to `admin@lagunatools.com`. This guide shows where and how to modify the email notification settings.

## Current Configuration

**Current Recipient**: `admin@lagunatools.com`  
**Default Fallback**: `seun_sodimu@lagunatools.com`

## Configuration Locations

### 1. Primary Configuration - Environment Variable

**File**: `.env`  
**Line**: 22  
**Current Setting**:
```env
NOTIFICATION_TO_EMAILS=admin@lagunatools.com
```

**To Change**: Update this line with new email address(es):
```env
# Single email
NOTIFICATION_TO_EMAILS=newemail@lagunatools.com

# Multiple emails (comma-separated)
NOTIFICATION_TO_EMAILS=admin@lagunatools.com,manager@lagunatools.com,support@lagunatools.com
```

### 2. Fallback Configuration - Config File

**File**: `config/config.php`  
**Line**: 57  
**Current Setting**:
```php
'to_emails' => explode(',', $_ENV['NOTIFICATION_TO_EMAILS'] ?? 'seun_sodimu@lagunatools.com'),
```

**To Change**: Modify the fallback email:
```php
'to_emails' => explode(',', $_ENV['NOTIFICATION_TO_EMAILS'] ?? 'your-new-fallback@lagunatools.com'),
```

### 3. AWS Deployment Configuration

**File**: `aws-deployment/config/aws-config.php`  
**Line**: 122  
**Current Setting**:
```php
'to_emails' => explode(',', $_ENV['NOTIFICATION_TO_EMAILS'] ?? 'seun_sodimu@lagunatools.com'),
```

**To Change**: Update for AWS deployments:
```php
'to_emails' => explode(',', $_ENV['NOTIFICATION_TO_EMAILS'] ?? 'aws-admin@lagunatools.com'),
```

## Email Notification Types

### 1. Order Processing Notifications

**Sent When**:
- Order successfully processed
- Order processing failed
- Batch processing completed
- Manual upload completed

**Triggered From**:
- `src/Controllers/WebhookController.php` (lines 159, 192, 459, 493, 534)
- `src/Controllers/OrderController.php` (line 508)

### 2. Error Notifications

**Sent When**:
- Webhook processing fails
- System errors occur
- API failures happen

**Triggered From**:
- `src/Controllers/WebhookController.php` (line 102)

## Email Service Implementation

### UnifiedEmailService

**File**: `src/Services/UnifiedEmailService.php`

**Key Methods**:
- `sendOrderNotification()` - Sends order status notifications
- `sendErrorNotification()` - Sends error alerts

**Configuration Reading**:
```php
// Reads configuration from config.php
$config = require __DIR__ . '/../../config/config.php';
$recipients = $config['notifications']['to_emails'] ?? [];
```

## How to Change Email Recipients

### Method 1: Environment Variable (Recommended)

1. **Edit `.env` file**:
   ```env
   NOTIFICATION_TO_EMAILS=your-new-email@lagunatools.com
   ```

2. **For multiple recipients**:
   ```env
   NOTIFICATION_TO_EMAILS=admin@lagunatools.com,manager@lagunatools.com
   ```

3. **Restart the application** (if using a web server that caches environment variables)

### Method 2: Direct Configuration File Edit

1. **Edit `config/config.php`**:
   ```php
   'to_emails' => ['your-new-email@lagunatools.com'],
   ```

2. **For multiple recipients**:
   ```php
   'to_emails' => [
       'admin@lagunatools.com',
       'manager@lagunatools.com',
       'support@lagunatools.com'
   ],
   ```

### Method 3: Database Configuration (Future Enhancement)

Currently not implemented, but could be added to allow dynamic email configuration through the admin interface.

## Additional Email Settings

### From Email Configuration

**Environment Variables**:
```env
NOTIFICATION_FROM_EMAIL=noreply@lagunatools.com
NOTIFICATION_FROM_NAME="3DCart Integration"
```

**Config File** (`config/config.php`):
```php
'from_email' => $_ENV['NOTIFICATION_FROM_EMAIL'] ?? 'noreply@lagunatools.com',
'from_name' => $_ENV['NOTIFICATION_FROM_NAME'] ?? '3DCart Integration',
```

### Enable/Disable Notifications

**Environment Variable**:
```env
# To disable all notifications
NOTIFICATION_ENABLED=false
```

**Config File**:
```php
'enabled' => $_ENV['NOTIFICATION_ENABLED'] ?? true,
```

## Testing Email Configuration

### Test Email Functionality

**File**: `public/test-email.php`  
**URL**: `http://your-domain/test-email.php`

This page allows you to:
- Test email service connectivity
- Send test notifications
- Verify recipient configuration

### Test Email Provider

**File**: `public/test-email-provider.php`  
**URL**: `http://your-domain/test-email-provider.php`

This page allows you to:
- Test specific email providers (Brevo, SendGrid)
- Send sample notifications
- Verify email delivery

## Verification Steps

After changing email configuration:

1. **Check Configuration**:
   ```php
   // Add to any PHP file to verify current config
   $config = require 'config/config.php';
   var_dump($config['notifications']['to_emails']);
   ```

2. **Test Email Sending**:
   - Visit `public/test-email.php`
   - Send a test notification
   - Verify receipt at new email address

3. **Monitor Logs**:
   - Check `logs/app-*.log` for email sending confirmation
   - Look for entries like: `Email sent successfully` or `Email sending failed`

## Database References

The following database entries also reference admin@lagunatools.com but are for user authentication, not notifications:

- `database/user_auth_schema.sql` (line 87)
- `database/user_auth_schema_simple.sql` (line 88)
- `setup-database-fixed.php` (line 179)

These create admin user accounts and don't affect email notifications.

## Summary

**To change email notifications from `admin@lagunatools.com` to a new address:**

1. **Quick Change**: Edit `.env` file, line 22:
   ```env
   NOTIFICATION_TO_EMAILS=new-email@lagunatools.com
   ```

2. **Multiple Recipients**: Use comma-separated values:
   ```env
   NOTIFICATION_TO_EMAILS=email1@domain.com,email2@domain.com
   ```

3. **Test**: Use `public/test-email.php` to verify the change

4. **Monitor**: Check logs to ensure emails are being sent successfully

The system will automatically use the new email address(es) for all order processing and error notifications.