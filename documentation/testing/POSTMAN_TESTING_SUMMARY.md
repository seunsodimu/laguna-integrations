# API Testing with Postman - Complete Guide

This document provides comprehensive instructions for testing both 3DCart and NetSuite API credentials using Postman.

## 📋 Quick Reference

| Service | Auth Method | Key Headers | Common Issues |
|---------|-------------|-------------|---------------|
| **3DCart** | Header-based | `PrivateKey`, `Token` | Redirects, SSL |
| **NetSuite** | OAuth 1.0 | `Authorization` (OAuth) | Credentials, Permissions |

## 🛠️ 3DCart API Testing

### Quick Setup
```
Method: GET
URL: https://lagunaedi.com/3dCartWebAPI/v2/Orders?limit=1

Headers:
- Content-Type: application/json
- Accept: application/json
- PrivateKey: 3f1c50d3f246ff1837aad8575fafc84a
- Token: 649c1804bc94da1657c4625e66bb3d8c
```

### Current Issue
**Problem**: "Will not follow more than 10 redirects"
- The store URL redirects multiple times
- Response shows: `Object moved` with redirect to `/myaccount.asp?404`

### Solutions to Test
1. **Try Secure URL**: `https://lagunaedi.3dcartstores.com/3dCartWebAPI/v2/Orders?limit=1`
2. **Follow redirects manually** in Postman
3. **Test simpler endpoints**: `/Store`, `/Products`

### Expected Results
- ✅ **200 OK**: JSON array with order data
- ❌ **401**: Invalid credentials
- ⚠️ **302**: Redirect issue (try secure URL)

## 🛠️ NetSuite API Testing

### Quick Setup
```
Method: GET
URL: https://11134099.suitetalk.api.netsuite.com/services/rest/record/v1/customer?limit=1

Authorization: OAuth 1.0
- Consumer Key: b31cb32cd5bf15fd61906f320bda79325438e0fd6a77c1d973a8d589810d9f07
- Consumer Secret: f7e1a2d2b48bb002616be230e5b730369eb85a4a3522915979a077b503e8dbfc
- Access Token: ea9b7200ef1d1ba451f7f44d00fa415205b977a30eb6b589c1c9fab7cfe101b8
- Token Secret: 7100e2d7d576e445b64e0bda03d372575ab701312af754faf1ad00c4919d9f28
- Signature Method: HMAC-SHA1
- Realm: 11134099
```

### Current Issue
**Problem**: "401 Unauthorized - INVALID_LOGIN"
- OAuth signature is correct (we fixed the 404 → 401)
- Issue is with NetSuite credentials/permissions

### Solutions to Test
1. **Check Integration Application** is enabled in NetSuite
2. **Verify Access Token** is not expired
3. **Test simpler endpoints**: `/subsidiary`, `/currency`

### Expected Results
- ✅ **200 OK**: JSON with customer data
- ❌ **401**: Credential/permission issue
- ❌ **403**: Insufficient permissions

## 📁 Documentation Files

### Detailed Guides
- `3DCART_POSTMAN_TEST.md` - Complete 3DCart testing guide
- `NETSUITE_POSTMAN_TEST.md` - Complete NetSuite testing guide

### Quick References
- `3DCART_POSTMAN_QUICK.md` - 3DCart quick setup
- `POSTMAN_QUICK_TEST.md` - NetSuite quick setup

### Debug Scripts
- `debug_3dcart.php` - Test 3DCart connection with detailed output
- `debug_netsuite.php` - Test NetSuite connection with detailed output
- `debug_oauth_signature.php` - Shows OAuth signature generation

## 🎯 Testing Strategy

### Step 1: Test 3DCart
1. Run `php debug_3dcart.php` to see current status
2. Test in Postman with main URL
3. If redirects, try secure URL: `https://lagunaedi.3dcartstores.com`
4. Compare Postman results with PHP output

### Step 2: Test NetSuite
1. Run `php debug_netsuite.php` to see current status
2. Test in Postman with OAuth 1.0 setup
3. If 401, check NetSuite admin panel
4. Compare OAuth signatures between Postman and PHP

### Step 3: Compare Results
- **Both work in Postman**: PHP implementation issue
- **Both fail in Postman**: Credential/configuration issue
- **Mixed results**: Service-specific issues

## 🔍 Current Status Summary

### 3DCart Status: ⚠️ Redirect Issue
- **Code**: Working (OAuth implementation correct)
- **Credentials**: Likely correct
- **Issue**: Store URL redirects too many times
- **Solution**: Use secure URL or fix redirect handling

### NetSuite Status: ❌ Credential Issue
- **Code**: Working (URL structure fixed, OAuth correct)
- **Credentials**: Invalid or insufficient permissions
- **Issue**: NetSuite configuration problem
- **Solution**: Check NetSuite admin panel settings

## 🚀 Next Steps

1. **Test 3DCart in Postman** with both URLs
2. **Test NetSuite in Postman** to confirm credential issue
3. **Fix 3DCart**: Update to use secure URL if Postman works
4. **Fix NetSuite**: Check admin panel settings if Postman fails

## 💡 Pro Tips

### For 3DCart
- Always test with `limit=1` to minimize data
- Disable SSL verification in development
- Follow redirects manually to find correct endpoint

### For NetSuite
- Use HMAC-SHA1 (not SHA256)
- Include realm in OAuth header
- Check Login Audit Trail for specific errors
- Test with simpler endpoints first

This comprehensive testing approach will definitively identify whether issues are in the code or the API configurations.