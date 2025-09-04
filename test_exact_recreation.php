<?php
require_once __DIR__ . '/vendor/autoload.php';

use Laguna\Integration\Services\NetSuiteService;

echo "Testing Exact Recreation of Failing Customer\n";
echo "===========================================\n\n";

try {
    $netSuiteService = new NetSuiteService();
    
    // Try creating the exact customer data that's failing, but with a slight modification
    echo "Attempt 1: Exact data with timestamp suffix in company name\n";
    $timestamp = time();
    
    $customerData1 = [
        'companyName' => "1144809: OakTree Supply $timestamp",
        'firstName' => 'David',
        'lastName' => 'Williams',
        'email' => 'david@williams.com',
        'phone' => '260-637-0054',
        'isPerson' => true,
        'subsidiary' => ['id' => 1],
        'defaultAddress' => '14110 Plank Street, Fort Wayne, IN, 46818'
    ];
    
    $result1 = $netSuiteService->createCustomer($customerData1);
    echo "✅ Customer created successfully with timestamp!\n";
    echo "Customer ID: " . json_encode($result1) . "\n\n";
    
    // Now try with the exact original data
    echo "Attempt 2: Exact original data (this should fail)\n";
    $customerData2 = [
        'companyName' => '1144809: OakTree Supply',
        'firstName' => 'David',
        'lastName' => 'Williams',
        'email' => 'david@williams.com',
        'phone' => '260-637-0054',
        'isPerson' => true,
        'subsidiary' => ['id' => 1],
        'defaultAddress' => '14110 Plank Street, Fort Wayne, IN, 46818'
    ];
    
    $result2 = $netSuiteService->createCustomer($customerData2);
    echo "✅ Customer created successfully with exact data!\n";
    echo "Customer ID: " . json_encode($result2) . "\n";
    
} catch (Exception $e) {
    echo "❌ Error occurred:\n";
    echo "Error: " . $e->getMessage() . "\n";
    
    // The error will tell us exactly which attempt failed
    echo "\nThis confirms whether the issue is with the exact data combination or something else.\n";
}
?>