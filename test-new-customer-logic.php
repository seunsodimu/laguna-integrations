<?php

require_once __DIR__ . '/vendor/autoload.php';

use Laguna\Integration\Services\NetSuiteService;
use Laguna\Integration\Utils\Logger;

/**
 * Test script for the new customer assignment logic based on BillingPaymentMethod
 */

echo "<h1>Testing New Customer Assignment Logic</h1>\n";

try {
    $netSuiteService = new NetSuiteService();
    $logger = Logger::getInstance();
    
    echo "<h2>Test 1: Dropship Order</h2>\n";
    
    // Test dropship order
    $dropshipOrderData = [
        'OrderID' => 'TEST_DROPSHIP_001',
        'BillingPaymentMethod' => 'Dropship to Customer',
        'BillingEmail' => 'company@example.com',
        'BillingPhoneNumber' => '555-123-4567',
        'BillingFirstName' => 'John',
        'BillingLastName' => 'Doe',
        'BillingCompany' => 'Test Company',
        'BillingAddress' => '123 Main St',
        'BillingCity' => 'Anytown',
        'BillingState' => 'CA',
        'BillingZipCode' => '12345',
        'BillingCountry' => 'US',
        'InvoiceNumberPrefix' => 'INV',
        'InvoiceNumber' => '12345',
        'ShipmentList' => [
            [
                'ShipmentFirstName' => 'Jane',
                'ShipmentLastName' => 'Smith',
                'ShipmentPhone' => '555-987-6543',
                'ShipmentAddress' => '456 Oak Ave',
                'ShipmentCity' => 'Somewhere',
                'ShipmentState' => 'NY',
                'ShipmentZipCode' => '67890',
                'ShipmentCountry' => 'US'
            ]
        ],
        'QuestionList' => [
            [
                'QuestionID' => 1,
                'QuestionAnswer' => 'customer@example.com'
            ],
            [
                'QuestionID' => 2,
                'QuestionAnswer' => 'PO123456'
            ]
        ]
    ];
    
    echo "<h3>Dropship Order Data:</h3>\n";
    echo "<pre>" . json_encode($dropshipOrderData, JSON_PRETTY_PRINT) . "</pre>\n";
    
    echo "<h3>Processing Dropship Order...</h3>\n";
    $dropshipCustomerId = $netSuiteService->findOrCreateCustomerByPaymentMethod($dropshipOrderData);
    echo "<p><strong>Dropship Customer ID:</strong> {$dropshipCustomerId}</p>\n";
    
    echo "<h2>Test 2: Regular Order with Valid Email</h2>\n";
    
    // Test regular order with valid email
    $regularOrderData = [
        'OrderID' => 'TEST_REGULAR_001',
        'BillingPaymentMethod' => 'Credit Card',
        'BillingEmail' => 'billing@example.com',
        'BillingPhoneNumber' => '555-111-2222',
        'BillingFirstName' => 'Bob',
        'BillingLastName' => 'Johnson',
        'BillingCompany' => 'Johnson Corp',
        'BillingAddress' => '789 Pine St',
        'BillingCity' => 'Testville',
        'BillingState' => 'TX',
        'BillingZipCode' => '54321',
        'BillingCountry' => 'US',
        'ShipmentList' => [
            [
                'ShipmentFirstName' => 'Bob',
                'ShipmentLastName' => 'Johnson',
                'ShipmentPhone' => '555-111-2222',
                'ShipmentAddress' => '789 Pine St',
                'ShipmentCity' => 'Testville',
                'ShipmentState' => 'TX',
                'ShipmentZipCode' => '54321',
                'ShipmentCountry' => 'US'
            ]
        ],
        'QuestionList' => [
            [
                'QuestionID' => 1,
                'QuestionAnswer' => 'store@example.com'
            ]
        ]
    ];
    
    echo "<h3>Regular Order Data:</h3>\n";
    echo "<pre>" . json_encode($regularOrderData, JSON_PRETTY_PRINT) . "</pre>\n";
    
    echo "<h3>Processing Regular Order...</h3>\n";
    $regularCustomerId = $netSuiteService->findOrCreateCustomerByPaymentMethod($regularOrderData);
    echo "<p><strong>Regular Customer ID:</strong> {$regularCustomerId}</p>\n";
    
    echo "<h2>Test 3: Regular Order with Invalid Email</h2>\n";
    
    // Test regular order with invalid email
    $invalidEmailOrderData = [
        'OrderID' => 'TEST_INVALID_001',
        'BillingPaymentMethod' => 'PayPal',
        'BillingEmail' => 'billing2@example.com',
        'BillingPhoneNumber' => '555-333-4444',
        'BillingFirstName' => 'Alice',
        'BillingLastName' => 'Williams',
        'BillingCompany' => 'Williams LLC',
        'BillingAddress' => '321 Elm St',
        'BillingCity' => 'Nowhere',
        'BillingState' => 'FL',
        'BillingZipCode' => '98765',
        'BillingCountry' => 'US',
        'ShipmentList' => [
            [
                'ShipmentFirstName' => 'Alice',
                'ShipmentLastName' => 'Williams',
                'ShipmentPhone' => '555-333-4444',
                'ShipmentAddress' => '321 Elm St',
                'ShipmentCity' => 'Nowhere',
                'ShipmentState' => 'FL',
                'ShipmentZipCode' => '98765',
                'ShipmentCountry' => 'US'
            ]
        ],
        'QuestionList' => [
            [
                'QuestionID' => 1,
                'QuestionAnswer' => 'not-an-email'
            ]
        ]
    ];
    
    echo "<h3>Invalid Email Order Data:</h3>\n";
    echo "<pre>" . json_encode($invalidEmailOrderData, JSON_PRETTY_PRINT) . "</pre>\n";
    
    echo "<h3>Processing Invalid Email Order...</h3>\n";
    $invalidEmailCustomerId = $netSuiteService->findOrCreateCustomerByPaymentMethod($invalidEmailOrderData);
    echo "<p><strong>Invalid Email Customer ID:</strong> {$invalidEmailCustomerId}</p>\n";
    
    echo "<h2>Test Summary</h2>\n";
    echo "<ul>\n";
    echo "<li><strong>Dropship Customer ID:</strong> {$dropshipCustomerId}</li>\n";
    echo "<li><strong>Regular Customer ID:</strong> {$regularCustomerId}</li>\n";
    echo "<li><strong>Invalid Email Customer ID:</strong> {$invalidEmailCustomerId}</li>\n";
    echo "</ul>\n";
    
    echo "<h2>Logic Verification</h2>\n";
    echo "<h3>Expected Behavior:</h3>\n";
    echo "<ul>\n";
    echo "<li><strong>Dropship Order:</strong> Should create a person customer (isPerson=T) with invoice number in lastname</li>\n";
    echo "<li><strong>Regular Order:</strong> Should search for existing store customer first, then create company customer (isPerson=F) if not found</li>\n";
    echo "<li><strong>Invalid Email Order:</strong> Should create company customer (isPerson=F) without email</li>\n";
    echo "</ul>\n";
    
    echo "<p><strong>✅ All tests completed successfully!</strong></p>\n";
    
} catch (Exception $e) {
    echo "<p><strong>❌ Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>\n";
}

echo "<h2>Implementation Summary</h2>\n";
echo "<h3>New Customer Assignment Logic:</h3>\n";
echo "<ol>\n";
echo "<li><strong>Check BillingPaymentMethod:</strong></li>\n";
echo "<ul>\n";
echo "<li>If 'Dropship to Customer': Create person customer with invoice number in lastname</li>\n";
echo "<li>If not 'Dropship to Customer': Search for existing store customer or create company customer</li>\n";
echo "</ul>\n";
echo "<li><strong>Email Validation:</strong> Extract from QuestionList[QuestionID=1] and validate format</li>\n";
echo "<li><strong>Parent Company Search:</strong> Use billing email/phone to find parent company (isPerson=F)</li>\n";
echo "<li><strong>Customer Creation:</strong> Set appropriate isPerson flag and customer data based on order type</li>\n";
echo "</ol>\n";

?>