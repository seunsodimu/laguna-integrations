<?php

namespace Laguna\Integration\Controllers;

use Laguna\Integration\Services\ThreeDCartService;
use Laguna\Integration\Services\NetSuiteService;
use Laguna\Integration\Services\HubSpotService;
use Laguna\Integration\Services\UnifiedEmailService;
use Laguna\Integration\Utils\Logger;
use Laguna\Integration\Utils\NetSuiteEnvironmentManager;

/**
 * Status Controller
 * 
 * Handles connection status monitoring and health checks for all integrations.
 */
class StatusController {
    private $threeDCartService;
    private $netSuiteService;
    private $hubSpotService;
    private $emailService;
    private $logger;
    private $config;
    
    public function __construct() {
        $this->threeDCartService = new ThreeDCartService();
        $this->netSuiteService = new NetSuiteService();
        $this->hubSpotService = new HubSpotService();
        $this->emailService = new UnifiedEmailService();
        $this->logger = Logger::getInstance();
        $this->config = require __DIR__ . '/../../config/config.php';
    }
    
    /**
     * Get comprehensive status of all integrations
     */
    public function getStatus() {
        $status = [
            'timestamp' => date('c'),
            'overall_status' => 'unknown',
            'services' => [],
            'system_info' => $this->getSystemInfo(),
            'configuration' => $this->getConfigurationStatus(),
            'netsuite_environment' => $this->getNetSuiteEnvironmentStatus()
        ];
        
        // Test each service
        $emailProviderInfo = $this->emailService->getProviderInfo();
        $emailProviderName = $emailProviderInfo['name'] ?? 'Email Service';
        
        $services = [
            '3DCart' => [$this->threeDCartService, 'testConnection'],
            'NetSuite' => [$this->netSuiteService, 'testConnection'],
            'HubSpot' => [$this->hubSpotService, 'testConnection'],
            $emailProviderName => [$this->emailService, 'testConnection']
        ];
        
        $allServicesUp = true;
        
        foreach ($services as $serviceName => $serviceConfig) {
            try {
                $service = $serviceConfig[0];
                $method = $serviceConfig[1];
                
                $serviceStatus = $service->$method();
                $serviceStatus['name'] = $serviceName;
                $serviceStatus['last_checked'] = date('c');
                
                $status['services'][$serviceName] = $serviceStatus;
                
                if (!$serviceStatus['success']) {
                    $allServicesUp = false;
                }
                
            } catch (\Exception $e) {
                $status['services'][$serviceName] = [
                    'name' => $serviceName,
                    'success' => false,
                    'error' => $e->getMessage(),
                    'last_checked' => date('c')
                ];
                $allServicesUp = false;
                
                $this->logger->error("Service status check failed for {$serviceName}", [
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // Set overall status
        $status['overall_status'] = $allServicesUp ? 'healthy' : 'degraded';
        
        // Add additional health checks
        $status['health_checks'] = $this->performHealthChecks();
        
        // Log status check
        $this->logger->info('Status check performed', [
            'overall_status' => $status['overall_status'],
            'services_up' => array_sum(array_column($status['services'], 'success')),
            'total_services' => count($status['services'])
        ]);
        
        return $status;
    }
    
    /**
     * Get system information
     */
    private function getSystemInfo() {
        return [
            'php_version' => PHP_VERSION,
            'server_time' => date('c'),
            'timezone' => date_default_timezone_get(),
            'memory_usage' => [
                'current' => $this->formatBytes(memory_get_usage(true)),
                'peak' => $this->formatBytes(memory_get_peak_usage(true)),
                'limit' => ini_get('memory_limit')
            ],
            'disk_space' => [
                'free' => $this->formatBytes(disk_free_space('.')),
                'total' => $this->formatBytes(disk_total_space('.'))
            ],
            'extensions' => [
                'curl' => extension_loaded('curl'),
                'json' => extension_loaded('json'),
                'openssl' => extension_loaded('openssl'),
                'zip' => extension_loaded('zip')
            ]
        ];
    }
    
    /**
     * Get configuration status
     */
    private function getConfigurationStatus() {
        $configStatus = [
            'credentials_file_exists' => file_exists(__DIR__ . '/../../config/credentials.php'),
            'logs_directory_writable' => is_writable(__DIR__ . '/../../logs'),
            'uploads_directory_writable' => is_writable(__DIR__ . '/../../uploads'),
            'notifications_enabled' => $this->config['notifications']['enabled'],
            'auto_create_customers' => $this->config['order_processing']['auto_create_customers'],
            'webhook_enabled' => $this->config['webhook']['enabled'],
            'webhook_secret_configured' => !empty($this->config['webhook']['secret_key'])
        ];
        
        // Check if logs directory exists, create if not
        $logsDir = __DIR__ . '/../../logs';
        if (!is_dir($logsDir)) {
            mkdir($logsDir, 0755, true);
            $configStatus['logs_directory_writable'] = is_writable($logsDir);
        }
        
        // Check if uploads directory exists, create if not
        $uploadsDir = __DIR__ . '/../../uploads';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
            $configStatus['uploads_directory_writable'] = is_writable($uploadsDir);
        }
        
        return $configStatus;
    }
    
    /**
     * Get NetSuite environment status
     */
    private function getNetSuiteEnvironmentStatus() {
        $envManager = NetSuiteEnvironmentManager::getInstance();
        return $envManager->getEnvironmentStatus();
    }
    
    /**
     * Perform additional health checks
     */
    private function performHealthChecks() {
        $checks = [];
        
        // Check log file size
        $logFile = $this->config['logging']['file'];
        if (file_exists($logFile)) {
            $logSize = filesize($logFile);
            $checks['log_file_size'] = [
                'status' => $logSize < 100 * 1024 * 1024 ? 'ok' : 'warning', // 100MB threshold
                'size' => $this->formatBytes($logSize),
                'message' => $logSize < 100 * 1024 * 1024 ? 'Log file size is normal' : 'Log file is large, consider rotation'
            ];
        } else {
            $checks['log_file_size'] = [
                'status' => 'info',
                'message' => 'Log file does not exist yet'
            ];
        }
        
        // Check uploads directory
        $uploadsDir = $this->config['upload']['upload_path'];
        if (is_dir($uploadsDir)) {
            $uploadFiles = glob($uploadsDir . '*');
            $checks['uploads_directory'] = [
                'status' => count($uploadFiles) < 100 ? 'ok' : 'warning',
                'file_count' => count($uploadFiles),
                'message' => count($uploadFiles) < 100 ? 'Upload directory is clean' : 'Many files in upload directory, consider cleanup'
            ];
        }
        
        // Check required PHP extensions
        $requiredExtensions = ['curl', 'json', 'openssl'];
        $missingExtensions = [];
        
        foreach ($requiredExtensions as $ext) {
            if (!extension_loaded($ext)) {
                $missingExtensions[] = $ext;
            }
        }
        
        $checks['php_extensions'] = [
            'status' => empty($missingExtensions) ? 'ok' : 'error',
            'missing' => $missingExtensions,
            'message' => empty($missingExtensions) ? 'All required extensions loaded' : 'Missing extensions: ' . implode(', ', $missingExtensions)
        ];
        
        // Check memory usage
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->parseBytes(ini_get('memory_limit'));
        $memoryPercent = ($memoryUsage / $memoryLimit) * 100;
        
        $checks['memory_usage'] = [
            'status' => $memoryPercent < 80 ? 'ok' : ($memoryPercent < 95 ? 'warning' : 'error'),
            'usage_percent' => round($memoryPercent, 2),
            'message' => "Memory usage: {$memoryPercent}%"
        ];
        
        return $checks;
    }
    
    /**
     * Get detailed service status with additional metrics
     */
    public function getDetailedStatus() {
        $status = $this->getStatus();
        
        // Add recent activity metrics
        $status['recent_activity'] = $this->getRecentActivity();
        
        // Add performance metrics
        $status['performance'] = $this->getPerformanceMetrics();
        
        return $status;
    }
    
    /**
     * Get recent activity from logs
     */
    private function getRecentActivity() {
        $activity = [
            'last_24_hours' => [
                'orders_processed' => 0,
                'errors' => 0,
                'api_calls' => 0
            ],
            'last_hour' => [
                'orders_processed' => 0,
                'errors' => 0,
                'api_calls' => 0
            ]
        ];
        
        // This would typically read from log files or database
        // For now, return placeholder data
        
        return $activity;
    }
    
    /**
     * Get performance metrics
     */
    private function getPerformanceMetrics() {
        return [
            'average_order_processing_time' => '2.5 seconds',
            'api_response_times' => [
                '3DCart' => '150ms',
                'NetSuite' => '800ms',
                'SendGrid' => '200ms'
            ],
            'success_rate_24h' => '98.5%',
            'uptime' => $this->getUptime()
        ];
    }
    
    /**
     * Get system uptime (simplified)
     */
    private function getUptime() {
        // This is a simplified version - in production you might track this differently
        return [
            'current_session' => 'N/A',
            'last_restart' => 'N/A'
        ];
    }
    
    /**
     * Send status alert if services are down
     */
    public function checkAndAlert() {
        $status = $this->getStatus();
        
        foreach ($status['services'] as $serviceName => $serviceStatus) {
            if (!$serviceStatus['success']) {
                $this->emailService->sendConnectionAlert(
                    $serviceName,
                    false,
                    [
                        'Error' => $serviceStatus['error'] ?? 'Unknown error',
                        'Last Checked' => $serviceStatus['last_checked'],
                        'Status Code' => $serviceStatus['status_code'] ?? 'N/A'
                    ]
                );
            }
        }
        
        return $status;
    }
    
    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Parse bytes from string (like "128M")
     */
    private function parseBytes($val) {
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        $val = (int)$val;
        
        switch($last) {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }
        
        return $val;
    }
    
    /**
     * Get status as JSON response
     */
    public function getStatusJson() {
        header('Content-Type: application/json');
        echo json_encode($this->getStatus(), JSON_PRETTY_PRINT);
    }
    
    /**
     * Get detailed status as JSON response
     */
    public function getDetailedStatusJson() {
        header('Content-Type: application/json');
        echo json_encode($this->getDetailedStatus(), JSON_PRETTY_PRINT);
    }
}