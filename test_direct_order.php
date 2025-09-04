<?php
require_once __DIR__ . '/vendor/autoload.php';

use Laguna\Integration\Controllers\WebhookController;
use Laguna\Integration\Utils\Logger;

echo "Testing Direct Order Processing\n";
echo "===============================\n\n";

try {
    // Clear any opcache to ensure our changes are loaded
    if (function_exists('opcache_reset')) {
        opcache_reset();
        echo "✅ OPcache cleared\n";
    } else {
        echo "ℹ️  OPcache not available\n";
    }
    
    // Initialize webhook controller
    $webhookController = new WebhookController();
    $logger = Logger::getInstance();
    
    echo "Processing order 1144809 directly...\n\n";
    
    // Process the order
    $result = $webhookController->processOrder('1144809');
    
    if ($result['success']) {
        echo "✅ Order processed successfully!\n";
        echo "NetSuite Order ID: " . ($result['netsuite_order_id'] ?? 'N/A') . "\n";
    } else {
        echo "❌ Order processing failed:\n";
        echo "Error: " . ($result['error'] ?? 'Unknown error') . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Exception occurred:\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n=== Check the latest log entries for enhanced error details ===\n";
?>