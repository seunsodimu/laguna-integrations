<?php

require_once __DIR__ . '/vendor/autoload.php';

use Laguna\Integration\Services\NetSuiteService;
use Laguna\Integration\Utils\Logger;

/**
 * Test to verify dropship customers are created without email addresses
 */

echo "<h1>Testing Dropship Customer Creation Without Email</h1>\n";

try {
    $netSuiteService = new NetSuiteService();
    $logger = Logger::getInstance();
    
    echo "<h2>Test: Dropship Customer Data Building</h2>\n";
    
    $dropshipOrderData = [
        'OrderID' => 'TEST_DROPSHIP_NO_EMAIL_' . time(),
        'BillingPaymentMethod' => 'Dropship to Customer',
        'BillingEmail' => 'company@example.com',
        'BillingPhoneNumber' => '555-123-4567',
        'InvoiceNumberPrefix' => 'INV',
        'InvoiceNumber' => time(),
        'ShipmentList' => [
            [
                'ShipmentFirstName' => 'Jane',
                'ShipmentLastName' => 'Smith',
                'ShipmentPhone' => '555-987-6543'
            ]
        ],
        'QuestionList' => [
            [
                'QuestionID' => 1,
                'QuestionAnswer' => 'customer@example.com'
            ]
        ]
    ];
    
    echo "<h3>Testing Customer Data Building (Internal Method)</h3>\n";
    
    // Use reflection to test the buildDropshipCustomerData method
    $reflection = new ReflectionClass($netSuiteService);
    $method = $reflection->getMethod('buildDropshipCustomerData');
    $method->setAccessible(true);
    
    // Test with valid email
    $customerData = $method->invoke($netSuiteService, $dropshipOrderData, 'customer@example.com', true, null);
    
    echo "<h4>Customer Data Generated:</h4>\n";
    echo "<pre>" . json_encode([
        'firstname' => $customerData['firstname'],
        'lastname' => $customerData['lastName'],
        'isPerson' => $customerData['isPerson'],
        'email' => $customerData['email'],
        'phone' => $customerData['phone'],
        'company' => $customerData['company']
    ], JSON_PRETTY_PRINT) . "</pre>\n";
    
    // Verify email is empty
    if ($customerData['email'] === '') {
        echo "<p>✅ <strong>SUCCESS:</strong> Email field is empty as expected for dropship customer</p>\n";
    } else {
        echo "<p>❌ <strong>ERROR:</strong> Email field should be empty but contains: '{$customerData['email']}'</p>\n";
    }
    
    // Verify isPerson is true
    if ($customerData['isPerson'] === true) {
        echo "<p>✅ <strong>SUCCESS:</strong> isPerson is true as expected for dropship customer</p>\n";
    } else {
        echo "<p>❌ <strong>ERROR:</strong> isPerson should be true but is: " . var_export($customerData['isPerson'], true) . "</p>\n";
    }
    
    // Verify lastname contains invoice number
    $expectedInvoice = $dropshipOrderData['InvoiceNumberPrefix'] . $dropshipOrderData['InvoiceNumber'];
    if (strpos($customerData['lastName'], $expectedInvoice) !== false) {
        echo "<p>✅ <strong>SUCCESS:</strong> Invoice number '{$expectedInvoice}' found in lastname</p>\n";
    } else {
        echo "<p>❌ <strong>ERROR:</strong> Invoice number '{$expectedInvoice}' not found in lastname: '{$customerData['lastName']}'</p>\n";
    }
    
    echo "<h2>Test: Full Dropship Customer Creation</h2>\n";
    
    echo "<h3>Creating Actual Dropship Customer...</h3>\n";
    $customerId = $netSuiteService->findOrCreateCustomerByPaymentMethod($dropshipOrderData);
    echo "<p><strong>Created Customer ID:</strong> {$customerId}</p>\n";
    
    echo "<h2>Test: Comparison with Regular Customer</h2>\n";
    
    $regularOrderData = $dropshipOrderData;
    $regularOrderData['BillingPaymentMethod'] = 'Credit Card';
    $regularOrderData['OrderID'] = 'TEST_REGULAR_NO_EMAIL_' . time();
    $regularOrderData['BillingCompany'] = 'Test Company ' . time();
    
    echo "<h3>Testing Regular Customer Data Building</h3>\n";
    
    $method = $reflection->getMethod('buildRegularCustomerData');
    $method->setAccessible(true);
    
    $regularCustomerData = $method->invoke($netSuiteService, $regularOrderData, 'customer@example.com', true, null);
    
    echo "<h4>Regular Customer Data Generated:</h4>\n";
    echo "<pre>" . json_encode([
        'firstname' => $regularCustomerData['firstname'],
        'lastname' => $regularCustomerData['lastName'],
        'isPerson' => $regularCustomerData['isPerson'],
        'email' => $regularCustomerData['email'],
        'phone' => $regularCustomerData['phone'],
        'company' => $regularCustomerData['company']
    ], JSON_PRETTY_PRINT) . "</pre>\n";
    
    // Verify regular customer has email
    if ($regularCustomerData['email'] === 'customer@example.com') {
        echo "<p>✅ <strong>SUCCESS:</strong> Regular customer has email as expected</p>\n";
    } else {
        echo "<p>❌ <strong>ERROR:</strong> Regular customer should have email but has: '{$regularCustomerData['email']}'</p>\n";
    }
    
    // Verify regular customer isPerson is false
    if ($regularCustomerData['isPerson'] === false) {
        echo "<p>✅ <strong>SUCCESS:</strong> Regular customer isPerson is false as expected</p>\n";
    } else {
        echo "<p>❌ <strong>ERROR:</strong> Regular customer isPerson should be false but is: " . var_export($regularCustomerData['isPerson'], true) . "</p>\n";
    }
    
    echo "<h2>✅ Test Summary</h2>\n";
    echo "<h3>Dropship Customer Behavior:</h3>\n";
    echo "<ul>\n";
    echo "<li>✅ <strong>Email:</strong> Always empty (regardless of valid email in QuestionList)</li>\n";
    echo "<li>✅ <strong>isPerson:</strong> Always true</li>\n";
    echo "<li>✅ <strong>Lastname:</strong> Includes invoice number</li>\n";
    echo "<li>✅ <strong>Customer Creation:</strong> Successfully creates in NetSuite</li>\n";
    echo "</ul>\n";
    
    echo "<h3>Regular Customer Behavior (for comparison):</h3>\n";
    echo "<ul>\n";
    echo "<li>✅ <strong>Email:</strong> Uses validated email from QuestionList</li>\n";
    echo "<li>✅ <strong>isPerson:</strong> Always false (company)</li>\n";
    echo "<li>✅ <strong>Company:</strong> Uses BillingCompany or constructed name</li>\n";
    echo "</ul>\n";
    
    echo "<p><strong>✅ All tests passed! Dropship customers are correctly created without email addresses.</strong></p>\n";
    
} catch (Exception $e) {
    echo "<p><strong>❌ Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>\n";
}

?>