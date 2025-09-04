# Dropship Customer Duplicate Creation Fix

## Problem Description

Order #1145999 was failing with the error:
```
"A customer record with this ID already exists. You must enter a unique customer ID for each record you create."
```

### Root Cause Analysis

1. **Initial Success**: The first attempt successfully created:
   - Dropship customer (ID: 471324)
   - Sales order (NetSuite ID: 276483)

2. **Response Parsing Error**: NetSuite returned a 204 status code with the order ID in the Location header, but the system was trying to parse JSON from an empty response body, causing a "Trying to access array offset on value of type null" error.

3. **Retry Attempts**: The parsing error triggered retry logic, which attempted to create the same customer again, resulting in the duplicate customer error.

## Solution Implemented

### 1. Case-Insensitive NetSuite Queries

**Problem**: NetSuite's SuiteQL is case-sensitive, causing customer searches to fail when email cases don't match exactly.

**Example**: 
- Query: `WHERE email = 'store33@rockler.com'` → **No results**
- Actual email in NetSuite: `Store33@rockler.com`
- Fixed query: `WHERE LOWER(email) = LOWER('store33@rockler.com')` → **Success**

**Files Updated**: `src/Services/NetSuiteService.php`

**Methods Modified**:
- `findCustomerByEmail()` - Email searches
- `findParentCompanyCustomer()` - Billing email searches  
- `findStoreCustomer()` - Store customer email searches
- `findExistingDropshipCustomer()` - Name searches
- Person customer search by entityid
- Company name keyword searches

**Query Changes**:
```sql
-- Before (case-sensitive)
WHERE email = 'user@domain.com'
WHERE firstName = 'John' AND lastName = 'Doe'
WHERE entityid = 'John Doe'
WHERE companyName LIKE '%keyword%'

-- After (case-insensitive)  
WHERE LOWER(email) = LOWER('user@domain.com')
WHERE LOWER(firstName) = LOWER('John') AND LOWER(lastName) = LOWER('Doe')
WHERE LOWER(entityid) = LOWER('John Doe')
WHERE LOWER(companyName) LIKE '%keyword%'
```

### 2. Dropship Customer Search Before Creation

**File**: `src/Services/NetSuiteService.php`

**Method Added**: `findExistingDropshipCustomer()`
- Searches for existing dropship customers by firstName, lastName, and parent company
- Uses SuiteQL query with proper SQL escaping
- Returns existing customer if found, null otherwise

**Method Modified**: `handleDropshipCustomer()`
- Now checks for existing customers before attempting creation
- Uses existing customer ID if found
- Only creates new customer if none exists

```php
// Check if dropship customer already exists
$existingCustomer = $this->findExistingDropshipCustomer($customerData, $parentCustomerId);
if ($existingCustomer) {
    $this->logger->info('Found existing dropship customer', [
        'customer_id' => $existingCustomer['id'],
        'firstName' => $existingCustomer['firstName'] ?? 'N/A',
        'lastName' => $existingCustomer['lastName'] ?? 'N/A',
        'parent_id' => $parentCustomerId
    ]);
    return $existingCustomer['id'];
}
```

### 3. Proper 204 Response Handling for Sales Orders

**File**: `src/Services/NetSuiteService.php`

**Method Modified**: `createSalesOrder()`
- Added proper handling for 204 No Content responses
- Extracts order ID from Location header
- Supports both 204 (Location header) and 200/201 (response body) formats

```php
if ($statusCode === 204) {
    // 204 No Content - record created successfully, ID in Location header
    $locationHeader = $response->getHeader('Location');
    if (!empty($locationHeader)) {
        $location = $locationHeader[0];
        // Extract ID from Location header (e.g., .../salesOrder/123456)
        if (preg_match('/\/salesOrder\/(\d+)$/', $location, $matches)) {
            $salesOrderId = $matches[1];
            $createdOrder = ['id' => $salesOrderId];
        }
    }
}
```

## Test Results

### Order #1145999 Analysis
- **Customer Email**: veth1241589@gmail.com (from QuestionList)
- **Billing Email**: landerson@acmetools.com
- **Parent Company**: Found (ID: 6820)
- **Dropship Customer**: Found existing (ID: 471324)
- **Sales Order**: Already exists (NetSuite ID: 276483)

### Expected Behavior After Fix
1. **First Attempt**: Create customer and order successfully, handle 204 response correctly
2. **Retry Attempts** (if needed): Find existing customer, skip creation, continue processing
3. **No Errors**: No duplicate customer errors, no response parsing failures

## Files Modified

1. **src/Services/NetSuiteService.php**
   - Added `findExistingDropshipCustomer()` method
   - Modified `handleDropshipCustomer()` method
   - Enhanced `createSalesOrder()` method for 204 response handling

## Testing

Run the test script to verify the fix:
```bash
php test-dropship-customer-fix.php
```

## Impact

- ✅ **Prevents duplicate customer creation errors**
- ✅ **Handles NetSuite 204 responses correctly**  
- ✅ **Reliable case-insensitive customer matching**
- ✅ **Reduces failed order processing attempts**
- ✅ **Improves system reliability for dropship orders**
- ✅ **Maintains data integrity in NetSuite**
- ✅ **Better customer detection regardless of email/name case**

## Technical Notes

### Dropship Customer Identification
Dropship customers are identified by:
- `firstName` from ShipmentList
- `lastName` from ShipmentList + invoice number (format: "LastName: InvoicePrefix-InvoiceNumber")
- `parent` company customer ID
- `isPerson = 'T'` (always true for dropship customers)

### NetSuite Response Patterns
- **204 No Content**: Record created, ID in Location header
- **200/201 OK**: Record created, full data in response body
- **400 Bad Request**: Validation errors, duplicate records, etc.

### Search Query Examples

**Case-Insensitive Email Search:**
```sql
SELECT id, firstName, lastName, email, companyName, phone, isperson 
FROM customer 
WHERE LOWER(email) = LOWER('Store33@rockler.com')
```

**Case-Insensitive Dropship Customer Search:**
```sql
SELECT id, firstName, lastName, email, companyName, phone, isperson, parent 
FROM customer 
WHERE LOWER(firstName) = LOWER('Veronica') 
  AND LOWER(lastName) = LOWER('Thomas: AB-39504') 
  AND isPerson = 'T' 
  AND parent = 6820
```

**Case-Insensitive Company Search:**
```sql
SELECT id, firstName, lastName, email, companyName, phone, isperson 
FROM customer 
WHERE LOWER(companyName) LIKE '%acmetools%' 
  AND LOWER(email) = LOWER('landerson@acmetools.com')
```

This comprehensive fix ensures robust handling of dropship customer creation, proper NetSuite response parsing, and reliable case-insensitive customer matching, preventing the duplicate customer errors that were causing order processing failures.