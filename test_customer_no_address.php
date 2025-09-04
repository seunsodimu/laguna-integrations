<?php
require_once __DIR__ . '/vendor/autoload.php';

use Laguna\Integration\Services\NetSuiteService;

echo "Testing Customer Creation Without Address Book\n";
echo "=============================================\n\n";

try {
    $netSuiteService = new NetSuiteService();
    
    // Create customer with same basic data as the failing order, but without addressbook
    $customerData = [
        'companyName' => '1144809: OakTree Supply TEST',
        'firstName' => 'David',
        'lastName' => 'Williams',
        'email' => 'david.test@williams.com',
        'phone' => '260-637-0054',
        'isPerson' => true,
        'subsidiary' => ['id' => 1],
        'defaultAddress' => '14110 Plank Street, Fort Wayne, IN, 46818'
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
}
?>