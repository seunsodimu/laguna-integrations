<?php
require_once __DIR__ . '/vendor/autoload.php';

use Laguna\Integration\Services\NetSuiteService;

echo "Testing Simple Customer Creation\n";
echo "===============================\n\n";

try {
    $netSuiteService = new NetSuiteService();
    
    // Create a simple customer with unique data
    $timestamp = time();
    $customerData = [
        'companyName' => "Test Company $timestamp",
        'firstName' => 'Test',
        'lastName' => 'User',
        'email' => "test$timestamp@example.com",
        'phone' => '555-0123',
        'isPerson' => true,
        'subsidiary' => ['id' => 1]
    ];
    
    echo "Creating customer with data:\n";
    print_r($customerData);
    echo "\n";
    
    $result = $netSuiteService->createCustomer($customerData);
    
    echo "✅ Customer created successfully!\n";
    echo "Customer ID: $result\n";
    
} catch (Exception $e) {
    echo "❌ Error creating customer:\n";
    echo "Error: " . $e->getMessage() . "\n";
}
?>