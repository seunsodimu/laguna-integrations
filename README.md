# Laguna Integrations Platform

A flexible, multi-platform integration hub that connects various business systems to NetSuite for automated data synchronization and business process automation.

## Overview

The Laguna Integrations Platform has evolved from a single 3DCart-NetSuite integration to a comprehensive multi-integration system that supports:

- **3DCart → NetSuite**: Automated order processing and customer management
- **HubSpot → NetSuite**: Lead synchronization and campaign management
- **Extensible Architecture**: Easy to add new integrations

## Features

### 🛒 3DCart Integration
- Real-time webhook processing for new orders
- Automatic customer creation and matching
- Sales order generation in NetSuite
- Comprehensive order synchronization tools
- Manual upload capabilities for bulk processing

### 🎯 HubSpot Integration
- Contact property change webhooks
- Automatic lead creation in NetSuite
- Campaign management and synchronization
- Lead lifecycle stage filtering
- Sales team assignment

### ⚙️ System Management
- Unified status monitoring for all integrations
- Individual integration status pages
- Webhook configuration management
- Email notification system
- Environment-aware NetSuite connections

## Architecture

### Directory Structure
```
laguna-integrations/
├── config/
│   ├── config.php          # Main configuration
│   └── credentials.php     # API credentials
├── src/
│   ├── Controllers/        # Business logic controllers
│   ├── Services/          # API service classes
│   ├── Middleware/        # Authentication & security
│   └── Utils/            # Utility classes
├── public/               # Web-accessible files
│   ├── index.php         # Main dashboard
│   ├── status.php        # System status
│   ├── hubspot-status.php # HubSpot status
│   ├── hubspot-webhook.php # HubSpot webhook
│   └── webhook.php       # 3DCart webhook
└── logs/                # Application logs
```

### Key Components

#### Services
- **HubSpotService**: HubSpot API interactions and webhook processing
- **NetSuiteService**: NetSuite REST API operations (extended for campaigns/leads)
- **ThreeDCartService**: 3DCart API operations
- **UnifiedEmailService**: Multi-provider email notifications

#### Controllers
- **StatusController**: System health monitoring (updated for multi-integration)

#### Utilities
- **Logger**: Centralized logging system
- **NetSuiteEnvironmentManager**: Environment-aware NetSuite connections
- **UrlHelper**: URL generation and routing

## Configuration

### Integration Configuration
The system supports multiple integrations defined in `config/config.php`:

```php
'integrations' => [
    '3dcart_netsuite' => [
        'enabled' => true,
        'name' => '3DCart to NetSuite',
        'description' => 'Automated order processing from 3DCart to NetSuite',
        'webhook_endpoint' => 'webhook.php',
        'status_page' => 'status.php'
    ],
    'hubspot_netsuite' => [
        'enabled' => true,
        'name' => 'HubSpot to NetSuite',
        'description' => 'Lead synchronization from HubSpot to NetSuite',
        'webhook_endpoint' => 'hubspot-webhook.php',
        'status_page' => 'hubspot-status.php'
    ]
]
```

### HubSpot Configuration
Add HubSpot credentials to `config/credentials.php`:

```php
'hubspot' => [
    'access_token' => 'YOUR_HUBSPOT_ACCESS_TOKEN',
    'base_url' => 'https://api.hubapi.com',
    'webhook_secret' => 'your-hubspot-webhook-secret',
]
```

## HubSpot Integration Details

### Webhook Setup
1. Go to HubSpot account settings
2. Navigate to Integrations → Private Apps
3. Create or edit your private app
4. Go to the Webhooks tab
5. Add webhook URL: `https://yourdomain.com/hubspot-webhook.php`
6. Subscribe to "Contact property change" events
7. Set property filter to "hubspot_owner_id" (optional)

### Processing Flow
1. **Webhook Trigger**: Contact property change in HubSpot
2. **Contact Retrieval**: Fetch full contact details using HubSpot API
3. **Lifecycle Check**: Verify contact lifecycle stage is "lead"
4. **Campaign Processing**: Find or create campaign in NetSuite
5. **Lead Creation**: Create lead record in NetSuite with campaign association
6. **HubSpot Update**: Update HubSpot contact with NetSuite customer ID

### Campaign ID Formatting
- Source: `hs_analytics_source_data_1` property from HubSpot
- Formatting: Replace non-alphanumeric characters with underscores
- Truncate to 60 characters maximum
- Default to "None" if empty

## API Endpoints

### Status Endpoints
- `GET /status.php` - Overall system status (HTML)
- `GET /status.php?format=json` - System status (JSON)
- `GET /hubspot-status.php` - HubSpot integration status (HTML)
- `GET /hubspot-status.php?format=json` - HubSpot status (JSON)

### Webhook Endpoints
- `POST /webhook.php` - 3DCart order webhooks
- `POST /hubspot-webhook.php` - HubSpot contact property change webhooks

### Management Pages
- `/index.php` - Main dashboard
- `/webhook-settings.php` - Webhook configuration
- `/test-hubspot.php` - HubSpot integration testing

## NetSuite Extensions

The NetSuiteService has been extended to support HubSpot integration:

### New Methods
- `searchCampaign($campaignId)` - Search for existing campaigns
- `createCampaign($campaignData)` - Create new campaigns
- `createLead($leadData)` - Create lead records (returns NetSuite ID from Location header)

### HubSpot Service Extensions
- `updateContactNetSuiteId($contactId, $netsuiteId)` - Update HubSpot contact with NetSuite customer ID

### Campaign Management
- Automatic campaign creation if not found
- Campaign ID formatting and validation
- Campaign association with leads

## Monitoring & Logging

### Status Monitoring
- Real-time service health checks
- Integration-specific status pages
- Performance metrics and response times
- Error tracking and alerting

### Logging
- Centralized logging system
- Integration-specific log entries
- Error tracking and debugging
- Performance monitoring

## Security

### Authentication
- Session-based authentication system
- User management and access control
- Secure credential storage

### Webhook Security
- Signature verification for webhooks
- HTTPS-only communication
- Input validation and sanitization

## Development

### Adding New Integrations

1. **Configuration**: Add integration config to `config/config.php`
2. **Service Class**: Create service class in `src/Services/`
3. **Webhook Endpoint**: Create webhook handler in `public/`
4. **Status Page**: Create status page in `public/`
5. **Update StatusController**: Add service to monitoring

### Testing

- Use `/test-hubspot.php` for HubSpot integration testing
- Individual service connection testing
- Webhook payload simulation
- Status monitoring verification

## Deployment

### Requirements
- PHP 7.4+
- Composer for dependencies
- HTTPS-enabled web server
- NetSuite account with REST API access
- HubSpot account with private app

### Installation
1. Clone repository
2. Run `composer install`
3. Configure credentials in `config/credentials.php`
4. Set up webhooks in external systems
5. Configure web server with HTTPS

## Support

### Troubleshooting
- Check `/logs/` directory for error logs
- Use status pages for service health
- Test individual components with test pages
- Verify webhook configurations

### Common Issues
- **Authentication Failures**: Check API credentials
- **Webhook Failures**: Verify webhook URLs and signatures
- **NetSuite Errors**: Check environment and permissions
- **Campaign Issues**: Verify campaign ID formatting

## Version History

### v2.0.0
- Multi-integration architecture
- HubSpot integration added
- Enhanced status monitoring
- Improved webhook management
- Extended NetSuite service

### v1.0.0
- Initial 3DCart-NetSuite integration
- Basic webhook processing
- Order synchronization
- Customer management