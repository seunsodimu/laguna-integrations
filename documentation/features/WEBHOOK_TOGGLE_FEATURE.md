# 🔄 Webhook Toggle Feature

## Overview

The webhook toggle feature allows administrators to easily enable or disable 3DCart webhook processing without modifying configuration files directly. This provides better control over order processing and system maintenance.

## ✨ Features

### 1. **Configuration Setting**
- New `webhook.enabled` setting in `config/config.php`
- Environment variable support via `WEBHOOK_ENABLED`
- Default value: `true` (enabled)

### 2. **Webhook Endpoint Protection**
- Webhook requests are checked against the enabled status
- Disabled webhooks return HTTP 503 (Service Unavailable)
- Proper logging of disabled webhook attempts

### 3. **Management Interface**
- Dedicated webhook settings page at `/webhook-settings.php`
- One-click enable/disable functionality
- Visual status indicators
- Confirmation dialogs for safety

### 4. **Status Monitoring**
- Webhook status displayed in main dashboard
- Status page shows webhook enabled/disabled state
- Webhook information page shows current status

## 🚀 Usage

### Via Web Interface

1. **Access Settings Page**
   ```
   Navigate to: /webhook-settings.php
   ```

2. **Toggle Status**
   - Click "🛑 Disable Webhook Processing" to disable
   - Click "✅ Enable Webhook Processing" to enable
   - Confirm the action when prompted

### Via Configuration File

1. **Edit config/config.php**
   ```php
   'webhook' => [
       'enabled' => false, // Set to false to disable
       'secret_key' => $_ENV['WEBHOOK_SECRET'] ?? 'your-webhook-secret-key',
       'timeout' => 30,
   ],
   ```

### Via Environment Variables

1. **Set Environment Variable**
   ```bash
   # In .env file or system environment
   WEBHOOK_ENABLED=false
   ```

## 📊 Status Indicators

### Dashboard Display
- ✅ **Enabled**: Webhook Processing: ✅ Enabled
- ⚠️ **Disabled**: Webhook Processing: ⚠️ Disabled

### Status Page Display
- Shows "Webhook Enabled: ✅ Yes" or "Webhook Enabled: ❌ No"

### Webhook Information Page
- **Enabled**: Green status box with "✅ Endpoint Status: Enabled"
- **Disabled**: Orange warning box with "⚠️ Endpoint Status: Disabled"

## 🔧 Technical Implementation

### Configuration Structure
```php
// config/config.php
'webhook' => [
    'enabled' => $_ENV['WEBHOOK_ENABLED'] ?? true,
    'secret_key' => $_ENV['WEBHOOK_SECRET'] ?? 'your-webhook-secret-key',
    'timeout' => 30,
],
```

### Webhook Endpoint Logic
```php
// public/webhook.php
if (!$config['webhook']['enabled']) {
    $logger->info('Webhook request received but webhook processing is disabled');
    
    http_response_code(503);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Webhook processing is currently disabled',
        'message' => 'Contact administrator to enable webhook processing',
        'timestamp' => date('c')
    ]);
    exit;
}
```

### Status Controller Integration
```php
// src/Controllers/StatusController.php
$configStatus = [
    // ... other status checks
    'webhook_enabled' => $this->config['webhook']['enabled'],
    // ... more status checks
];
```

## 🎯 Use Cases

### 1. **System Maintenance**
- Disable webhooks during system updates
- Prevent order processing during maintenance windows
- Safely restart services without losing webhook data

### 2. **Debugging and Testing**
- Temporarily disable automatic processing
- Test manual order processing workflows
- Debug integration issues without interference

### 3. **Emergency Situations**
- Quickly stop order processing if issues are detected
- Prevent cascading failures in downstream systems
- Buy time to investigate and resolve problems

### 4. **Controlled Rollouts**
- Gradually enable webhook processing after updates
- Test with limited order volume before full activation
- Rollback capability if issues arise

## ⚠️ Important Considerations

### When Webhook is Disabled

1. **Automatic Processing Stops**
   - Orders from 3DCart will not be processed automatically
   - Webhook requests return HTTP 503 status
   - No NetSuite sales orders will be created automatically

2. **Manual Processing Still Available**
   - Upload feature continues to work
   - Order synchronization feature remains functional
   - Manual order processing is unaffected

3. **3DCart Configuration**
   - 3DCart webhook configuration is not affected
   - 3DCart will continue sending webhook requests
   - Consider updating 3DCart settings if disabling long-term

### Logging and Monitoring

- All webhook status changes are logged
- Disabled webhook attempts are logged as INFO level
- Status changes include timestamp and source

## 🔄 Migration and Rollback

### Enabling Webhooks
```php
// Method 1: Via settings page
// Navigate to /webhook-settings.php and click "Enable"

// Method 2: Via configuration
'enabled' => true,

// Method 3: Via environment
WEBHOOK_ENABLED=true
```

### Disabling Webhooks
```php
// Method 1: Via settings page
// Navigate to /webhook-settings.php and click "Disable"

// Method 2: Via configuration
'enabled' => false,

// Method 3: Via environment
WEBHOOK_ENABLED=false
```

## 📁 Files Modified

### New Files
- `public/webhook-settings.php` - Webhook management interface
- `documentation/features/WEBHOOK_TOGGLE_FEATURE.md` - This documentation

### Modified Files
- `config/config.php` - Added webhook.enabled setting
- `public/webhook.php` - Added enabled status check
- `public/index.php` - Added webhook status display and settings link
- `src/Controllers/StatusController.php` - Added webhook status monitoring
- `config/credentials.example.php` - Added webhook configuration documentation

## 🚀 Future Enhancements

### Potential Improvements
1. **Scheduled Enable/Disable** - Set specific times for webhook activation
2. **Rate Limiting Integration** - Disable webhooks when rate limits are exceeded
3. **Health Check Integration** - Auto-disable on service failures
4. **Webhook Queue** - Store disabled webhook requests for later processing
5. **API Endpoint** - RESTful API for webhook status management

### Monitoring Enhancements
1. **Webhook Metrics** - Track enabled/disabled time periods
2. **Alert Integration** - Notify administrators of status changes
3. **Dashboard Widgets** - Real-time webhook status monitoring
4. **Historical Reporting** - Track webhook availability over time

## 🎉 Benefits

### Operational Benefits
- **Improved Control**: Easy webhook management without file editing
- **Reduced Downtime**: Quick disable during maintenance
- **Better Debugging**: Isolate webhook issues easily
- **Emergency Response**: Rapid response to system issues

### User Experience Benefits
- **Visual Interface**: Clear status indicators and controls
- **Safety Features**: Confirmation dialogs prevent accidents
- **Comprehensive Information**: Clear explanations of impact
- **Easy Navigation**: Integrated with existing dashboard

### Technical Benefits
- **Clean Implementation**: Follows existing code patterns
- **Proper Logging**: All actions are logged appropriately
- **Status Integration**: Works with existing monitoring
- **Environment Support**: Flexible configuration options

---

**Feature Status**: ✅ **COMPLETE**  
**Implementation Date**: August 7, 2025  
**Version**: 1.0.0  
**Compatibility**: All existing features maintained