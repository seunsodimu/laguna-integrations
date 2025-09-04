<?php
/**
 * Test Payload Debug
 * 
 * This script tests the actual customer payload being generated vs expected format
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Services\NetSuiteService;
use Laguna\Integration\Services\OrderProcessingService;
use Laguna\Integration\Models\Customer;
use Laguna\Integration\Middleware\AuthMiddleware;

// Start output buffering
ob_start();

// Set JSON content type
header('Content-Type: application/json');

try {
    // Check authentication
    $auth = new AuthMiddleware();
    if (!$auth->isAuthenticated()) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'error' => 'Authentication required. Please log in.',
            'redirect' => 'login.php'
        ]);
        exit;
    }
    
    $response = [
        'success' => true,
        'steps' => []
    ];
    
    $response['steps'][] = 'Testing customer payload generation vs expected format...';
    
    // Expected payload format from the user
    $expectedPayload = [
        "firstName" => "Jaunty",
        "lastName" => "Elfricx",
        "email" => "guest13@lagunatest.com",
        "isPerson" => true,
        "subsidiary" => [
            "id" => 1
        ],
        "defaultAddress" => "75 W Overton Rd, Boise, ID, 83709",
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
    
    // Step 1: Test Customer model approach
    $response['steps'][] = 'Step 1: Testing Customer model approach...';
    
    $customer = Customer::fromOrderData($testOrderData);
    $customerModelPayload = $customer->toNetSuiteFormat();
    
    $response['customer_model_result'] = [
        'payload' => $customerModelPayload,
        'has_default_address' => isset($customerModelPayload['defaultAddress']),
        'has_addressbook' => isset($customerModelPayload['addressbook']),
        'addressbook_items' => count($customerModelPayload['addressbook']['items'] ?? [])
    ];
    
    // Step 2: Test OrderProcessingService approach
    $response['steps'][] = 'Step 2: Testing OrderProcessingService approach...';
    
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
    
    // Step 3: Compare structures
    $response['steps'][] = 'Step 3: Comparing payload structures...';
    
    $comparison = [
        'expected_vs_customer_model' => [
            'firstName_match' => ($expectedPayload['firstName'] === $customerModelPayload['firstName']),
            'lastName_match' => ($expectedPayload['lastName'] === $customerModelPayload['lastName']),
            'email_match' => ($expectedPayload['email'] === $customerModelPayload['email']),
            'isPerson_match' => ($expectedPayload['isPerson'] === $customerModelPayload['isPerson']),
            'has_defaultAddress' => isset($customerModelPayload['defaultAddress']),
            'has_addressbook' => isset($customerModelPayload['addressbook']),
            'addressbook_structure_match' => false
        ],
        'expected_vs_order_processing' => [
            'firstName_match' => ($expectedPayload['firstName'] === $netsuiteCustomer['firstName']),
            'lastName_match' => ($expectedPayload['lastName'] === $netsuiteCustomer['lastName']),
            'email_match' => ($expectedPayload['email'] === $netsuiteCustomer['email']),
            'isPerson_match' => ($expectedPayload['isPerson'] === $netsuiteCustomer['isPerson']),
            'has_defaultAddress' => isset($netsuiteCustomer['defaultAddress']),
            'has_addressbook' => isset($netsuiteCustomer['addressbook']),
            'addressbook_structure_match' => false
        ]
    ];
    
    // Check addressbook structure match for customer model
    if (isset($customerModelPayload['addressbook']['items']) && isset($expectedPayload['addressbook']['items'])) {
        $customerItems = $customerModelPayload['addressbook']['items'];
        $expectedItems = $expectedPayload['addressbook']['items'];
        
        if (count($customerItems) === count($expectedItems)) {
            $structureMatch = true;
            foreach ($customerItems as $index => $item) {
                if (!isset($expectedItems[$index])) {
                    $structureMatch = false;
                    break;
                }
                
                $expectedItem = $expectedItems[$index];
                if (($item['defaultBilling'] ?? false) !== ($expectedItem['defaultBilling'] ?? false) ||
                    ($item['defaultShipping'] ?? false) !== ($expectedItem['defaultShipping'] ?? false) ||
                    !isset($item['addressbookaddress']) || !isset($expectedItem['addressbookaddress'])) {
                    $structureMatch = false;
                    break;
                }
            }
            $comparison['expected_vs_customer_model']['addressbook_structure_match'] = $structureMatch;
        }
    }
    
    // Check addressbook structure match for order processing
    if (isset($netsuiteCustomer['addressbook']['items']) && isset($expectedPayload['addressbook']['items'])) {
        $orderItems = $netsuiteCustomer['addressbook']['items'];
        $expectedItems = $expectedPayload['addressbook']['items'];
        
        if (count($orderItems) === count($expectedItems)) {
            $structureMatch = true;
            foreach ($orderItems as $index => $item) {
                if (!isset($expectedItems[$index])) {
                    $structureMatch = false;
                    break;
                }
                
                $expectedItem = $expectedItems[$index];
                if (($item['defaultBilling'] ?? false) !== ($expectedItem['defaultBilling'] ?? false) ||
                    ($item['defaultShipping'] ?? false) !== ($expectedItem['defaultShipping'] ?? false) ||
                    !isset($item['addressbookaddress']) || !isset($expectedItem['addressbookaddress'])) {
                    $structureMatch = false;
                    break;
                }
            }
            $comparison['expected_vs_order_processing']['addressbook_structure_match'] = $structureMatch;
        }
    }
    
    $response['comparison'] = $comparison;
    
    // Step 4: Identify issues
    $response['steps'][] = 'Step 4: Identifying issues...';
    
    $issues = [];
    
    // Check Customer model issues
    if (!$comparison['expected_vs_customer_model']['has_defaultAddress']) {
        $issues[] = 'Customer model: Missing defaultAddress';
    }
    if (!$comparison['expected_vs_customer_model']['has_addressbook']) {
        $issues[] = 'Customer model: Missing addressbook';
    }
    if (!$comparison['expected_vs_customer_model']['addressbook_structure_match']) {
        $issues[] = 'Customer model: Addressbook structure mismatch';
    }
    
    // Check OrderProcessing issues
    if (!$comparison['expected_vs_order_processing']['has_defaultAddress']) {
        $issues[] = 'OrderProcessing: Missing defaultAddress';
    }
    if (!$comparison['expected_vs_order_processing']['has_addressbook']) {
        $issues[] = 'OrderProcessing: Missing addressbook';
    }
    if (!$comparison['expected_vs_order_processing']['addressbook_structure_match']) {
        $issues[] = 'OrderProcessing: Addressbook structure mismatch';
    }
    
    $response['issues_found'] = $issues;
    
    if (empty($issues)) {
        $response['steps'][] = "🎉 No issues found - payload generation is working correctly!";
        $response['status'] = 'success';
    } else {
        $response['steps'][] = "❌ Issues found: " . implode(', ', $issues);
        $response['status'] = 'issues_found';
    }
    
    // Step 5: Show detailed comparison
    $response['steps'][] = 'Step 5: Detailed payload comparison...';
    
    $response['detailed_comparison'] = [
        'expected_payload' => $expectedPayload,
        'customer_model_payload' => $customerModelPayload,
        'order_processing_payload' => $netsuiteCustomer,
        'field_by_field_comparison' => [
            'basic_fields' => [
                'firstName' => [
                    'expected' => $expectedPayload['firstName'],
                    'customer_model' => $customerModelPayload['firstName'] ?? 'MISSING',
                    'order_processing' => $netsuiteCustomer['firstName'] ?? 'MISSING'
                ],
                'lastName' => [
                    'expected' => $expectedPayload['lastName'],
                    'customer_model' => $customerModelPayload['lastName'] ?? 'MISSING',
                    'order_processing' => $netsuiteCustomer['lastName'] ?? 'MISSING'
                ],
                'email' => [
                    'expected' => $expectedPayload['email'],
                    'customer_model' => $customerModelPayload['email'] ?? 'MISSING',
                    'order_processing' => $netsuiteCustomer['email'] ?? 'MISSING'
                ]
            ],
            'address_fields' => [
                'defaultAddress' => [
                    'expected' => $expectedPayload['defaultAddress'],
                    'customer_model' => $customerModelPayload['defaultAddress'] ?? 'MISSING',
                    'order_processing' => $netsuiteCustomer['defaultAddress'] ?? 'MISSING'
                ],
                'addressbook_items_count' => [
                    'expected' => count($expectedPayload['addressbook']['items']),
                    'customer_model' => count($customerModelPayload['addressbook']['items'] ?? []),
                    'order_processing' => count($netsuiteCustomer['addressbook']['items'] ?? [])
                ]
            ]
        ]
    ];
    
    ob_clean();
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>