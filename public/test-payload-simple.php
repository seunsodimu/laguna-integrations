<?php
/**
 * Simple Payload Test (No Auth Required)
 * 
 * This script tests the customer payload generation without authentication
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Services\NetSuiteService;
use Laguna\Integration\Services\OrderProcessingService;
use Laguna\Integration\Models\Customer;

// Set JSON content type
header('Content-Type: application/json');

try {
    $response = [
        'success' => true,
        'steps' => []
    ];
    
    $response['steps'][] = 'Testing customer payload generation (no auth)...';
    
    // Expected payload format from the user
    $expectedPayload = [
        "firstName" => "Jaunty",
        "lastName" => "Elfricx",
        "email" => "guest13@lagunatest.com",
        "isPerson" => true,
        "subsidiary" => [
            "id" => 1
        ],
        "defaultAddress" => "13 Overton RD, Boise, ID, 83709",
        "addressbook" => [
            "items" => [
                [
                    "defaultBilling" => true,
                    "defaultShipping" => false,
                    "addressbookaddress" => [
                        "country" => "US",
                        "zip" => "83709",
                        "addressee" => "Jam Company",
                        "addr1" => "13 Overton RD",
                        "city" => "Boise",
                        "state" => "ID"
                    ]
                ],
                [
                    "defaultBilling" => false,
                    "defaultShipping" => true,
                    "addressbookaddress" => [
                        "country" => "US",
                        "zip" => "83111",
                        "addressee" => "JamShip Company",
                        "addr1" => "5513 Oldverton RD",
                        "city" => "Boise",
                        "state" => "ID"
                    ]
                ]
            ]
        ]
    ];
    
    // Create test order data that should generate similar payload
    $testOrderData = [
        'OrderID' => 'TEST-12345',
        'BillingFirstName' => 'Jaunty',
        'BillingLastName' => 'Elfricx',
        'BillingCompany' => 'Jam Company',
        'BillingEmail' => 'guest13@lagunatest.com',
        'BillingEmailAddress' => 'guest13@lagunatest.com',
        'BillingAddress' => '13 Overton RD',
        'BillingAddress2' => '',
        'BillingCity' => 'Boise',
        'BillingState' => 'ID',
        'BillingZipCode' => '83709',
        'BillingCountry' => 'US',
        'BillingPhoneNumber' => '555-123-4567',
        'ShipmentList' => [
            [
                'ShipmentCompany' => 'JamShip Company',
                'ShipmentAddress' => '5513 Oldverton RD',
                'ShipmentAddress2' => '',
                'ShipmentCity' => 'Boise',
                'ShipmentState' => 'ID',
                'ShipmentZipCode' => '83111',
                'ShipmentCountry' => 'US'
            ]
        ]
    ];
    
    $response['test_data'] = [
        'expected_payload' => $expectedPayload,
        'test_order_data' => $testOrderData
    ];
    
    // Test Customer model approach
    $response['steps'][] = 'Testing Customer model approach...';
    
    $customer = Customer::fromOrderData($testOrderData);
    $customerModelPayload = $customer->toNetSuiteFormat();
    
    $response['customer_model_result'] = [
        'payload' => $customerModelPayload,
        'has_default_address' => isset($customerModelPayload['defaultAddress']),
        'has_addressbook' => isset($customerModelPayload['addressbook']),
        'addressbook_items' => count($customerModelPayload['addressbook']['items'] ?? [])
    ];
    
    // Test OrderProcessingService approach
    $response['steps'][] = 'Testing OrderProcessingService approach...';
    
    $orderProcessingService = new OrderProcessingService();
    $reflection = new ReflectionClass($orderProcessingService);
    $extractMethod = $reflection->getMethod('extractCustomerInfo');
    $extractMethod->setAccessible(true);
    $extractedCustomerInfo = $extractMethod->invoke($orderProcessingService, $testOrderData);
    
    // Simulate NetSuite customer creation
    $netsuiteCustomer = [
        'firstName' => $extractedCustomerInfo['firstname'],
        'lastName' => $extractedCustomerInfo['lastname'],
        'email' => $extractedCustomerInfo['email'],
        'isPerson' => true,
        'subsidiary' => ['id' => 1]
    ];
    
    // Apply address creation
    $netsuiteService = new NetSuiteService();
    $netsuiteReflection = new ReflectionClass($netsuiteService);
    $addAddressesMethod = $netsuiteReflection->getMethod('addCustomerAddresses');
    $addAddressesMethod->setAccessible(true);
    $addAddressesMethod->invoke($netsuiteService, $netsuiteCustomer, $extractedCustomerInfo);
    
    $response['order_processing_result'] = [
        'extracted_data' => $extractedCustomerInfo,
        'payload' => $netsuiteCustomer,
        'has_default_address' => isset($netsuiteCustomer['defaultAddress']),
        'has_addressbook' => isset($netsuiteCustomer['addressbook']),
        'addressbook_items' => count($netsuiteCustomer['addressbook']['items'] ?? [])
    ];
    
    // Compare with expected
    $response['comparison'] = [
        'expected_default_address' => $expectedPayload['defaultAddress'],
        'customer_model_default_address' => $customerModelPayload['defaultAddress'] ?? 'MISSING',
        'order_processing_default_address' => $netsuiteCustomer['defaultAddress'] ?? 'MISSING',
        
        'expected_addressbook_items' => count($expectedPayload['addressbook']['items']),
        'customer_model_addressbook_items' => count($customerModelPayload['addressbook']['items'] ?? []),
        'order_processing_addressbook_items' => count($netsuiteCustomer['addressbook']['items'] ?? []),
        
        'customer_model_matches_expected' => [
            'defaultAddress' => ($customerModelPayload['defaultAddress'] ?? '') === $expectedPayload['defaultAddress'],
            'addressbook_count' => count($customerModelPayload['addressbook']['items'] ?? []) === count($expectedPayload['addressbook']['items']),
            'basic_fields' => [
                'firstName' => ($customerModelPayload['firstName'] ?? '') === $expectedPayload['firstName'],
                'lastName' => ($customerModelPayload['lastName'] ?? '') === $expectedPayload['lastName'],
                'email' => ($customerModelPayload['email'] ?? '') === $expectedPayload['email'],
                'isPerson' => ($customerModelPayload['isPerson'] ?? false) === $expectedPayload['isPerson']
            ]
        ],
        
        'order_processing_matches_expected' => [
            'defaultAddress' => ($netsuiteCustomer['defaultAddress'] ?? '') === $expectedPayload['defaultAddress'],
            'addressbook_count' => count($netsuiteCustomer['addressbook']['items'] ?? []) === count($expectedPayload['addressbook']['items']),
            'basic_fields' => [
                'firstName' => ($netsuiteCustomer['firstName'] ?? '') === $expectedPayload['firstName'],
                'lastName' => ($netsuiteCustomer['lastName'] ?? '') === $expectedPayload['lastName'],
                'email' => ($netsuiteCustomer['email'] ?? '') === $expectedPayload['email'],
                'isPerson' => ($netsuiteCustomer['isPerson'] ?? false) === $expectedPayload['isPerson']
            ]
        ]
    ];
    
    // Identify issues
    $issues = [];
    
    if (!$response['customer_model_result']['has_default_address']) {
        $issues[] = 'Customer model: Missing defaultAddress';
    }
    if (!$response['customer_model_result']['has_addressbook']) {
        $issues[] = 'Customer model: Missing addressbook';
    }
    if ($response['customer_model_result']['addressbook_items'] === 0) {
        $issues[] = 'Customer model: Empty addressbook items';
    }
    
    if (!$response['order_processing_result']['has_default_address']) {
        $issues[] = 'OrderProcessing: Missing defaultAddress';
    }
    if (!$response['order_processing_result']['has_addressbook']) {
        $issues[] = 'OrderProcessing: Missing addressbook';
    }
    if ($response['order_processing_result']['addressbook_items'] === 0) {
        $issues[] = 'OrderProcessing: Empty addressbook items';
    }
    
    // Check if defaultAddress format matches
    if (($customerModelPayload['defaultAddress'] ?? '') !== $expectedPayload['defaultAddress']) {
        $issues[] = 'Customer model: defaultAddress format mismatch';
    }
    if (($netsuiteCustomer['defaultAddress'] ?? '') !== $expectedPayload['defaultAddress']) {
        $issues[] = 'OrderProcessing: defaultAddress format mismatch';
    }
    
    $response['issues_found'] = $issues;
    
    if (empty($issues)) {
        $response['status'] = 'success';
        $response['message'] = 'All tests passed - payload generation is working correctly!';
    } else {
        $response['status'] = 'issues_found';
        $response['message'] = 'Issues found: ' . implode(', ', $issues);
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>