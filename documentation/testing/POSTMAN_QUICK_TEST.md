# NetSuite Postman Quick Test

## 🚀 Quick Setup

1. **Create New Request in Postman**
   - Method: `GET`
   - URL: `https://11134099.suitetalk.api.netsuite.com/services/rest/record/v1/customer?limit=1`

2. **Authorization Tab → OAuth 1.0**
   ```
   Consumer Key: b31cb32cd5bf15fd61906f320bda79325438e0fd6a77c1d973a8d589810d9f07
   Consumer Secret: f7e1a2d2b48bb002616be230e5b730369eb85a4a3522915979a077b503e8dbfc
   Access Token: ea9b7200ef1d1ba451f7f44d00fa415205b977a30eb6b589c1c9fab7cfe101b8
   Token Secret: 7100e2d7d576e445b64e0bda03d372575ab701312af754faf1ad00c4919d9f28
   Signature Method: HMAC-SHA1
   Add params to header: ✓
   Auto add parameters: ✓
   Realm: 11134099
   ```

3. **Headers**
   ```
   Content-Type: application/json
   Accept: application/json
   ```

4. **Click Send**

## 📊 Expected Results

### ✅ Success (200 OK)
```json
{
  "links": [...],
  "count": 1,
  "hasMore": false,
  "items": [...]
}
```
**→ Credentials are working! Issue is in PHP code.**

### ❌ Unauthorized (401)
```json
{
  "status": 401,
  "o:errorDetails": [{
    "o:errorCode": "INVALID_LOGIN"
  }]
}
```
**→ Credentials/NetSuite configuration issue.**

### ❌ Forbidden (403)
```json
{
  "status": 403,
  "o:errorDetails": [{
    "o:errorCode": "INSUFFICIENT_PERMISSION"
  }]
}
```
**→ Token lacks Customer record permissions.**

## 🔧 Alternative Test Endpoints

If customer fails, try:
- `/subsidiary?limit=1` (usually accessible)
- `/currency?limit=1` (read-only)
- `/account?limit=1` (basic access)

## 🐛 Troubleshooting

- **Invalid signature**: Ensure Signature Method = `HMAC-SHA1`
- **SSL errors**: Disable SSL verification in Postman settings
- **Invalid consumer**: Check Integration Application is enabled
- **Invalid token**: Check token expiration and permissions

## 📋 Compare with PHP

Run this to see what our PHP code generates:
```bash
php debug_oauth_signature.php
```

Compare the Authorization header with Postman's (in Console view).