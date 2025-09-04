# Brevo Email Service Setup Guide

## Overview

This guide explains how to set up and use Brevo (formerly SendinBlue) as your email service provider for the 3DCart to NetSuite integration system.

## What is Brevo?

Brevo (formerly SendinBlue) is a comprehensive email marketing and transactional email service that offers:
- Transactional email delivery
- Email marketing campaigns
- SMS marketing
- Marketing automation
- CRM features

## Why Choose Brevo?

- **Generous Free Tier**: 300 emails per day on the free plan
- **Competitive Pricing**: More affordable than many alternatives
- **High Deliverability**: Excellent email delivery rates
- **Easy Integration**: Simple REST API
- **EU-Based**: GDPR compliant by design
- **Reliable Service**: 99.9% uptime SLA

## Getting Started with Brevo

### 1. Create a Brevo Account

1. Visit [Brevo.com](https://www.brevo.com)
2. Sign up for a free account
3. Verify your email address
4. Complete the account setup process

### 2. Get Your API Key

1. Log into your Brevo dashboard
2. Go to **Settings** → **SMTP & API**
3. Click on **API Keys** tab
4. Click **Generate a new API key**
5. Give it a name (e.g., "3DCart Integration")
6. Copy the generated API key (starts with `xkeysib-`)

### 3. Verify Your Sender Email

1. In Brevo dashboard, go to **Settings** → **Senders & IP**
2. Click **Add a sender**
3. Enter your email address (e.g., `noreply@yourdomain.com`)
4. Follow the verification process
5. Wait for verification approval

## Configuration

### Method 1: Using the Web Interface

1. Access the email configuration page: `http://your-domain/public/email-provider-config.php`
2. Switch to Brevo provider
3. Enter your Brevo credentials:
   - **API Key**: Your Brevo API key (starts with `xkeysib-`)
   - **From Email**: Your verified sender email
   - **From Name**: Your company/system name
4. Save the configuration

### Method 2: Manual Configuration

Edit `config/credentials.php`:

```php
'email' => [
    'provider' => 'brevo', // Switch to Brevo
    
    'brevo' => [
        'api_key' => 'xkeysib-your-api-key-here',
        'from_email' => 'noreply@yourdomain.com',
        'from_name' => '3DCart Integration System',
    ],
    
    // Keep SendGrid config for fallback
    'sendgrid' => [
        'api_key' => 'your-sendgrid-api-key',
        'from_email' => 'noreply@yourdomain.com',
        'from_name' => '3DCart Integration System',
    ],
],
```

## Testing Your Setup

### Web Interface Testing

1. Go to `http://your-domain/public/test-email.php`
2. The page will show "Brevo" as the current provider
3. Enter your email address
4. Select a test type
5. Click "Send Test Email"

### Command Line Testing

```bash
# Test basic email
php test-email-cli.php your-email@example.com basic

# Test order notification
php test-email-cli.php your-email@example.com order

# Test error alert
php test-email-cli.php your-email@example.com error
```

### Expected Output (Success)

```
=== Brevo Email Test (CLI) ===

Testing Brevo connection...
✅ Brevo connection successful
Sending test email...
To: your-email@example.com
Type: basic
✅ Test email sent successfully!
Status Code: 201
Message ID: <message-id@smtp-relay.brevo.com>
```

## API Limits and Quotas

### Free Plan Limits
- **300 emails per day**
- **Unlimited contacts**
- **Email support**

### Paid Plan Benefits
- **Higher sending limits** (20,000+ emails/month)
- **No daily sending limit**
- **Phone support**
- **Advanced features**

### Rate Limits
- **API Calls**: 3,000 calls per hour
- **Email Sending**: Based on your plan's quota

## Troubleshooting

### Common Issues

#### 1. API Key Invalid
**Error**: `401 Unauthorized`
**Solution**: 
- Verify your API key is correct
- Ensure it starts with `xkeysib-`
- Check if the key has been revoked

#### 2. Sender Not Verified
**Error**: `400 Bad Request - Invalid sender`
**Solution**:
- Verify your sender email in Brevo dashboard
- Wait for verification approval
- Use only verified sender addresses

#### 3. Quota Exceeded
**Error**: `402 Payment Required - not_enough_credits`
**Solution**:
- Check your Brevo dashboard for usage
- Upgrade your plan if needed
- Wait for quota reset (free plans reset daily)

#### 4. Invalid Email Format
**Error**: `400 Bad Request - Invalid email address`
**Solution**:
- Ensure recipient emails are valid
- Check for typos in email addresses

### Debug Steps

1. **Check Connection**:
   ```bash
   php test-email-cli.php test@example.com basic
   ```

2. **Check Logs**:
   ```bash
   tail -f logs/app-$(date +%Y-%m-%d).log
   ```

3. **Verify Configuration**:
   - Visit `http://your-domain/public/email-provider-config.php`
   - Check provider status

4. **Test API Directly**:
   ```bash
   curl -X GET "https://api.brevo.com/v3/account" \
        -H "api-key: your-api-key"
   ```

## Switching Between Providers

### Quick Switch via Web Interface

1. Go to `http://your-domain/public/email-provider-config.php`
2. Select desired provider from dropdown
3. Click "Switch Provider"

### Manual Switch

Edit `config/credentials.php` and change:
```php
'provider' => 'brevo', // or 'sendgrid'
```

### Testing Multiple Providers

The system can test all configured providers:
- Web: Visit email configuration page
- CLI: Check status dashboard

## Best Practices

### 1. Email Content
- Use clear, descriptive subject lines
- Include both HTML and text versions
- Keep content concise and relevant

### 2. Sender Reputation
- Use consistent sender information
- Monitor bounce rates
- Handle unsubscribes properly

### 3. Monitoring
- Check email delivery rates regularly
- Monitor API usage and quotas
- Set up alerts for failures

### 4. Security
- Keep API keys secure
- Use environment variables for sensitive data
- Rotate API keys periodically

## Integration Features

### Supported Email Types

1. **Order Notifications**: Sent when orders are processed
2. **Error Alerts**: System error notifications
3. **Connection Alerts**: Service status changes
4. **Test Emails**: Various test email formats

### Automatic Failover

The system supports multiple providers configured simultaneously:
- Primary provider handles all emails
- Can manually switch providers
- Each provider maintains separate credentials

### Logging and Monitoring

- All email activities are logged
- Provider-specific error handling
- Quota monitoring and alerts
- Connection status tracking

## Support

### Brevo Support
- **Documentation**: [developers.brevo.com](https://developers.brevo.com)
- **Support**: Available through Brevo dashboard
- **Community**: Brevo community forums

### Integration Support
- Check system logs: `logs/app-[date].log`
- Use test tools: `test-email.php` and `test-email-cli.php`
- Review configuration: `email-provider-config.php`

## Migration from SendGrid

### Step-by-Step Migration

1. **Setup Brevo Account** (as described above)
2. **Configure Brevo** in the system
3. **Test Brevo** thoroughly
4. **Switch Provider** when ready
5. **Monitor** for any issues

### Rollback Plan

If issues occur with Brevo:
1. Switch back to SendGrid via configuration page
2. Check logs for specific errors
3. Fix Brevo configuration
4. Test again before switching

### Data Considerations

- Email templates work with both providers
- No data migration needed
- Logs will show which provider sent each email

---

**Need Help?** Check the troubleshooting section above or review the system logs for detailed error information.