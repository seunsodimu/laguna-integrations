<?php

namespace Laguna\Integration\Utils;

/**
 * NetSuite Environment Manager
 * 
 * Provides utilities for managing NetSuite environment switching,
 * validation, and environment-aware operations.
 */
class NetSuiteEnvironmentManager
{
    private static $instance = null;
    private $credentials;
    private $config;
    
    private function __construct()
    {
        // Load .env file first if it exists
        if (file_exists(__DIR__ . '/../../.env')) {
            $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
            $dotenv->load();
        }
        
        $this->credentials = require __DIR__ . '/../../config/credentials.php';
        $this->config = require __DIR__ . '/../../config/config.php';
    }
    
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get the current NetSuite environment
     */
    public function getCurrentEnvironment(): string
    {
        return $this->credentials['netsuite_config']['current_environment'];
    }
    
    /**
     * Get all available environments
     */
    public function getAvailableEnvironments(): array
    {
        return $this->credentials['netsuite_config']['available_environments'];
    }
    
    /**
     * Check if currently running in production environment
     */
    public function isProduction(): bool
    {
        return $this->getCurrentEnvironment() === 'production';
    }
    
    /**
     * Check if currently running in sandbox environment
     */
    public function isSandbox(): bool
    {
        return $this->getCurrentEnvironment() === 'sandbox';
    }
    
    /**
     * Get current NetSuite credentials
     */
    public function getCurrentCredentials(): array
    {
        return $this->credentials['netsuite'];
    }
    
    /**
     * Get credentials for a specific environment
     */
    public function getCredentialsForEnvironment(string $environment): ?array
    {
        $allCredentials = $this->credentials['netsuite_config']['all_credentials'];
        return $allCredentials[$environment] ?? null;
    }
    
    /**
     * Get environment information for display
     */
    public function getEnvironmentInfo(): array
    {
        $current = $this->getCurrentEnvironment();
        $credentials = $this->getCurrentCredentials();
        
        return [
            'environment' => $current,
            'account_id' => $credentials['account_id'],
            'base_url' => $credentials['base_url'],
            'is_production' => $this->isProduction(),
            'is_sandbox' => $this->isSandbox(),
            'available_environments' => $this->getAvailableEnvironments()
        ];
    }
    
    /**
     * Validate environment configuration
     */
    public function validateEnvironment(): array
    {
        $issues = [];
        $current = $this->getCurrentEnvironment();
        $credentials = $this->getCurrentCredentials();
        
        // Check if environment is valid
        if (!in_array($current, $this->getAvailableEnvironments())) {
            $issues[] = "Invalid environment: {$current}";
        }
        
        // Check required credential fields
        $requiredFields = [
            'account_id', 'consumer_key', 'consumer_secret', 
            'token_id', 'token_secret', 'base_url'
        ];
        
        foreach ($requiredFields as $field) {
            if (empty($credentials[$field])) {
                $issues[] = "Missing or empty credential field: {$field}";
            }
        }
        
        // Validate account ID format
        if (!empty($credentials['account_id'])) {
            if ($this->isProduction() && strpos($credentials['account_id'], '_SB') !== false) {
                $issues[] = "Production environment should not use sandbox account ID";
            }
            if ($this->isSandbox() && strpos($credentials['account_id'], '_SB') === false) {
                $issues[] = "Sandbox environment should use sandbox account ID (ending with _SB)";
            }
        }
        
        // Validate base URL
        if (!empty($credentials['base_url'])) {
            if ($this->isProduction() && strpos($credentials['base_url'], '-sb') !== false) {
                $issues[] = "Production environment should not use sandbox base URL";
            }
            if ($this->isSandbox() && strpos($credentials['base_url'], '-sb') === false) {
                $issues[] = "Sandbox environment should use sandbox base URL (containing -sb)";
            }
        }
        
        return [
            'valid' => empty($issues),
            'issues' => $issues,
            'environment' => $current
        ];
    }
    
    /**
     * Get environment switching instructions
     */
    public function getSwitchingInstructions(): array
    {
        return [
            'current' => $this->getCurrentEnvironment(),
            'instructions' => [
                'windows_cmd' => 'set NETSUITE_ENVIRONMENT=production',
                'windows_powershell' => '$env:NETSUITE_ENVIRONMENT="production"',
                'linux_mac' => 'export NETSUITE_ENVIRONMENT=production',
                'env_file' => 'Add NETSUITE_ENVIRONMENT=production to .env file',
                'php_code' => '$_ENV[\'NETSUITE_ENVIRONMENT\'] = \'production\';'
            ],
            'available_environments' => $this->getAvailableEnvironments(),
            'restart_required' => 'Web server restart may be required for environment variable changes to take effect'
        ];
    }
    
    /**
     * Generate environment status for monitoring
     */
    public function getEnvironmentStatus(): array
    {
        $validation = $this->validateEnvironment();
        $info = $this->getEnvironmentInfo();
        
        return [
            'environment' => $info['environment'],
            'account_id' => $info['account_id'],
            'base_url' => $info['base_url'],
            'is_production' => $info['is_production'],
            'is_sandbox' => $info['is_sandbox'],
            'validation' => $validation,
            'timestamp' => date('Y-m-d H:i:s T')
        ];
    }
    
    /**
     * Check if environment variable is set
     */
    public function isEnvironmentVariableSet(): bool
    {
        return isset($_ENV['NETSUITE_ENVIRONMENT']);
    }
    
    /**
     * Get the source of environment configuration
     */
    public function getEnvironmentSource(): string
    {
        if (isset($_ENV['NETSUITE_ENVIRONMENT'])) {
            return 'Environment variable';
        }
        return 'Default (sandbox)';
    }
}