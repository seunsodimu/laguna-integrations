# NetSuite Environment Management

This document explains the improved environment management system for switching between NetSuite sandbox and production environments.

## Overview

The system now supports dynamic environment switching using environment variables, eliminating the need to manually edit configuration files. This approach is safer, more maintainable, and follows industry best practices.

## Key Features

- **Dynamic Environment Switching**: Switch between environments using environment variables
- **Safety First**: Defaults to sandbox environment for safety
- **Validation**: Automatic validation of environment configuration
- **Environment Awareness**: All services know which environment they're using
- **Easy Monitoring**: Environment status is displayed throughout the application
- **Command Line Tools**: Utilities for environment management

## Environment Configuration

### Available Environments

- **sandbox**: NetSuite sandbox environment (default)
- **production**: NetSuite production environment

### Setting the Environment

#### Method 1: Environment Variables

**Windows Command Prompt:**
```cmd
set NETSUITE_ENVIRONMENT=production
```

**Windows PowerShell:**
```powershell
$env:NETSUITE_ENVIRONMENT="production"
```

**Linux/Mac Terminal:**
```bash
export NETSUITE_ENVIRONMENT=production
```

#### Method 2: .env File

Create a `.env` file in the project root:
```env
NETSUITE_ENVIRONMENT=production
```

#### Method 3: Web Server Configuration

**Apache (.htaccess):**
```apache
SetEnv NETSUITE_ENVIRONMENT production
```

**Nginx:**
```nginx
fastcgi_param NETSUITE_ENVIRONMENT production;
```

## File Structure Changes

### Updated Files

1. **config/credentials.php**
   - Now contains both sandbox and production credentials
   - Dynamically selects credentials based on environment variable
   - Includes environment validation and metadata

2. **src/Utils/NetSuiteEnvironmentManager.php** (NEW)
   - Centralized environment management
   - Environment validation and status reporting
   - Switching instructions and utilities

3. **src/Services/NetSuiteService.php**
   - Enhanced with environment awareness
   - Logs environment information on initialization
   - Provides environment status methods

4. **public/environment-status.php** (NEW)
   - Web interface for environment status
   - Connection testing
   - Switching instructions

5. **scripts/switch-environment.php** (NEW)
   - Command-line utility for environment management
   - Status checking, validation, and testing

## Usage Examples

### Checking Current Environment

**Web Interface:**
- Visit `/environment-status.php`
- Check the dashboard header
- View system status page

**Command Line:**
```bash
php scripts/switch-environment.php status
```

**Programmatically:**
```php
use Laguna\Integration\Utils\NetSuiteEnvironmentManager;

$envManager = NetSuiteEnvironmentManager::getInstance();
$currentEnv = $envManager->getCurrentEnvironment();
echo "Current environment: {$currentEnv}";
```

### Switching Environments

**Command Line:**
```bash
# Switch to production
php scripts/switch-environment.php switch production

# Switch to sandbox
php scripts/switch-environment.php switch sandbox
```

**Environment Variable:**
```bash
# Set environment variable and restart web server
export NETSUITE_ENVIRONMENT=production
sudo systemctl restart apache2
```

### Validating Configuration

**Command Line:**
```bash
php scripts/switch-environment.php validate
```

**Programmatically:**
```php
$envManager = NetSuiteEnvironmentManager::getInstance();
$validation = $envManager->validateEnvironment();

if ($validation['valid']) {
    echo "Configuration is valid";
} else {
    foreach ($validation['issues'] as $issue) {
        echo "Issue: {$issue}\n";
    }
}
```

### Testing Connection

**Command Line:**
```bash
php scripts/switch-environment.php test
```

**Web Interface:**
- Visit `/environment-status.php?test_connection=1`

## Environment Validation

The system automatically validates:

- Environment variable value is valid ('production' or 'sandbox')
- Required credential fields are present
- Account ID format matches environment (sandbox IDs contain '_SB')
- Base URL matches environment (sandbox URLs contain '-sb')
- Credentials are properly configured

## Security Considerations

1. **Default to Sandbox**: The system defaults to sandbox for safety
2. **Environment Validation**: Automatic validation prevents misconfigurations
3. **Credential Separation**: Production and sandbox credentials are clearly separated
4. **Access Control**: Environment status pages require authentication
5. **Logging**: Environment switches and validations are logged

## Monitoring and Alerts

### Dashboard Integration

- Environment status is displayed on the main dashboard
- Color-coded environment indicators (red for production, green for sandbox)
- Quick access to environment management tools

