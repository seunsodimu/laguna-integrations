<?php
/**
 * Debug Customer Search
 * 
 * This script tests customer search functionality
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Services\NetSuiteService;
use Laguna\Integration\Services\ThreeDCartService;
use Laguna\Integration\Utils\Logger;
use Laguna\Integration\Middleware\AuthMiddleware;

// Start output buffering
ob_start();

// Set JSON content type
header('Content-Type: application/json');

// Log file
$logFile = __DIR__ . '/../logs/debug-customer-search.log';

function debugLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

try {
    debugLog("=== DEBUG CUSTOMER SEARCH START ===");
    
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
    
    // Initialize services
    $threeDCartService = new ThreeDCartService();
    $netSuiteService = new NetSuiteService();
    $logger = Logger::getInstance();
    
    debugLog("Services initialized");
    
    // Get order ID from POST or use default
    $orderId = $_POST['order_id'] ?? '1060221';
    debugLog("Testing with order ID: $orderId");
    
    // Get order from 3DCart
    debugLog("Fetching order from 3DCart...");
    $orderData = $threeDCartService->getOrder($orderId);
    
    if (!$orderData) {
        throw new Exception("Order not found: $orderId");
    }
    
    debugLog("Order fetched successfully");
    
    // Extract customer email
    $customerEmail = $orderData['BillingEmail'] ?? '';
    debugLog("Customer email: $customerEmail");
    
    if (empty($customerEmail)) {
        throw new Exception("No customer email found in order");
    }
    
    // Test customer search
    debugLog("Searching for customer by email...");
    $existingCustomer = $netSuiteService->findCustomerByEmail($customerEmail);
    
    if ($existingCustomer) {
        debugLog("Customer found: " . json_encode($existingCustomer));
        
        $response = [
            'success' => true,
            'message' => 'Customer found in NetSuite',
            'order_id' => $orderId,
            'customer_email' => $customerEmail,
            'customer_found' => true,
            'customer_id' => $existingCustomer['id'],
            'customer_name' => ($existingCustomer['firstName'] ?? '') . ' ' . ($existingCustomer['lastName'] ?? ''),
            'customer_data' => $existingCustomer
        ];
    } else {
        debugLog("Customer NOT found");
        
        // Try to search with different query formats
        debugLog("Testing alternative search methods...");
        
        // Test with manual SuiteQL query
        try {
            $suiteQLQuery = "SELECT id, firstName, lastName, email, companyName FROM customer WHERE email = '" . $customerEmail . "'";
            debugLog("Testing SuiteQL: $suiteQLQuery");
            
            $suiteQLResult = $netSuiteService->executeSuiteQLQuery($suiteQLQuery);
            debugLog("SuiteQL result: " . json_encode($suiteQLResult));
            
        } catch (Exception $e) {
            debugLog("SuiteQL test failed: " . $e->getMessage());
        }
        
        $response = [
            'success' => true,
            'message' => 'Customer NOT found in NetSuite',
            'order_id' => $orderId,
            'customer_email' => $customerEmail,
            'customer_found' => false,
            'order_data' => [
                'BillingFirstName' => $orderData['BillingFirstName'] ?? '',
                'BillingLastName' => $orderData['BillingLastName'] ?? '',
                'BillingEmail' => $orderData['BillingEmail'] ?? '',
                'BillingPhone' => $orderData['BillingPhone'] ?? '',
                'CustomerID' => $orderData['CustomerID'] ?? ''
            ]
        ];
    }
    
    debugLog("Response prepared: " . json_encode($response));
    
    ob_clean();
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    debugLog("Exception: " . $e->getMessage());
    
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => true
    ]);
}

debugLog("=== DEBUG CUSTOMER SEARCH END ===");
?>