<?php

namespace Laguna\Integration\Utils;

use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;

/**
 * Logger Utility Class
 * 
 * Provides centralized logging functionality for the integration system.
 */
class Logger {
    private static $instance = null;
    private $logger;
    
    private function __construct() {
        $config = require __DIR__ . '/../../config/config.php';
        $logConfig = $config['logging'];
        
        $this->logger = new MonologLogger('3dcart-netsuite');
        
        if ($logConfig['enabled']) {
            // Create logs directory if it doesn't exist
            $logDir = dirname($logConfig['file']);
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            
            // Use rotating file handler to manage log file sizes
            $handler = new RotatingFileHandler(
                $logConfig['file'],
                $logConfig['max_files'] ?? 30,
                $this->getLogLevel($logConfig['level'])
            );
            
            // Custom formatter for better readability
            $formatter = new LineFormatter(
                "[%datetime%] %channel%.%level_name%: %message% %context%\n",
                'Y-m-d H:i:s'
            );
            $handler->setFormatter($formatter);
            
            $this->logger->pushHandler($handler);
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function getLogLevel($level) {
        switch (strtolower($level)) {
            case 'debug':
                return MonologLogger::DEBUG;
            case 'info':
                return MonologLogger::INFO;
            case 'warning':
                return MonologLogger::WARNING;
            case 'error':
                return MonologLogger::ERROR;
            default:
                return MonologLogger::INFO;
        }
    }
    
    public function debug($message, array $context = []) {
        $this->logger->debug($message, $context);
    }
    
    public function info($message, array $context = []) {
        $this->logger->info($message, $context);
    }
    
    public function warning($message, array $context = []) {
        $this->logger->warning($message, $context);
    }
    
    public function error($message, array $context = []) {
        $this->logger->error($message, $context);
    }
    
    public function critical($message, array $context = []) {
        $this->logger->critical($message, $context);
    }
    
    /**
     * Generic log method that accepts log level as parameter
     */
    public function log($level, $message, array $context = []) {
        switch (strtolower($level)) {
            case 'debug':
                $this->debug($message, $context);
                break;
            case 'info':
                $this->info($message, $context);
                break;
            case 'warning':
            case 'warn':
                $this->warning($message, $context);
                break;
            case 'error':
                $this->error($message, $context);
                break;
            case 'critical':
                $this->critical($message, $context);
                break;
            default:
                $this->info($message, $context);
                break;
        }
    }
    
    /**
     * Log order processing events
     */
    public function logOrderEvent($orderId, $event, $details = []) {
        $this->info("Order Event: {$event}", [
            'order_id' => $orderId,
            'event' => $event,
            'details' => $details,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Log API calls
     */
    public function logApiCall($service, $endpoint, $method, $responseCode, $duration = null) {
        $context = [
            'service' => $service,
            'endpoint' => $endpoint,
            'method' => $method,
            'response_code' => $responseCode,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        if ($duration !== null) {
            $context['duration_ms'] = $duration;
        }
        
        if ($responseCode >= 200 && $responseCode < 300) {
            $this->info("API Call Successful", $context);
        } else {
            $this->warning("API Call Failed", $context);
        }
    }
    
    /**
     * Log webhook events
     */
    public function logWebhook($source, $event, $data = []) {
        $this->info("Webhook Received", [
            'source' => $source,
            'event' => $event,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    }
}