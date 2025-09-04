# 3DCart Order Status Update Implementation

## Overview

After an order has been successfully synced to NetSuite, the system now automatically updates the order status in 3DCart to reflect the processing result. This provides visibility into which orders have been processed and helps with order management workflow.

## Implementation Details

### Configuration

**File**: `config/config.php`  
**Section**: `order_processing`

```php
'order_processing' => [
    // ... existing settings ...
    
    // 3DCart Order Status Updates
    'update_3dcart_status' => $_ENV['UPDATE_3DCART_STATUS'] ?? true, // Enable/disable status updates
    'success_status_id' => $_ENV['SUCCESS_STATUS_ID'] ?? 2, // Status ID for successfully processed orders (2 = Processing)
    'error_status_id' => $_ENV['ERROR_STATUS_ID'] ?? 5, // Status ID for failed orders (5 = Cancelled)
    'status_comments' => $_ENV['STATUS_COMMENTS'] ?? true, // Add comments when updating status
],
```

**Environment Variables** (`.env` file):
```env
# 3DCart Order Status Updates
UPDATE_3DCART_STATUS=true
SUCCESS_STATUS_ID=2
ERROR_STATUS_ID=5
STATUS_COMMENTS=true
```

### Status ID Reference

| Status ID | Status Name | Usage |
|-----------|-------------|-------|
| 1 | New | Default for new orders |
| 2 | Processing | **Success Status** - Order synced to NetSuite |
| 3 | Partial | Partially fulfilled orders |
| 4 | Shipped | Orders that have been shipped |
| 5 | Cancelled | **Error Status** - Processing failed |
| 6 | Not Completed | Incomplete orders |
| 7 | Unpaid | Unpaid orders |
| 8 | Backordered | Backordered items |
| 9 | Pending Review | Orders pending review |
| 10 | Partially Shipped | Partially shipped orders |

### Implementation Points

#### 1. WebhookController Integration

**File**: `src/Controllers/WebhookController.php`

**Success Path**:
```php
// After successful NetSuite order creation
$this->update3DCartOrderStatus($orderId, 'success', $netSuiteOrder['id']);
```

**Error Path**:
```php
// After processing failure
$this->update3DCartOrderStatus($orderId, 'error', null, $e->getMessage());
```

#### 2. OrderController Integration

**File**: `src/Controllers/OrderController.php`

**Success Path**:
```php
// After successful manual upload processing
$this->update3DCartOrderStatus($orderId, 'success', $netSuiteOrder['id']);
```

**Error Path**:
```php
// After manual upload processing failure
$this->update3DCartOrderStatus($orderId, 'error', null, $e->getMessage());
```

#### 3. ThreeDCartService Method

**File**: `src/Services/ThreeDCartService.php`

**Existing Method**: `updateOrderStatus($orderId, $statusId, $comments = '')`

**API Call**:
```php
PUT /Orders/{orderId}
Headers:
- SecureURL: https://lagunaedi.3dcartstores.com
- PrivateKey: fd9a91c51e39640778f0a64b8efdd4b3
- Token: 06184e8fbaae7ab0a58e62e47b0b1e6c
- Content-Type: application/json

Body:
{
    "OrderStatusID": 2,
    "InternalComments": "Order successfully synced to NetSuite. NetSuite Order ID: 12345"
}
```

## Status Update Logic

### Success Status Update

**When**: After successful NetSuite order creation  
**Status ID**: 2 (Processing)  
**Comments**: 
- Webhook: `"Order successfully synced to NetSuite. NetSuite Order ID: {netSuiteOrderId}"`
- Manual Upload: `"Order successfully synced to NetSuite via manual upload. NetSuite Order ID: {netSuiteOrderId}"`

### Error Status Update

**When**: After processing failure (max retries exceeded)  
**Status ID**: 5 (Cancelled)  
**Comments**: 
- Webhook: `"Order processing failed: {errorMessage}"`
- Manual Upload: `"Manual upload processing failed: {errorMessage}"`

### Configuration Options

#### Enable/Disable Status Updates
```env
UPDATE_3DCART_STATUS=false  # Disables all status updates
```

