<?php
require_once __DIR__ . '/vendor/autoload.php';

use Laguna\Integration\Services\NetSuiteService;

echo "Testing Customer Creation With Different Contact Info\n";
echo "====================================================\n\n";

try {
    $netSuiteService = new NetSuiteService();
    
    $timestamp = time();
    
    // Create customer with similar company name but different contact info
    $customerData = [
        'companyName' => '1144809: OakTree Supply',
        'firstName' => 'John',
        'lastName' => 'Doe',
        'email' => "john.doe$timestamp@example.com",
        'phone' => '555-9999',
        'isPerson' => true,
        'subsidiary' => ['id' => 1],
        'defaultAddress' => '123 Test Street, Test City, TX, 12345'
    ];
    
    echo "Creating customer with data:\n";
    print_r($customerData);
    echo "\n";
    
    $result = $netSuiteService->createCustomer($customerData);
    
    echo "✅ Customer created successfully!\n";
    echo "Customer ID: " . (is_array($result) ? json_encode($result) : $result) . "\n";
    
} catch (Exception $e) {
    echo "❌ Error creating customer:\n";
    echo "Error: " . $e->getMessage() . "\n";
    
    // Let's also test with a completely unique company name
    echo "\n--- Testing with unique company name ---\n";
    
    try {
        $customerData2 = [
            'companyName' => "Unique Company $timestamp",
            'firstName' => 'David',
            'lastName' => 'Williams',
            'email' => 'david@williams.com',
            'phone' => '260-637-0054',
            'isPerson' => true,
            'subsidiary' => ['id' => 1],
            'defaultAddress' => '14110 Plank Street, Fort Wayne, IN, 46818'
        ];
        
        echo "Creating customer with unique company name:\n";
        print_r($customerData2);
        echo "\n";
        
        $result2 = $netSuiteService->createCustomer($customerData2);
        
        echo "✅ Customer created successfully with unique company name!\n";
        echo "Customer ID: " . (is_array($result2) ? json_encode($result2) : $result2) . "\n";
        
    } catch (Exception $e2) {
        echo "❌ Error with unique company name too:\n";
        echo "Error: " . $e2->getMessage() . "\n";
    }
}
?>