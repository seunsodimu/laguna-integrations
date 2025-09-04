# Dropship Customer Email Update

## Change Summary

**Date**: January 2025  
**Change**: Updated dropship customer creation to always create customers WITHOUT email addresses

## What Changed

### Before
- Dropship customers were created with email addresses extracted from `QuestionList[QuestionID=1]`
- Email validation was performed and valid emails were included in customer records

### After
- Dropship customers are **always created without email addresses**
- Email field is set to empty string regardless of valid email in QuestionList
- This applies only to dropship orders (`BillingPaymentMethod === 'Dropship to Customer'`)

## Technical Implementation

### File Modified
- `src/Services/NetSuiteService.php`

### Method Updated
- `buildDropshipCustomerData()` - Changed email assignment from conditional to always empty

### Code Change
```php
// Before
'email' => $isValidEmail ? $customerEmail : '',

// After  
'email' => '', // Always empty for dropship customers
```

## Impact

### Dropship Orders (`BillingPaymentMethod === 'Dropship to Customer'`)
- ✅ Customer created as person (`isPerson = true`)
- ✅ Invoice number appended to lastname
- ✅ **Email field always empty**
- ✅ Parent company search still functions
- ✅ All other customer data preserved

### Regular Orders (All other payment methods)
- ✅ **No changes** - still use validated email from QuestionList
- ✅ Store customer search still functions
- ✅ Company customer creation unchanged

## Testing

### Test File Created
- `test-dropship-no-email.php` - Comprehensive test verifying the change

### Test Results
```
✅ Email field is empty as expected for dropship customer
✅ isPerson is true as expected for dropship customer  
✅ Invoice number found in lastname
✅ Customer creation successful (ID: 468927)
✅ Regular customers still have email addresses
```

## Verification

To verify this change is working correctly:

1. **Check Logs**: Look for log entry with `note: 'Dropship customers created without email address'`
2. **NetSuite Records**: Verify dropship customers have empty email fields
3. **Regular Orders**: Confirm regular orders still have email addresses

## Backward Compatibility

- ✅ **No breaking changes** - existing functionality preserved
- ✅ **Regular orders unchanged** - still process emails normally
- ✅ **Parent company search** - still functions for both order types
- ✅ **Customer creation** - all other fields and logic unchanged

## Monitoring

Monitor these log entries to confirm correct operation:

```
Processing dropship customer - note: 'Dropship customers created without email address'
Built dropship customer data - note: 'Email always empty for dropship customers'
```

## Summary

This targeted change ensures that:
- **Dropship customers** are created without email addresses as requested
- **Regular customers** continue to use email addresses from QuestionList
- **All other functionality** remains unchanged
- **System reliability** is maintained with comprehensive testing

The change is production-ready and has been thoroughly tested.