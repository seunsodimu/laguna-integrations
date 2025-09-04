---
description: Repository Information Overview
alwaysApply: true
---

# 3DCart to NetSuite Integration System

## Summary
A comprehensive PHP-based integration system that automatically processes orders from 3DCart and creates corresponding sales orders in NetSuite, with customer management and email notifications. The system supports webhook integration, manual order uploads via CSV/Excel, and provides real-time monitoring of all integrations.

## Structure
- **config/**: Configuration files including main settings, database config, and API credentials
- **src/**: Core application code organized in MVC pattern (Controllers, Models, Services, Utils)
- **public/**: Web-accessible entry points and endpoints (index.php, webhook.php, etc.)
- **logs/**: Application logs directory
- **uploads/**: Directory for manual order file uploads
- **docs/**: Documentation files (setup, deployment, API credentials)
- **tests/**: PHPUnit test files
- **vendor/**: Composer dependencies

## Language & Runtime
**Language**: PHP
**Version**: 7.4 or higher
**Build System**: Composer
**Package Manager**: Composer

## Dependencies
**Main Dependencies**:
- guzzlehttp/guzzle (^7.0): HTTP client for API requests
- monolog/monolog (^2.0): Logging framework
- phpoffice/phpspreadsheet (^1.24): Excel/CSV file processing
- sendgrid/sendgrid (^8.0): Email service integration
- vlucas/phpdotenv (^5.0): Environment variable management

**Development Dependencies**:
- phpunit/phpunit (^9.0): Testing framework

## Build & Installation
```bash
# Clone repository
git clone <repository-url>
cd laguna_3dcart_netsuite

# Install dependencies
composer install

# Configure credentials
cp config/credentials.example.php config/credentials.php
# Edit config/credentials.php with API credentials

# Setup database (if using database logging)
mysql -u username -p database_name < database/schema.sql

# Ensure write permissions
chmod -R 755 logs/ uploads/
```

## Main Entry Points
- **public/index.php**: Main dashboard
- **public/webhook.php**: 3DCart webhook endpoint
- **public/upload.php**: Manual order upload interface
- **public/status.php**: Connection status dashboard

## Testing
**Framework**: PHPUnit 9.0+
**Test Location**: tests/ directory
**Configuration**: Autoloaded via composer.json
**Run Command**:
```bash
composer test
# or
vendor/bin/phpunit
```

## Requirements
- PHP 7.4 or higher
- cURL extension
- JSON extension
- OpenSSL extension
- mbstring extension
- MySQL (optional, for advanced logging)