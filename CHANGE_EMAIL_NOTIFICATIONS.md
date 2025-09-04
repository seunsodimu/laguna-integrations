# How to Change Email Notification Recipient

## Current Situation
Email notifications are currently being sent to: **`admin@lagunatools.com`**

## Quick Fix - Change Email Recipient

### Step 1: Edit Environment File
**File**: `.env` (in project root)  
**Line**: 22

**Change from**:
```env
NOTIFICATION_TO_EMAILS=admin@lagunatools.com
```

**Change to**:
```env
NOTIFICATION_TO_EMAILS=your-new-email@lagunatools.com
```

### Step 2: For Multiple Recipients (Optional)
If you want multiple people to receive notifications:
```env
NOTIFICATION_TO_EMAILS=email1@lagunatools.com,email2@lagunatools.com,email3@lagunatools.com
```

### Step 3: Test the Change
1. Visit: `http://your-domain/test-email.php`
2. Click "Send Test Order Notification"
3. Check that the email arrives at your new address

## What Gets Notified

The system sends email notifications for:

### ✅ Order Processing Events
- **Order Successfully Processed** - When an order is successfully created in NetSuite
- **Order Processing Failed** - When an order fails to process
- **Batch Processing Completed** - When multiple orders are processed
- **Manual Upload Completed** - When orders are uploaded via CSV/Excel

### ✅ System Errors
- **Webhook Processing Failed** - When webhook processing encounters errors
- **API Failures** - When NetSuite or 3DCart API calls fail
- **System Exceptions** - When unexpected errors occur

## Backup Configuration Locations

If the `.env` file doesn't exist or the environment variable isn't set, the system falls back to:

### Fallback 1: Main Config File
**File**: `config/config.php`  
**Line**: 57  
**Current**: `'seun_sodimu@lagunatools.com'`

### Fallback 2: AWS Config File  
**File**: `aws-deployment/config/aws-config.php`  
**Line**: 122  
**Current**: `'seun_sodimu@lagunatools.com'`

## Other Email Settings You Can Change

### From Email Address
```env
NOTIFICATION_FROM_EMAIL=noreply@lagunatools.com
NOTIFICATION_FROM_NAME="3DCart Integration"
```

### Disable All Notifications
```env
NOTIFICATION_ENABLED=false
```

## Files That Send Notifications

### Order Processing Notifications
- `src/Controllers/WebhookController.php` - Webhook order processing
- `src/Controllers/OrderController.php` - Manual order uploads

### Error Notifications  
- `src/Controllers/WebhookController.php` - System errors and failures

### Email Service Implementation
- `src/Services/UnifiedEmailService.php` - Main email service
- `src/Services/BrevoEmailService.php` - Brevo email provider

## Verification

After making the change:

1. **Check current config**:
   ```php
   <?php
   $config = require 'config/config.php';
   echo "Current recipients: " . implode(', ', $config['notifications']['to_emails']);
   ?>
   ```

2. **Send test email**: Visit `public/test-email.php`

3. **Monitor logs**: Check `logs/app-*.log` for email sending confirmations

## Summary

**To change email notifications from `admin@lagunatools.com`:**

1. Edit `.env` file, line 22: `NOTIFICATION_TO_EMAILS=new-email@domain.com`
2. Test using `public/test-email.php`
3. Monitor logs to confirm emails are being sent

That's it! The change takes effect immediately.