<?php
/**
 * NetSuite Environment Switching Utility
 * 
 * Command-line utility to switch between NetSuite environments
 * and validate the configuration.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Utils\NetSuiteEnvironmentManager;
use Laguna\Integration\Services\NetSuiteService;

// Check if running from command line
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

function printUsage() {
    echo "NetSuite Environment Switching Utility\n";
    echo "=====================================\n\n";
    echo "Usage: php switch-environment.php [command] [environment]\n\n";
    echo "Commands:\n";
    echo "  status                    Show current environment status\n";
    echo "  switch [environment]      Switch to specified environment\n";
    echo "  validate                  Validate current environment configuration\n";
    echo "  test                      Test connection with current environment\n";
    echo "  list                      List available environments\n\n";
    echo "Environments:\n";
    echo "  production               Switch to production environment\n";
    echo "  sandbox                  Switch to sandbox environment\n\n";
    echo "Examples:\n";
    echo "  php switch-environment.php status\n";
    echo "  php switch-environment.php switch production\n";
    echo "  php switch-environment.php validate\n";
    echo "  php switch-environment.php test\n";
}

function setEnvironmentVariable($environment) {
    // For Windows
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        echo "Setting environment variable on Windows...\n";
        echo "Run this command in your terminal:\n";
        echo "set NETSUITE_ENVIRONMENT={$environment}\n\n";
        echo "Or in PowerShell:\n";
        echo "\$env:NETSUITE_ENVIRONMENT=\"{$environment}\"\n\n";
    } else {
        // For Unix-like systems
        echo "Setting environment variable on Unix/Linux/Mac...\n";
        echo "Run this command in your terminal:\n";
        echo "export NETSUITE_ENVIRONMENT={$environment}\n\n";
    }
    
    echo "Alternatively, add this to your .env file:\n";
    echo "NETSUITE_ENVIRONMENT={$environment}\n\n";
    echo "Note: Web server restart may be required for changes to take effect.\n";
}

// Parse command line arguments
$command = $argv[1] ?? 'status';
$environment = $argv[2] ?? null;

$envManager = NetSuiteEnvironmentManager::getInstance();

switch ($command) {
    case 'status':
        echo "NetSuite Environment Status\n";
        echo "===========================\n\n";
        
        $status = $envManager->getEnvironmentStatus();
        echo "Current Environment: " . $status['environment'] . "\n";
        echo "Account ID: " . $status['account_id'] . "\n";
        echo "Base URL: " . $status['base_url'] . "\n";
        echo "Is Production: " . ($status['is_production'] ? 'Yes' : 'No') . "\n";
        echo "Is Sandbox: " . ($status['is_sandbox'] ? 'Yes' : 'No') . "\n";
        echo "Environment Source: " . $envManager->getEnvironmentSource() . "\n";
        echo "Variable Set: " . ($envManager->isEnvironmentVariableSet() ? 'Yes' : 'No') . "\n";
        
        if (!$status['validation']['valid']) {
            echo "\nValidation Issues:\n";
            foreach ($status['validation']['issues'] as $issue) {
                echo "  - {$issue}\n";
            }
        } else {
            echo "\nConfiguration: Valid ✓\n";
        }
        break;
        
    case 'switch':
        if (!$environment) {
            echo "Error: Environment not specified.\n";
            echo "Usage: php switch-environment.php switch [production|sandbox]\n";
            exit(1);
        }
        
        $availableEnvironments = $envManager->getAvailableEnvironments();
        if (!in_array($environment, $availableEnvironments)) {
            echo "Error: Invalid environment '{$environment}'.\n";
            echo "Available environments: " . implode(', ', $availableEnvironments) . "\n";
            exit(1);
        }
        
        echo "Switching to {$environment} environment...\n\n";
        setEnvironmentVariable($environment);
        break;
        
    case 'validate':
        echo "Validating Environment Configuration\n";
        echo "===================================\n\n";
        
        $validation = $envManager->validateEnvironment();
        $current = $envManager->getCurrentEnvironment();
        
        echo "Current Environment: {$current}\n";
        
        if ($validation['valid']) {
            echo "Status: Valid ✓\n";
            echo "All configuration checks passed.\n";
        } else {
            echo "Status: Invalid ✗\n";
            echo "Issues found:\n";
            foreach ($validation['issues'] as $issue) {
                echo "  - {$issue}\n";
            }
        }
        break;
        
    case 'test':
        echo "Testing NetSuite Connection\n";
        echo "===========================\n\n";
        
        $envInfo = $envManager->getEnvironmentInfo();
        echo "Environment: " . $envInfo['environment'] . "\n";
        echo "Account ID: " . $envInfo['account_id'] . "\n";
        echo "Base URL: " . $envInfo['base_url'] . "\n\n";
        
        echo "Testing connection...\n";
        
        try {
            $netsuiteService = new NetSuiteService();
            $result = $netsuiteService->testConnection();
            
            if ($result['success']) {
                echo "Connection: Success ✓\n";
                echo "Status Code: " . $result['status_code'] . "\n";
                echo "Response Time: " . $result['response_time'] . "\n";
            } else {
                echo "Connection: Failed ✗\n";
                echo "Error: " . $result['error'] . "\n";
                if (isset($result['status_code'])) {
                    echo "Status Code: " . $result['status_code'] . "\n";
                }
            }
        } catch (Exception $e) {
            echo "Connection: Failed ✗\n";
            echo "Error: " . $e->getMessage() . "\n";
        }
        break;
        
    case 'list':
        echo "Available NetSuite Environments\n";
        echo "===============================\n\n";
        
        $current = $envManager->getCurrentEnvironment();
        $available = $envManager->getAvailableEnvironments();
        
        foreach ($available as $env) {
            $marker = ($env === $current) ? ' (current)' : '';
            echo "  - {$env}{$marker}\n";
        }
        break;
        
    case 'help':
    case '--help':
    case '-h':
        printUsage();
        break;
        
    default:
        echo "Error: Unknown command '{$command}'\n\n";
        printUsage();
        exit(1);
}

echo "\n";