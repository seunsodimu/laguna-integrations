<?php
require_once __DIR__ . '/vendor/autoload.php';

use Laguna\Integration\Services\OrderProcessingService;
use Laguna\Integration\Utils\Logger;

echo "Testing Order 1144809 Processing\n";
echo "=================================\n\n";

try {
    // Initialize logger
    $logger = Logger::getInstance();
    
    // Initialize order processing service
    $orderService = new OrderProcessingService($logger);
    
    echo "Processing order 1144809 to get detailed NetSuite error...\n\n";
    
    // Process the order that was failing
    $result = $orderService->processOrder('1144809');
    
    if ($result['success']) {
        echo "✅ Order processed successfully!\n";
        echo "Sales Order ID: " . ($result['sales_order_id'] ?? 'N/A') . "\n";
        echo "Customer ID: " . ($result['customer_id'] ?? 'N/A') . "\n";
    } else {
        echo "❌ Order processing failed:\n";
        echo "Error: " . ($result['error'] ?? 'Unknown error') . "\n";
        echo "\nCheck the logs for detailed NetSuite error response.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Exception occurred:\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\nCheck the logs for detailed NetSuite error response.\n";
}

echo "\n=== LOG ANALYSIS ===\n";
echo "Check the latest log file for these entries:\n";
echo "1. 'NetSuite customer creation failed - Full error details' - Contains the full NetSuite response\n";
echo "2. 'NetSuite customer creation failed - Request payload' - Contains the exact payload sent\n";
echo "3. Look for any field validation warnings about truncated fields\n\n";

echo "The enhanced error logging should now show the complete NetSuite error response.\n";
?>