#### Custom Status IDs
```env
SUCCESS_STATUS_ID=4  # Use "Shipped" instead of "Processing"
ERROR_STATUS_ID=6    # Use "Not Completed" instead of "Cancelled"
```

#### Disable Comments
```env
STATUS_COMMENTS=false  # Don't add comments to status updates
```

## Error Handling

### Non-Blocking Errors
- Status update failures **do not** prevent order processing
- Errors are logged but don't affect the main workflow
- Email notifications are sent for status update failures

### Logging
```php
// Success
$this->logger->info('3DCart order status updated successfully', [
    'order_id' => $orderId,
    'status_id' => $statusId,
    'type' => $type,
    'result' => $result
]);

// Error
$this->logger->error('Failed to update 3DCart order status', [
    'order_id' => $orderId,
    'type' => $type,
    'error' => $e->getMessage(),
    'netsuite_order_id' => $netSuiteOrderId
]);
```

### Email Notifications
Status update failures trigger error notifications:
```php
$this->emailService->sendErrorNotification(
    "Failed to update 3DCart order status for Order #{$orderId}: " . $e->getMessage(),
    [
        'order_id' => $orderId,
        'type' => $type,
        'netsuite_order_id' => $netSuiteOrderId
    ]
);
```

## Testing

### Test Script
**File**: `test-3dcart-status-update.php`

**Features**:
- Tests order status retrieval
- Tests status updates (success and error)
- Verifies status changes
- Restores original status
- Shows available status IDs

**Usage**:
```bash
php test-3dcart-status-update.php
```

### Manual Testing

1. **Process an order** via webhook or manual upload
2. **Check 3DCart admin** - order status should be updated to "Processing" (ID: 2)
3. **Check logs** - should show status update success
4. **Simulate failure** - disable NetSuite temporarily, process order
5. **Check 3DCart admin** - order status should be updated to "Cancelled" (ID: 5)

## Workflow Integration

### Complete Order Processing Flow

1. **Order Received** (3DCart webhook or manual upload)
2. **Order Validation** (validate order data)
3. **Customer Processing** (find/create customer in NetSuite)
4. **NetSuite Order Creation** (create sales order)
5. **✅ SUCCESS PATH**:
   - Update 3DCart status to "Processing" (ID: 2)
   - Add comment with NetSuite Order ID
   - Send success email notification
6. **❌ ERROR PATH**:
   - Update 3DCart status to "Cancelled" (ID: 5)
   - Add comment with error message
   - Send error email notification

### Benefits

1. **Visibility**: Clear indication of which orders have been processed
2. **Workflow**: Orders can be filtered by status in 3DCart admin
3. **Tracking**: NetSuite Order ID is stored in comments for reference
4. **Error Management**: Failed orders are clearly marked
5. **Audit Trail**: Complete processing history in order comments

## Customization

### Custom Status Mapping

To use different status IDs, update the configuration:

```env
# Use "Shipped" for successful orders
SUCCESS_STATUS_ID=4

# Use "Pending Review" for failed orders  
ERROR_STATUS_ID=9
```

### Custom Comments

Modify the `update3DCartOrderStatus` method in controllers to customize comments:

```php
// Custom success comment
$comments = "Processed by Integration System on " . date('Y-m-d H:i:s') . ". NetSuite ID: {$netSuiteOrderId}";

// Custom error comment
$comments = "Processing failed at " . date('Y-m-d H:i:s') . ": " . $errorMessage;
```

### Conditional Updates

Add business logic to conditionally update status:

```php
// Only update status for certain payment methods
if ($orderData['BillingPaymentMethod'] !== 'Dropship to Customer') {
    $this->update3DCartOrderStatus($orderId, 'success', $netSuiteOrderId);
}
```

## Summary

The 3DCart order status update feature provides:

- ✅ **Automatic status updates** after order processing
- ✅ **Configurable status IDs** for success and error cases
- ✅ **Detailed comments** with NetSuite Order IDs and error messages
- ✅ **Non-blocking error handling** - doesn't affect main workflow
- ✅ **Comprehensive logging** and email notifications
- ✅ **Easy configuration** via environment variables
- ✅ **Testing tools** for verification

This enhancement improves order management workflow by providing clear visibility into which orders have been successfully processed and synced to NetSuite.