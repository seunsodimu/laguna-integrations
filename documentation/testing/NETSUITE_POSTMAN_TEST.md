# Testing NetSuite Credentials in Postman

This guide will help you test your NetSuite REST API credentials using Postman to verify if the issue is with the credentials or the code implementation.

## Prerequisites

1. **Postman installed** (Download from https://www.postman.com/downloads/)
2. **Your NetSuite credentials** from `config/credentials.php`:
   - Account ID: `11134099`
   - Consumer Key: `b31cb32cd5bf15fd61906f320bda79325438e0fd6a77c1d973a8d589810d9f07`
   - Consumer Secret: `f7e1a2d2b48bb002616be230e5b730369eb85a4a3522915979a077b503e8dbfc`
   - Token ID: `ea9b7200ef1d1ba451f7f44d00fa415205b977a30eb6b589c1c9fab7cfe101b8`
   - Token Secret: `7100e2d7d576e445b64e0bda03d372575ab701312af754faf1ad00c4919d9f28`

## Step 1: Create a New Request in Postman

1. Open Postman
2. Click **"New"** → **"Request"**
3. Name it: `NetSuite Customer Test`
4. Save it to a collection (create one if needed)

## Step 2: Configure the Request

### Basic Request Setup
- **Method**: `GET`
- **URL**: `https://11134099.suitetalk.api.netsuite.com/services/rest/record/v1/customer?limit=1`

### Headers
Add these headers:
```
Content-Type: application/json
Accept: application/json
```

## Step 3: Configure OAuth 1.0 Authentication

1. Go to the **"Authorization"** tab
2. **Type**: Select `OAuth 1.0`
3. Fill in the OAuth 1.0 parameters:

### OAuth 1.0 Configuration
```
Consumer Key: b31cb32cd5bf15fd61906f320bda79325438e0fd6a77c1d973a8d589810d9f07
Consumer Secret: f7e1a2d2b48bb002616be230e5b730369eb85a4a3522915979a077b503e8dbfc
Access Token: ea9b7200ef1d1ba451f7f44d00fa415205b977a30eb6b589c1c9fab7cfe101b8
Token Secret: 7100e2d7d576e445b64e0bda03d372575ab701312af754faf1ad00c4919d9f28
Signature Method: HMAC-SHA1
Add params to header: ✓ (checked)
Auto add parameters: ✓ (checked)
Realm: 11134099
```

### Important Settings
- **Signature Method**: Must be `HMAC-SHA1` (not HMAC-SHA256)
- **Add params to header**: Must be checked
- **Realm**: Must be your Account ID (`11134099`)

## Step 4: Send the Request

1. Click **"Send"**
2. Check the response

## Expected Results

### ✅ Success Response (200 OK)
If credentials are correct, you should get:
```json
{
  "links": [...],
  "count": 1,
  "hasMore": false,
  "items": [
    {
      "links": [...],
      "id": "123",
      "refName": "Customer Name",
      ...
    }
  ],
  "offset": 0,
  "totalResults": 1
}
```

### ❌ Authentication Error (401 Unauthorized)
If credentials are wrong, you'll get:
```json
{
  "type": "https://www.rfc-editor.org/rfc/rfc9110.html#section-15.5.2",
  "title": "Unauthorized",
  "status": 401,
  "o:errorDetails": [
    {
      "detail": "Invalid login attempt. For more details, see the Login Audit Trail in the NetSuite UI at Setup > Users/Roles > User Management > View Login Audit Trail.",
      "o:errorCode": "INVALID_LOGIN"
    }
  ]
}
```

### ❌ Permission Error (403 Forbidden)
If credentials are valid but lack permissions:
```json
{
  "type": "https://www.rfc-editor.org/rfc/rfc9110.html#section-15.5.1",
  "title": "Forbidden",
  "status": 403,
  "o:errorDetails": [
    {
      "detail": "You do not have permission to access this resource.",
      "o:errorCode": "INSUFFICIENT_PERMISSION"
    }
  ]
}
```

## Alternative Test Endpoints

If `/customer` fails due to permissions, try these simpler endpoints:

### Test 1: Subsidiary (Usually accessible)
```
GET https://11134099.suitetalk.api.netsuite.com/services/rest/record/v1/subsidiary?limit=1
```

### Test 2: Currency (Read-only, usually accessible)
```
GET https://11134099.suitetalk.api.netsuite.com/services/rest/record/v1/currency?limit=1
```

### Test 3: Account (Basic access)
```
GET https://11134099.suitetalk.api.netsuite.com/services/rest/record/v1/account?limit=1
```

## Troubleshooting Common Issues

### Issue 1: "Invalid signature" or "Invalid timestamp"
**Solution**: 
- Ensure **Signature Method** is `HMAC-SHA1`
- Check that **Add params to header** is enabled
- Verify the **Realm** is set to your Account ID

### Issue 2: "Invalid consumer key"
**Solution**:
- Double-check the Consumer Key from your Integration Application
- Ensure the Integration Application is **enabled** in NetSuite

### Issue 3: "Invalid token"
**Solution**:
- Verify the Access Token is not expired
- Check that the token has **REST Web Services** permission
- Ensure the token role has access to the Customer record type

### Issue 4: SSL/TLS errors
**Solution**:
- In Postman settings, turn off **"SSL certificate verification"**
- Go to Settings → General → SSL certificate verification (OFF)

## Step 5: Export Postman Request

Once you get it working, you can export the request:

1. Click the **"Code"** link below the Send button
2. Select **"cURL"** to see the equivalent curl command
3. This will show you exactly what headers and parameters Postman is sending

## Debugging with Postman Console

1. Open **Postman Console** (View → Show Postman Console)
2. Send your request
3. Check the console for detailed request/response information
4. Look for the exact Authorization header being sent

## Expected Authorization Header Format

The Authorization header should look like this:
```
OAuth realm="11134099", oauth_consumer_key="b31cb32cd5...", oauth_token="ea9b7200ef...", oauth_signature_method="HMAC-SHA1", oauth_timestamp="1641234567", oauth_nonce="abc123...", oauth_version="1.0", oauth_signature="xyz789..."
```

## Next Steps Based on Results

### If Postman Works ✅
- The credentials are correct
- The issue is in the PHP OAuth implementation
- Compare the Authorization header from Postman with the PHP code

### If Postman Fails ❌
- The credentials or NetSuite configuration need to be fixed
- Check the NetSuite setup following the API_CREDENTIALS.md guide
- Review the Login Audit Trail in NetSuite for specific error details

## NetSuite Login Audit Trail

To check the Login Audit Trail in NetSuite:

1. Log in to NetSuite
2. Go to **Setup** → **Users/Roles** → **User Management** → **View Login Audit Trail**
3. Look for recent failed login attempts
4. Check the details for specific error messages

This will give you the exact reason why the authentication is failing.