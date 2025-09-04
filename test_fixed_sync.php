<?php
require_once __DIR__ . '/vendor/autoload.php';

use Laguna\Integration\Controllers\WebhookController;
use Laguna\Integration\Services\NetSuiteService;
use Laguna\Integration\Services\ThreeDCartService;
use Laguna\Integration\Services\EmailService;
use Laguna\Integration\Utils\Logger;

echo "Testing Fixed Sync for Order 1144809\n";
echo "====================================\n\n";

try {
    // Initialize services
    $logger = Logger::getInstance();
    $netSuiteService = new NetSuiteService();
    $threeDCartService = new ThreeDCartService();
    $emailService = new EmailService();
    
    // Initialize webhook controller with updated logic
    $webhookController = new WebhookController($netSuiteService, $threeDCartService, $emailService, $logger);
    
    echo "1. Testing phone number search for existing customer...\n";
    $existingCustomer = $netSuiteService->findCustomerByPhone('260-637-0054');
    
    if ($existingCustomer) {
        echo "✅ Found existing customer by phone:\n";
        echo "   Customer ID: " . $existingCustomer['id'] . "\n";
        echo "   Email: " . ($existingCustomer['email'] ?? 'N/A') . "\n";
        echo "   Name: " . ($existingCustomer['firstName'] ?? '') . " " . ($existingCustomer['lastName'] ?? '') . "\n\n";
    } else {
        echo "❌ No customer found with phone 260-637-0054\n\n";
    }
    
    echo "2. Processing order 1144809 with updated logic...\n";
    
    // Process the order using the webhook controller
    $result = $webhookController->processOrder('1144809');
    
    if ($result) {
        echo "✅ Order processed successfully!\n";
        echo "Result: " . json_encode($result) . "\n";
    } else {
        echo "❌ Order processing failed\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error occurred:\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== Check the latest log file for detailed results ===\n";
?>