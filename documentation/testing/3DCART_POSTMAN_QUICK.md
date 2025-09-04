# 3DCart Postman Quick Test

## 🚀 Quick Setup

1. **Create New Request in Postman**
   - Method: `GET`
   - URL: `https://apirest.3dcart.com/3dCartWebAPI/v1/Orders?limit=1`

2. **Headers**
   ```
   Accept: application/json
   SecureURL: https://lagunaedi.3dcartstores.com
   PrivateKey: 3f1c50d3f246ff1837aad8575fafc84a
   Token: 649c1804bc94da1657c4625e66bb3d8c
   Authorization: Bearer 7sBEfBy2dm9ZQNE6p4WT4o9sqqCVkyY7ktmrNDiuqXs=
   ```

3. **Settings**
   - Disable SSL certificate verification (Settings → General)

4. **Click Send**

## 📊 Expected Results

### ✅ Success (200 OK)
```json
[
  {
    "OrderID": 12345,
    "OrderDate": "2024-01-15T10:30:00",
    "CustomerID": 67890,
    "BillingFirstName": "John",
    "OrderTotal": 99.99,
    ...
  }
]
```
**→ Credentials are working! Issue might be in PHP redirect handling.**

### ❌ Unauthorized (401)
```json
{
  "Message": "Authorization has been denied for this request."
}
```
**→ Invalid Private Key or Token.**

### ❌ Forbidden (403)
```json
{
  "Message": "Invalid API credentials"
}
```
**→ API credentials are wrong or disabled.**

### ⚠️ Redirects (302)
```
Location: https://lagunaedi.3dcartstores.com/...
```
**→ Try the secure URL instead.**

## 🔧 Alternative Test URLs

If main URL fails, try:
1. **Secure URL**: `https://lagunaedi.3dcartstores.com/3dCartWebAPI/v2/Orders?limit=1`
2. **Store Info**: `https://lagunaedi.com/3dCartWebAPI/v2/Store`
3. **Products**: `https://lagunaedi.com/3dCartWebAPI/v2/Products?limit=1`

## 🐛 Quick Troubleshooting

- **SSL errors**: Disable SSL verification in Postman
- **Too many redirects**: Use secure URL or follow redirects manually
- **401/403 errors**: Check credentials in 3DCart admin panel
- **404 errors**: Verify store URL is correct
- **Rate limits**: Wait 30-60 seconds between requests

## 📋 Compare with PHP

Current PHP issue: "Will not follow more than 10 redirects"
- This suggests the store URL redirects multiple times
- Try the secure URL in PHP code
- Or increase redirect limit in Guzzle client

## ⚡ Key Differences from NetSuite

- **No OAuth**: Simple header-based auth
- **No signatures**: Just Private Key + Token
- **Redirects common**: Store URLs often redirect
- **Rate limiting**: More restrictive than NetSuite