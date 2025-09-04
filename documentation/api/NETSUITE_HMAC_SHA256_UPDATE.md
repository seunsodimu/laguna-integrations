# NetSuite HMAC-SHA256 Signature Method Update

## Overview

The NetSuite integration has been updated to use **HMAC-SHA256** as the default signature method for OAuth 1.0 authentication, replacing the previous HMAC-SHA1 method. This provides enhanced security and compliance with modern cryptographic standards.

## What Changed

### Before (HMAC-SHA1)
```php
'oauth_signature_method' => 'HMAC-SHA1'
$signature = base64_encode(hash_hmac('sha1', $baseString, $signingKey, true));
```

### After (HMAC-SHA256)
```php
'oauth_signature_method' => 'HMAC-SHA256'
$signature = base64_encode(hash_hmac('sha256', $baseString, $signingKey, true));
```

## Configuration

### Setting Signature Method

In your `config/credentials.php` file, you can now specify the signature method:

```php
'netsuite' => [
    'account_id' => 'your-netsuite-account-id',
    'consumer_key' => 'your-consumer-key',
    'consumer_secret' => 'your-consumer-secret',
    'token_id' => 'your-token-id',
    'token_secret' => 'your-token-secret',
    'base_url' => 'https://your-account-id.suitetalk.api.netsuite.com',
    'rest_api_version' => 'v1',
    'signature_method' => 'HMAC-SHA256', // Options: 'HMAC-SHA256' or 'HMAC-SHA1'
],
```

### Default Behavior

- **Default**: HMAC-SHA256 (if not specified)
- **Fallback**: If an invalid method is specified, defaults to HMAC-SHA256
- **Legacy Support**: HMAC-SHA1 is still supported for backward compatibility

## Benefits of HMAC-SHA256

1. **Enhanced Security**: SHA-256 provides stronger cryptographic protection than SHA-1
2. **Collision Resistance**: Better resistance to hash collision attacks
3. **Industry Standard**: Aligns with modern security best practices
4. **Future-Proof**: Ensures compatibility with evolving security requirements

## Testing the Update

### 1. Test Connection
```bash
php test_netsuite_hmac_sha256.php
```

### 2. Debug Signature Generation
```bash
php debug_netsuite_signature.php
```

### 3. Standard NetSuite Test
```bash
php debug_netsuite.php
```

## Troubleshooting

### Common Issues

#### 1. Authentication Failures
**Symptoms**: 401 Unauthorized errors after the update

**Solutions**:
- Verify your NetSuite integration record supports HMAC-SHA256
- Check that your consumer key and token are still valid
- Ensure your NetSuite account has the required permissions

#### 2. Signature Method Not Supported
**Symptoms**: "Invalid signature method" errors

**Solutions**:
- Update your NetSuite integration record to support HMAC-SHA256
- Temporarily switch back to HMAC-SHA1 if needed:
  ```php
  'signature_method' => 'HMAC-SHA1'
  ```

#### 3. Legacy NetSuite Versions
**Symptoms**: Connection works with HMAC-SHA1 but fails with HMAC-SHA256

**Solutions**:
- Check your NetSuite version compatibility
- Use HMAC-SHA1 for older NetSuite versions
- Contact NetSuite support for upgrade options

### NetSuite Integration Record Requirements

Ensure your NetSuite Integration Record has:
1. **Token-Based Authentication** enabled
2. **REST Web Services** permission
3. **HMAC-SHA256** signature method support (for newer NetSuite versions)

### Debugging Steps

1. **Check Current Method**:
   ```php
   $service = new NetSuiteService();
   echo $service->getSignatureMethod(); // Should show HMAC-SHA256
   ```

2. **Compare Signatures**:
   The debug script shows both HMAC-SHA1 and HMAC-SHA256 signatures for comparison

3. **Review Logs**:
   Check `logs/app-*.log` for detailed error messages

## Rollback Instructions

If you need to revert to HMAC-SHA1:

1. **Update Configuration**:
   ```php
   'signature_method' => 'HMAC-SHA1'
   ```

2. **Test Connection**:
   ```bash
   php debug_netsuite.php
   ```

## Security Considerations

### Why HMAC-SHA256?

- **SHA-1 Deprecation**: SHA-1 is considered cryptographically weak
- **Compliance**: Many security standards now require SHA-256 or higher
- **Future-Proofing**: Ensures long-term security of your integration

### Migration Timeline

- **Immediate**: HMAC-SHA256 is now the default
- **Legacy Support**: HMAC-SHA1 remains available for compatibility
- **Future**: HMAC-SHA1 support may be deprecated in future versions

## Support

### If You Need Help

1. **Test Scripts**: Use the provided test scripts to diagnose issues
2. **Logs**: Check application logs for detailed error information
3. **NetSuite Support**: Contact NetSuite if integration record updates are needed
4. **Documentation**: Review NetSuite's OAuth 1.0 documentation

### Contact Information

For integration-specific issues:
- Review the troubleshooting section above
- Check NetSuite's Login Audit Trail
- Verify integration record configuration

## Version History

- **v1.0**: Initial HMAC-SHA1 implementation
- **v2.0**: Added HMAC-SHA256 support with configurable signature method
- **Current**: HMAC-SHA256 as default with HMAC-SHA1 fallback support