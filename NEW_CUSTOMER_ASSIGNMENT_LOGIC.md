# New Customer Assignment Logic Implementation

## Overview

This document describes the new customer assignment logic implemented for the 3DCart to NetSuite integration system. The logic is based on the `BillingPaymentMethod` field from the 3DCart payload and replaces the previous customer assignment approach.

## Implementation Details

### Main Entry Point

**Method**: `NetSuiteService::findOrCreateCustomerByPaymentMethod($orderData)`
**Location**: `src/Services/NetSuiteService.php`

This method determines customer assignment based on the `BillingPaymentMethod` field and handles both dropship and regular orders.

### Logic Flow

#### 1. Email Extraction and Validation

- Extracts customer email from `QuestionList` where `QuestionID = 1`
- Validates email format using `filter_var($email, FILTER_VALIDATE_EMAIL)`
- Sets `$isValidEmail` flag for downstream processing

#### 2. Payment Method Decision

**If `BillingPaymentMethod === 'Dropship to Customer'`:**
- Calls `handleDropshipCustomer()` method
- Creates person customer (`isPerson = true`)
- Appends invoice number to lastname
- **Always creates customer WITHOUT email address**

**If `BillingPaymentMethod !== 'Dropship to Customer'`:**
- Calls `handleRegularCustomer()` method  
- Searches for existing store customer first
- Creates company customer (`isPerson = false`) if not found

### Dropship Customer Logic

**Method**: `handleDropshipCustomer($orderData, $customerEmail, $isValidEmail)`

1. **Parent Company Search**: Uses `findParentCompanyCustomer()` to search for parent company
2. **Customer Creation**: Creates person customer with:
   - `firstname`: From `ShipmentList[0]->ShipmentFirstName`
   - `lastname`: From `ShipmentList[0]->ShipmentLastName` + `: {InvoiceNumberPrefix}{InvoiceNumber}`
   - `isPerson`: `true`
   - `email`: **Always empty string** (dropship customers created without email)
   - `parent`: Parent company ID (if found) or null

### Regular Customer Logic

**Method**: `handleRegularCustomer($orderData, $customerEmail, $isValidEmail)`

1. **Store Customer Search**: If valid email, searches for existing store customer using `findStoreCustomer()`
2. **Return Existing**: If store customer found, returns existing customer ID
3. **Parent Company Search**: Uses `findParentCompanyCustomer()` to search for parent company
4. **Customer Creation**: Creates company customer with:
   - `firstname`: Empty string
   - `lastname`: Empty string
   - `isPerson`: `false`
   - `email`: Customer email (if valid) or empty string
   - `company`: From `BillingCompany` or constructed from shipment name
   - `parent`: Parent company ID (if found) or null

### Database Queries

#### Parent Company Query
```sql
SELECT id, firstName, lastName, email, companyName, phone, isperson 
FROM customer 
WHERE (email = '{BillingEmail}' OR phone = '{BillingPhoneNumber}') 
AND isperson = 'F'
```

#### Store Customer Query
```sql
SELECT id, firstName, lastName, email, companyName, phone, isperson 
FROM customer 
WHERE email = '{QuestionList->QuestionAnswer}' 
AND isperson = 'F'
```

### Security Features

- **SQL Injection Prevention**: All email and phone values are escaped using `str_replace("'", "''", $value)`
- **Email Validation**: Uses PHP's `filter_var()` with `FILTER_VALIDATE_EMAIL`
- **Input Sanitization**: All customer data fields are validated and truncated as needed

## Integration Points

### Updated Controllers

1. **WebhookController**: Updated both `processOrder()` and `processOrderFromWebhookData()` methods
2. **OrderController**: Updated `processOrdersFromFile()` method
3. **OrderProcessingService**: Updated `findOrCreateCustomer()` method

### Backward Compatibility

- Old methods (`findParentCustomer()`, `getOrCreateCustomer()`) are preserved but no longer used
- New logic is completely self-contained in new methods
- No breaking changes to existing API contracts

## Testing

### Test Files Created

1. **`test-new-customer-logic.php`**: Full integration test with actual customer creation
2. **`test-customer-logic-simple.php`**: Unit-style tests for individual methods using reflection

### Test Coverage

- ✅ Email extraction from QuestionList
- ✅ Email validation logic
- ✅ Parent company search functionality
- ✅ Dropship customer data building
- ✅ Regular customer data building
- ✅ SQL query escaping
- ✅ Invalid email handling

## Key Improvements

1. **Clear Logic Separation**: Dropship vs regular order handling is now explicit
2. **Better Email Handling**: Proper extraction from QuestionList and validation
3. **Enhanced Security**: SQL injection prevention and input validation
4. **Improved Logging**: Detailed logging at each step for debugging
5. **Parent Company Support**: Consistent parent-child relationship handling
6. **Customer Type Accuracy**: Proper `isPerson` flag setting based on order type

## Configuration

No additional configuration required. The logic uses existing NetSuite credentials and configuration from:
- `config/credentials.php`
- `config/config.php`

## Error Handling

- Comprehensive try-catch blocks with detailed error logging
- Graceful fallbacks when parent companies are not found
- Proper exception propagation to calling methods
- Detailed error context in logs for debugging

## Performance Considerations

- Efficient SuiteQL queries with proper indexing on email and phone fields
- Minimal API calls by combining search and creation logic
- Caching of reflection objects in test scenarios
- Optimized customer data building with minimal field processing

## Migration Notes

The new logic is automatically active for all new orders. No data migration is required as this only affects new customer creation, not existing customer records.

## Monitoring

Monitor the following log entries to verify correct operation:
- `Starting customer assignment by payment method`
- `Processing dropship customer` / `Processing regular customer`
- `Found parent company customer` / `No parent company customer found`
- `Found existing store customer` / `No store customer found`
- `Created new customer for order`

## Support

For issues or questions regarding the new customer assignment logic, check:
1. Application logs in `logs/` directory
2. NetSuite API response details in error messages
3. Test scripts for validation of individual components