<?php
/**
 * Test Order Sync
 * 
 * This script tests the complete order sync process for order 1060221
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Controllers\WebhookController;
use Laguna\Integration\Utils\Logger;
use Laguna\Integration\Middleware\AuthMiddleware;

// Start output buffering
ob_start();

// Set JSON content type
header('Content-Type: application/json');

// Log file
$logFile = __DIR__ . '/../logs/test-sync-order.log';

function debugLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

try {
    debugLog("=== TEST ORDER SYNC START ===");
    
    // Check authentication
    $auth = new AuthMiddleware();
    if (!$auth->isAuthenticated()) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'error' => 'Authentication required. Please log in.',
            'redirect' => 'login.php'
        ]);
        exit;
    }
    
    // Load configuration
    $config = require __DIR__ . '/../config/config.php';
    date_default_timezone_set($config['app']['timezone']);
    
    // Initialize webhook controller
    $webhookController = new WebhookController();
    $logger = Logger::getInstance();
    
    debugLog("Webhook controller initialized");
    
    // Test order ID
    $orderId = '1060221';
    debugLog("Testing sync for order ID: $orderId");
    
    // Simulate webhook payload for order sync
    $webhookPayload = [
        'OrderID' => $orderId,
        'Type' => 'order_updated' // or whatever type triggers sync
    ];
    
    debugLog("Webhook payload: " . json_encode($webhookPayload));
    
    // Capture the sync process
    ob_start();
    
    try {
        // Call the webhook handler directly
        $_POST = $webhookPayload; // Simulate POST data
        
        // Process the webhook
        $result = $webhookController->handleWebhook();
        
        $output = ob_get_contents();
        ob_end_clean();
        
        debugLog("Sync completed successfully");
        debugLog("Webhook output: " . $output);
        
        $response = [
            'success' => true,
            'message' => 'Order sync completed successfully',
            'order_id' => $orderId,
            'webhook_output' => $output,
            'result' => $result
        ];
        
    } catch (Exception $syncException) {
        $output = ob_get_contents();
        ob_end_clean();
        
        debugLog("Sync failed with exception: " . $syncException->getMessage());
        debugLog("Webhook output before exception: " . $output);
        
        $response = [
            'success' => false,
            'message' => 'Order sync failed',
            'order_id' => $orderId,
            'error' => $syncException->getMessage(),
            'webhook_output' => $output,
            'trace' => $syncException->getTraceAsString()
        ];
    }
    
    debugLog("Response prepared: " . json_encode($response));
    
    ob_clean();
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    debugLog("Main exception: " . $e->getMessage());
    
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => true
    ]);
}

debugLog("=== TEST ORDER SYNC END ===");
?>