### Status Monitoring

- Environment information included in system status
- Validation results displayed in status pages
- Connection testing with environment context

### Logging

Environment-related events are logged:
- Environment initialization
- Environment switches
- Validation failures
- Connection attempts with environment context

## Troubleshooting

### Common Issues

1. **Environment Variable Not Set**
   - System defaults to sandbox
   - Check environment variable is set correctly
   - Restart web server after setting variables

2. **Invalid Environment Value**
   - System logs error and defaults to sandbox
   - Check spelling: 'production' or 'sandbox'

3. **Credential Mismatch**
   - Validation will catch account ID/URL mismatches
   - Check credentials match the intended environment

4. **Connection Failures**
   - Use connection testing tools
   - Verify credentials are correct for the environment
   - Check network connectivity

### Diagnostic Commands

```bash
# Check current status
php scripts/switch-environment.php status

# Validate configuration
php scripts/switch-environment.php validate

# Test connection
php scripts/switch-environment.php test

# List available environments
php scripts/switch-environment.php list
```

## Migration from Old System

### Before (Manual Commenting)

```php
// NetSuite API Credentials (production)
// 'netsuite' => [
//     'account_id' => '11134099',
//     // ... production credentials
// ],

// NetSuite API Credentials (sandbox)
'netsuite' => [
    'account_id' => '11134099_SB2',
    // ... sandbox credentials
],
```

### After (Environment-Based)

```php
// Automatically selects based on NETSUITE_ENVIRONMENT
'netsuite' => $netsuiteCredentials[$netsuiteEnvironment],
```

### Migration Steps

1. **Backup Current Configuration**
   ```bash
   cp config/credentials.php config/credentials.php.backup
   ```

2. **Update Configuration**
   - The new system is already implemented
   - Both environments are configured
   - Default is sandbox (safe)

3. **Set Environment Variable**
   ```bash
   # For production
   export NETSUITE_ENVIRONMENT=production
   
   # For sandbox (default)
   export NETSUITE_ENVIRONMENT=sandbox
   ```

4. **Validate Configuration**
   ```bash
   php scripts/switch-environment.php validate
   ```

5. **Test Connection**
   ```bash
   php scripts/switch-environment.php test
   ```

## Best Practices

1. **Always Validate**: Run validation after environment changes
2. **Test Connections**: Test connectivity after switching environments
3. **Monitor Logs**: Check logs for environment-related issues
4. **Use Staging**: Test environment switches in staging before production
5. **Document Changes**: Keep track of environment switches
6. **Backup Configurations**: Backup credentials before making changes

## API Reference

### NetSuiteEnvironmentManager Methods

```php
// Get current environment
$environment = $envManager->getCurrentEnvironment();

// Check if production
$isProduction = $envManager->isProduction();

// Check if sandbox
$isSandbox = $envManager->isSandbox();

// Get environment info
$info = $envManager->getEnvironmentInfo();

// Validate environment
$validation = $envManager->validateEnvironment();

// Get switching instructions
$instructions = $envManager->getSwitchingInstructions();
```

### NetSuiteService Environment Methods

```php
$netsuiteService = new NetSuiteService();

// Get environment info
$envInfo = $netsuiteService->getEnvironmentInfo();

// Check environment
$isProduction = $netsuiteService->isProduction();
$isSandbox = $netsuiteService->isSandbox();

// Validate environment
$validation = $netsuiteService->validateEnvironment();
```

## Support

For issues with environment management:

1. Check the environment status page: `/environment-status.php`
2. Run diagnostic commands: `php scripts/switch-environment.php status`
3. Check application logs: `logs/app.log`
4. Validate configuration: `php scripts/switch-environment.php validate`

## Changelog

### Version 1.1.0 - Environment Management System

**Added:**
- Dynamic environment switching via environment variables
- NetSuiteEnvironmentManager utility class
- Environment validation and status reporting
- Command-line environment management tools
- Web interface for environment status
- Environment awareness in all NetSuite operations
- Comprehensive logging of environment operations

**Changed:**
- credentials.php now supports dynamic environment selection
- NetSuiteService enhanced with environment awareness
- Dashboard shows current environment status
- Status pages include environment information

**Deprecated:**
- Manual commenting/uncommenting of credentials (still works but not recommended)

**Security:**
- Default to sandbox environment for safety
- Automatic validation of environment configuration
- Clear separation of production and sandbox credentials