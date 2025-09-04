# Testing 3DCart Credentials in Postman

This guide will help you test your 3DCart REST API credentials using Postman to verify if the issue is with the credentials or the code implementation.

## Prerequisites

1. **Postman installed** (Download from https://www.postman.com/downloads/)
2. **Your 3DCart credentials** from `config/credentials.php`:
   - Store URL: `https://lagunaedi.com`
   - Private Key: `3f1c50d3f246ff1837aad8575fafc84a`
   - Token: `649c1804bc94da1657c4625e66bb3d8c`
   - Secure URL: `https://lagunaedi.3dcartstores.com`

## Step 1: Create a New Request in Postman

1. Open Postman
2. Click **"New"** → **"Request"**
3. Name it: `3DCart Orders Test`
4. Save it to a collection (create one if needed)

## Step 2: Configure the Request

### Basic Request Setup
- **Method**: `GET`
- **URL**: `https://apirest.3dcart.com/3dCartWebAPI/v1/Orders?limit=1`

### Headers
Add these headers:
```
Accept: application/json
SecureURL: https://lagunaedi.3dcartstores.com
PrivateKey: 3f1c50d3f246ff1837aad8575fafc84a
Token: 649c1804bc94da1657c4625e66bb3d8c
Authorization: Bearer 7sBEfBy2dm9ZQNE6p4WT4o9sqqCVkyY7ktmrNDiuqXs=
```

### Important Notes
- **No OAuth required** - 3DCart uses simple header-based authentication
- **PrivateKey and Token** are sent as HTTP headers
- **SSL verification** may need to be disabled

## Step 3: Configure SSL Settings (if needed)

1. Go to **Postman Settings** (gear icon)
2. Go to **General** tab
3. Turn **OFF** "SSL certificate verification"
4. This helps avoid SSL-related connection issues

## Step 4: Send the Request

1. Click **"Send"**
2. Check the response

## Expected Results

### ✅ Success Response (200 OK)
If credentials are correct, you should get:
```json
[
  {
    "OrderID": 12345,
    "OrderDate": "2024-01-15T10:30:00",
    "CustomerID": 67890,
    "BillingFirstName": "John",
    "BillingLastName": "Doe",
    "OrderTotal": 99.99,
    "OrderStatus": "New",
    ...
  }
]
```

### ❌ Authentication Error (401 Unauthorized)
If credentials are wrong, you'll get:
```json
{
  "Message": "Authorization has been denied for this request."
}
```

### ❌ Invalid API Key (403 Forbidden)
If the Private Key or Token is invalid:
```json
{
  "Message": "Invalid API credentials"
}
```

### ❌ Store Not Found (404 Not Found)
If the store URL is incorrect:
```html
<!DOCTYPE html>
<html>
<head><title>404 Not Found</title></head>
<body>
<h1>Not Found</h1>
<p>The requested URL was not found on this server.</p>
</body>
</html>
```

### ⚠️ Redirect Issues (302 Found)
If you get redirects, check the response headers for the correct URL:
```json
{
  "error": "Too many redirects"
}
```

## Alternative Test Endpoints

If `/Orders` fails, try these simpler endpoints:

### Test 1: Store Information (Basic access)
```
GET https://lagunaedi.com/3dCartWebAPI/v2/Store
```

### Test 2: Products (Usually accessible)
```
GET https://lagunaedi.com/3dCartWebAPI/v2/Products?limit=1
```

### Test 3: Categories (Read-only, usually accessible)
```
GET https://lagunaedi.com/3dCartWebAPI/v2/Categories?limit=1
```

### Test 4: Using Secure URL
If the main URL fails, try the secure URL:
```
GET https://lagunaedi.3dcartstores.com/3dCartWebAPI/v2/Orders?limit=1
```

## Troubleshooting Common Issues

### Issue 1: "SSL certificate problem"
**Solution**: 
- Disable SSL certificate verification in Postman settings
- Go to Settings → General → SSL certificate verification (OFF)

### Issue 2: "Too many redirects" or 302 responses
**Solution**:
- Try the secure URL instead: `https://lagunaedi.3dcartstores.com`
- Check if the store URL is correct
- Look at redirect headers to find the correct endpoint

### Issue 3: "Authorization has been denied"
**Solution**:
- Double-check the Private Key and Token values
- Ensure the API credentials are enabled in 3DCart admin
- Verify the credentials haven't expired

### Issue 4: "Invalid API credentials"
**Solution**:
- Log into 3DCart admin panel
- Go to Settings → General → API
- Verify the Private Key and Token are correct
- Check if API access is enabled for your account

### Issue 5: Connection timeout
**Solution**:
- Increase timeout in Postman (Settings → General → Request timeout)
- Try different endpoints to see if it's endpoint-specific
- Check if the store is temporarily down

## Step 5: Test Different URLs

3DCart stores can have multiple URLs. Test these variations:

1. **Main Store URL**: `https://lagunaedi.com/3dCartWebAPI/v2/Orders?limit=1`
2. **Secure URL**: `https://lagunaedi.3dcartstores.com/3dCartWebAPI/v2/Orders?limit=1`
3. **Without HTTPS**: `http://lagunaedi.com/3dCartWebAPI/v2/Orders?limit=1`

## Step 6: Export Working Request

Once you get it working:

1. Click the **"Code"** link below the Send button
2. Select **"cURL"** to see the equivalent curl command
3. This will show you exactly what headers Postman is sending

## Debugging with Postman Console

1. Open **Postman Console** (View → Show Postman Console)
2. Send your request
3. Check the console for:
   - Redirect information
   - Exact request headers sent
   - Response details
   - Any SSL/TLS errors

## Expected Request Headers

Your request should include these headers:
```
GET /3dCartWebAPI/v2/Orders?limit=1 HTTP/1.1
Host: lagunaedi.com
Content-Type: application/json
Accept: application/json
PrivateKey: 3f1c50d3f246ff1837aad8575fafc84a
Token: 649c1804bc94da1657c4625e66bb3d8c
User-Agent: 3DCart-Integration/1.0 (Postman)
```

## Rate Limiting Considerations

3DCart has rate limiting. If you get rate limit errors:
- Wait 30-60 seconds between requests
- Use `limit=1` parameter to minimize data transfer
- Check for `X-RateLimit-*` headers in responses

## Next Steps Based on Results

### If Postman Works ✅
- The credentials are correct
- The issue is in the PHP HTTP client configuration
- Compare the request headers from Postman with the PHP code
- Check for redirect handling differences

### If Postman Fails ❌
- The credentials or 3DCart configuration need to be fixed
- Check the 3DCart admin panel API settings
- Verify the store URL is correct
- Contact 3DCart support if credentials appear correct

## 3DCart Admin Panel Check

To verify your API credentials in 3DCart:

1. Log in to your 3DCart admin panel
2. Go to **Settings** → **General** → **API**
3. Check that:
   - **REST API** is enabled
   - **Private Key** matches your config
   - **Token** matches your config
   - **API Access** is enabled for your account
   - No IP restrictions are blocking your requests

## Common 3DCart API Gotchas

1. **Case Sensitivity**: Header names are case-sensitive
2. **URL Structure**: Must include `/3dCartWebAPI/v2/` in the path
3. **Redirects**: Store URLs often redirect, follow them
4. **Rate Limits**: Don't send requests too quickly
5. **SSL Issues**: Development environments often have SSL problems