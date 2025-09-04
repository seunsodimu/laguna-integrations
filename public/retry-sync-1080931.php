<?php
/**
 * Retry Sync Order 1080931
 * 
 * This script retries syncing order 1080931 after fixing the default_item_id
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Controllers\OrderController;
use Laguna\Integration\Middleware\AuthMiddleware;

// Start output buffering
ob_start();

// Set JSON content type
header('Content-Type: application/json');

try {
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
    
    $orderId = '1080931';
    
    // Initialize OrderController
    $orderController = new OrderController();
    
    // Check current configuration
    $config = require __DIR__ . '/../config/config.php';
    $defaultItemId = $config['netsuite']['default_item_id'];
    
    $response = [
        'success' => true,
        'order_id' => $orderId,
        'config_check' => [
            'default_item_id' => $defaultItemId,
            'is_fixed' => $defaultItemId != 1
        ],
        'steps' => []
    ];
    
    $response['steps'][] = "Retrying sync for 3DCart order {$orderId}...";
    $response['steps'][] = "Current default_item_id: {$defaultItemId}";
    
    if ($defaultItemId == 1) {
        $response['steps'][] = "❌ ERROR: default_item_id is still set to 1 - this will fail";
        throw new Exception("Configuration not updated - default_item_id is still 1");
    } else {
        $response['steps'][] = "✅ Configuration updated - using valid default_item_id";
    }
    
    // Attempt to sync the order
    $response['steps'][] = "Attempting to sync order...";
    
    $syncResult = $orderController->syncOrder($orderId);
    
    $response['sync_result'] = $syncResult;
    
    if ($syncResult['success']) {
        $response['steps'][] = "🎉 SUCCESS: Order synced successfully!";
        $response['steps'][] = "NetSuite Sales Order ID: " . ($syncResult['netsuite_id'] ?? 'N/A');
        $response['overall_status'] = 'SUCCESS';
    } else {
        $response['steps'][] = "❌ FAILED: Order sync still failed";
        $response['steps'][] = "Error: " . ($syncResult['error'] ?? 'Unknown error');
        $response['overall_status'] = 'FAILED';
        
        // Analyze the new error
        $error = $syncResult['error'] ?? '';
        if (strpos($error, 'Invalid Field Value') !== false) {
            $response['steps'][] = "🔍 Still getting invalid field value error - may need different default_item_id";
        }
    }
    
    ob_clean();
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'order_id' => $orderId ?? 'Unknown'
    ]);
}
?>