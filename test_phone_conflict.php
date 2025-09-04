<?php
require_once __DIR__ . '/vendor/autoload.php';

use Laguna\Integration\Services\NetSuiteService;

echo "Testing Phone Number Conflict\n";
echo "=============================\n\n";

try {
    $netSuiteService = new NetSuiteService();
    $timestamp = time();
    
    // Test 1: Same email, different phone
    echo "Test 1: Same email (david@williams.com), different phone\n";
    $customerData1 = [
        'companyName' => "Test Company Email $timestamp",
        'firstName' => 'David',
        'lastName' => 'Williams',
        'email' => 'david@williams.com',
        'phone' => '555-1234',  // Different phone
        'isPerson' => true,
        'subsidiary' => ['id' => 1]
    ];
    
    $result1 = $netSuiteService->createCustomer($customerData1);
    echo "✅ Customer created with same email, different phone!\n";
    echo "Customer ID: " . json_encode($result1) . "\n\n";
    
    // Test 2: Different email, same phone
    echo "Test 2: Different email, same phone (260-637-0054)\n";
    $customerData2 = [
        'companyName' => "Test Company Phone $timestamp",
        'firstName' => 'John',
        'lastName' => 'Smith',
        'email' => "test.phone$timestamp@example.com",
        'phone' => '260-637-0054',  // Same phone as the failing order
        'isPerson' => true,
        'subsidiary' => ['id' => 1]
    ];
    
    $result2 = $netSuiteService->createCustomer($customerData2);
    echo "✅ Customer created with same phone, different email!\n";
    echo "Customer ID: " . json_encode($result2) . "\n\n";
    
} catch (Exception $e) {
    echo "❌ Error occurred:\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "\nThis tells us which field is causing the conflict!\n";
}
?>