<?php
/**
 * Debug POST Request
 * 
 * This script logs exactly what happens during a POST request
 */

// Load autoloader and use statements first
require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Services\ThreeDCartService;
use Laguna\Integration\Services\NetSuiteService;
use Laguna\Integration\Utils\Logger;
use Laguna\Integration\Middleware\AuthMiddleware;

// Start output buffering
ob_start();

// Set JSON content type
header('Content-Type: application/json');

// Log file
$logFile = __DIR__ . '/../logs/debug-post.log';

function debugLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

try {
    debugLog("=== DEBUG POST REQUEST START ===");
    debugLog("Request Method: " . $_SERVER['REQUEST_METHOD']);
    debugLog("POST Data: " . json_encode($_POST));
    debugLog("Memory Usage: " . memory_get_usage(true) . " bytes");
    debugLog("Memory Limit: " . ini_get('memory_limit'));
    debugLog("Time Limit: " . ini_get('max_execution_time'));
    
    debugLog("Autoloader and use statements loaded");
    
    // Load configuration
    debugLog("Loading config...");
    $config = require __DIR__ . '/../config/config.php';
    date_default_timezone_set($config['app']['timezone']);
    debugLog("Config loaded");
    
    // Check authentication
    debugLog("Checking authentication...");
    $auth = new AuthMiddleware();
    if (!$auth->isAuthenticated()) {
        debugLog("Not authenticated - returning auth error");
        ob_clean();
        echo json_encode([
            'success' => false,
            'error' => 'Authentication required. Please log in.',
            'redirect' => 'login.php'
        ]);
        exit;
    }
    debugLog("Authentication OK");
    
    // Initialize services
    debugLog("Initializing services...");
    $threeDCartService = new ThreeDCartService();
    $netSuiteService = new NetSuiteService();
    $logger = Logger::getInstance();
    debugLog("Services initialized");
    
    // Check if this is a fetch_orders request
    if (($_POST['action'] ?? '') === 'fetch_orders') {
        debugLog("Processing fetch_orders request");
        
        $startDate = $_POST['start_date'] ?? '';
        $endDate = $_POST['end_date'] ?? '';
        $status = $_POST['status'] ?? '';
        
        debugLog("Date range: $startDate to $endDate, Status: $status");
        
        // Test 3DCart connection
        debugLog("Testing 3DCart connection...");
        $orders = $threeDCartService->getOrdersByDateRange($startDate, $endDate, $status);
        debugLog("3DCart returned " . count($orders) . " orders");
        
        // Limit for testing
        if (count($orders) > 5) {
            $orders = array_slice($orders, 0, 5);
            debugLog("Limited to 5 orders for testing");
        }
        
        // Test NetSuite connection
        debugLog("Testing NetSuite connection...");
        $orderIds = array_map(function($order) { return $order['OrderID']; }, $orders);
        $syncStatusMap = $netSuiteService->checkOrdersSyncStatus($orderIds);
        debugLog("NetSuite sync status checked for " . count($syncStatusMap) . " orders");
        
        // Build response
        debugLog("Building response...");
        $response = [
            'success' => true,
            'orders' => [],
            'total_count' => count($orders),
            'date_range' => "$startDate to $endDate",
            'debug' => true
        ];
        
        foreach ($orders as $order) {
            $orderId = $order['OrderID'];
            $syncStatus = $syncStatusMap[$orderId] ?? ['synced' => false];
            
            $response['orders'][] = [
                'order_id' => $orderId,
                'order_date' => $order['OrderDate'],
                'customer_name' => trim(($order['BillingFirstName'] ?? '') . ' ' . ($order['BillingLastName'] ?? '')),
                'order_total' => $order['OrderAmount'] ?? 0,
                'in_netsuite' => $syncStatus['synced']
            ];
        }
        
        debugLog("Response built with " . count($response['orders']) . " orders");
        
        // Test JSON encoding
        $json = json_encode($response);
        if ($json === false) {
            debugLog("JSON encoding failed: " . json_last_error_msg());
            throw new Exception("JSON encoding failed: " . json_last_error_msg());
        }
        
        debugLog("JSON encoding successful (" . strlen($json) . " bytes)");
        debugLog("Sending response...");
        
        ob_clean();
        echo $json;
        debugLog("Response sent successfully");
        exit;
    }
    
    // Default response
    debugLog("No specific action - sending default response");
    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Debug script working',
        'post_data' => $_POST
    ]);
    
} catch (Exception $e) {
    debugLog("Exception caught: " . $e->getMessage());
    debugLog("File: " . $e->getFile() . ", Line: " . $e->getLine());
    
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => true
    ]);
} catch (Throwable $e) {
    debugLog("Fatal error caught: " . $e->getMessage());
    debugLog("File: " . $e->getFile() . ", Line: " . $e->getLine());
    
    if (ob_get_level()) {
        ob_clean();
    }
    
    echo json_encode([
        'success' => false,
        'error' => 'Fatal error: ' . $e->getMessage(),
        'debug' => true
    ]);
}

debugLog("=== DEBUG POST REQUEST END ===");
?>