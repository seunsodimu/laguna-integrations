<?php
/**
 * Test Address Fix
 * 
 * This script tests the customer address creation fix to ensure addressbook is populated
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
    
    $response['steps'][] = 'Testing customer address creation fix...';
    
    // Step 1: Test the Customer model toNetSuiteFormat method
    $response['steps'][] = 'Step 1: Testing Customer model toNetSuiteFormat method...';
    
    // Create test order data
    $testOrderData = [
        'OrderID' => 'TEST-12345',
        'BillingFirstName' => 'John',
        'BillingLastName' => 'Doe',
        'BillingCompany' => 'Test Company Inc',
        'BillingEmail' => 'john.doe@testcompany.com',
        'BillingAddress' => '123 Main Street',
        'BillingAddress2' => 'Suite 100',
        'BillingCity' => 'New York',
        'BillingState' => 'NY',
        'BillingZipCode' => '10001',
        'BillingCountry' => 'US',
        'BillingPhoneNumber' => '555-123-4567',
        'ShipmentList' => [
            [
                'ShipmentCompany' => 'Shipping Warehouse',
                'ShipmentAddress' => '456 Industrial Blvd',
                'ShipmentAddress2' => 'Dock 5',
                'ShipmentCity' => 'Chicago',
                'ShipmentState' => 'IL',
                'ShipmentZipCode' => '60601',
                'ShipmentCountry' => 'US'
            ]
        ]
    ];
    
    // Create Customer from order data
    $customer = Customer::fromOrderData($testOrderData);
    $netsuiteFormat = $customer->toNetSuiteFormat();
    
    $response['customer_model_test'] = [
        'input_order_data' => $testOrderData,
        'customer_object' => $customer->toArray(),
        'netsuite_format' => $netsuiteFormat,
        'has_billing_fields' => isset($netsuiteFormat['BillingFirstName']),
        'has_shipment_list' => isset($netsuiteFormat['ShipmentList']),
        'billing_fields_present' => [
            'BillingFirstName' => isset($netsuiteFormat['BillingFirstName']),
            'BillingLastName' => isset($netsuiteFormat['BillingLastName']),
            'BillingCompany' => isset($netsuiteFormat['BillingCompany']),
            'BillingAddress' => isset($netsuiteFormat['BillingAddress']),
            'BillingCity' => isset($netsuiteFormat['BillingCity']),
            'BillingState' => isset($netsuiteFormat['BillingState']),
            'BillingZipCode' => isset($netsuiteFormat['BillingZipCode']),
            'BillingCountry' => isset($netsuiteFormat['BillingCountry']),
            'BillingPhoneNumber' => isset($netsuiteFormat['BillingPhoneNumber'])
        ]
    ];
    
    if ($netsuiteFormat['BillingFirstName'] ?? false) {
        $response['steps'][] = "✅ Customer model now includes billing fields";
    } else {
        $response['steps'][] = "❌ Customer model missing billing fields";
    }
    
    if ($netsuiteFormat['ShipmentList'] ?? false) {
        $response['steps'][] = "✅ Customer model now includes ShipmentList";
    } else {
        $response['steps'][] = "❌ Customer model missing ShipmentList";
    }
    
    // Step 2: Test the OrderProcessingService extractCustomerInfo method
    $response['steps'][] = 'Step 2: Testing OrderProcessingService extractCustomerInfo method...';
    
    // Create a reflection to access the private method
    $orderProcessingService = new OrderProcessingService();
    $reflection = new ReflectionClass($orderProcessingService);
    $extractMethod = $reflection->getMethod('extractCustomerInfo');
    $extractMethod->setAccessible(true);
    
    $extractedCustomerInfo = $extractMethod->invoke($orderProcessingService, $testOrderData);
    
    $response['order_processing_test'] = [
        'input_order_data' => $testOrderData,
        'extracted_customer_info' => $extractedCustomerInfo,
        'has_billing_fields' => isset($extractedCustomerInfo['BillingFirstName']),
        'has_shipment_list' => isset($extractedCustomerInfo['ShipmentList']),
        'billing_fields_present' => [
            'BillingFirstName' => isset($extractedCustomerInfo['BillingFirstName']),
            'BillingLastName' => isset($extractedCustomerInfo['BillingLastName']),
            'BillingCompany' => isset($extractedCustomerInfo['BillingCompany']),
            'BillingAddress' => isset($extractedCustomerInfo['BillingAddress']),
            'BillingCity' => isset($extractedCustomerInfo['BillingCity']),
            'BillingState' => isset($extractedCustomerInfo['BillingState']),
            'BillingZipCode' => isset($extractedCustomerInfo['BillingZipCode']),
            'BillingCountry' => isset($extractedCustomerInfo['BillingCountry']),
            'BillingPhoneNumber' => isset($extractedCustomerInfo['BillingPhoneNumber'])
        ]
    ];
    
    if ($extractedCustomerInfo['BillingFirstName'] ?? false) {
        $response['steps'][] = "✅ OrderProcessingService now includes billing fields";
    } else {
        $response['steps'][] = "❌ OrderProcessingService missing billing fields";
    }
    
    if ($extractedCustomerInfo['ShipmentList'] ?? false) {
        $response['steps'][] = "✅ OrderProcessingService now includes ShipmentList";
    } else {
        $response['steps'][] = "❌ OrderProcessingService missing ShipmentList";
    }
    
    // Step 3: Test the NetSuiteService address methods
    $response['steps'][] = 'Step 3: Testing NetSuiteService address creation methods...';
    
    // Create NetSuiteService instance
    $netsuiteService = new NetSuiteService();
    
    // Use reflection to test the private address methods
    $netsuiteReflection = new ReflectionClass($netsuiteService);
    
    // Test buildDefaultAddressString
    $buildDefaultMethod = $netsuiteReflection->getMethod('buildDefaultAddressString');
    $buildDefaultMethod->setAccessible(true);
    $defaultAddress = $buildDefaultMethod->invoke($netsuiteService, $extractedCustomerInfo);
    
    // Test buildAddressbook
    $buildAddressbookMethod = $netsuiteReflection->getMethod('buildAddressbook');
    $buildAddressbookMethod->setAccessible(true);
    $addressbook = $buildAddressbookMethod->invoke($netsuiteService, $extractedCustomerInfo);
    
    $response['netsuite_service_test'] = [
        'input_customer_data' => $extractedCustomerInfo,
        'default_address' => $defaultAddress,
        'addressbook' => $addressbook,
        'has_default_address' => !empty($defaultAddress),
        'addressbook_items' => count($addressbook['items'] ?? []),
        'has_billing_address' => false,
        'has_shipping_address' => false
    ];
    
    // Check for billing and shipping addresses
    if (isset($addressbook['items']) && is_array($addressbook['items'])) {
        foreach ($addressbook['items'] as $item) {
            if ($item['defaultBilling'] ?? false) {
                $response['netsuite_service_test']['has_billing_address'] = true;
            }
            if ($item['defaultShipping'] ?? false) {
                $response['netsuite_service_test']['has_shipping_address'] = true;
            }
        }
    }
    
    if (!empty($defaultAddress)) {
        $response['steps'][] = "✅ NetSuiteService creates defaultAddress string";
    } else {
        $response['steps'][] = "❌ NetSuiteService failed to create defaultAddress";
    }
    
    if ($response['netsuite_service_test']['addressbook_items'] > 0) {
        $response['steps'][] = "✅ NetSuiteService creates addressbook with {$response['netsuite_service_test']['addressbook_items']} items";
    } else {
        $response['steps'][] = "❌ NetSuiteService failed to create addressbook items";
    }
    
    if ($response['netsuite_service_test']['has_billing_address']) {
        $response['steps'][] = "✅ NetSuiteService includes billing address";
    } else {
        $response['steps'][] = "❌ NetSuiteService missing billing address";
    }
    
    if ($response['netsuite_service_test']['has_shipping_address']) {
        $response['steps'][] = "✅ NetSuiteService includes shipping address";
    } else {
        $response['steps'][] = "❌ NetSuiteService missing shipping address";
    }
    
    // Step 4: Test the complete flow
    $response['steps'][] = 'Step 4: Testing complete customer creation flow...';
    
    // Simulate the complete customer creation
    $testNetsuiteCustomer = [];
    $addAddressesMethod = $netsuiteReflection->getMethod('addCustomerAddresses');
    $addAddressesMethod->setAccessible(true);
    $addAddressesMethod->invoke($netsuiteService, $testNetsuiteCustomer, $extractedCustomerInfo);
    
    $response['complete_flow_test'] = [
        'input_customer_data' => $extractedCustomerInfo,
        'final_netsuite_customer' => $testNetsuiteCustomer,
        'has_default_address' => isset($testNetsuiteCustomer['defaultAddress']),
        'has_addressbook' => isset($testNetsuiteCustomer['addressbook']),
        'addressbook_items' => count($testNetsuiteCustomer['addressbook']['items'] ?? [])
    ];
    
    if (isset($testNetsuiteCustomer['defaultAddress'])) {
        $response['steps'][] = "✅ Complete flow creates defaultAddress";
    } else {
        $response['steps'][] = "❌ Complete flow missing defaultAddress";
    }
    
    if (isset($testNetsuiteCustomer['addressbook'])) {
        $response['steps'][] = "✅ Complete flow creates addressbook";
    } else {
        $response['steps'][] = "❌ Complete flow missing addressbook";
    }
    
    // Step 5: Validation
    $response['steps'][] = 'Step 5: Validating the fix...';
    
    $validations = [
        'customer_model_fixed' => [
            'description' => 'Customer model toNetSuiteFormat includes billing fields',
            'correct' => $response['customer_model_test']['has_billing_fields']
        ],
        'order_processing_fixed' => [
            'description' => 'OrderProcessingService extractCustomerInfo includes billing fields',
            'correct' => $response['order_processing_test']['has_billing_fields']
        ],
        'netsuite_service_works' => [
            'description' => 'NetSuiteService address methods work correctly',
            'correct' => $response['netsuite_service_test']['has_default_address'] && 
                        $response['netsuite_service_test']['addressbook_items'] > 0
        ],
        'complete_flow_works' => [
            'description' => 'Complete customer creation flow includes addresses',
            'correct' => $response['complete_flow_test']['has_default_address'] && 
                        $response['complete_flow_test']['has_addressbook']
        ],
        'addressbook_populated' => [
            'description' => 'Addressbook is properly populated with items',
            'correct' => $response['complete_flow_test']['addressbook_items'] > 0
        ]
    ];
    
    $response['validations'] = $validations;
    
    $allValid = array_reduce($validations, function($carry, $validation) {
        return $carry && $validation['correct'];
    }, true);
    
    if ($allValid) {
        $response['steps'][] = "🎉 All validations PASSED - Address creation fix is working!";
        $response['validation_result'] = 'success';
        $response['overall_status'] = 'SUCCESS - Customer address creation is now working correctly';
    } else {
        $response['steps'][] = "⚠️ Some validations FAILED - Fix needs more work";
        $response['validation_result'] = 'failed';
        $response['overall_status'] = 'FAILED - Address creation fix has issues';
    }
    
    // Summary
    $response['steps'][] = '📋 ADDRESS CREATION FIX SUMMARY:';
    $response['steps'][] = "• Customer Model: " . ($response['customer_model_test']['has_billing_fields'] ? "FIXED ✅" : "BROKEN ❌");
    $response['steps'][] = "• OrderProcessingService: " . ($response['order_processing_test']['has_billing_fields'] ? "FIXED ✅" : "BROKEN ❌");
    $response['steps'][] = "• NetSuiteService: " . ($response['netsuite_service_test']['has_default_address'] ? "WORKING ✅" : "BROKEN ❌");
    $response['steps'][] = "• Complete Flow: " . ($response['complete_flow_test']['has_addressbook'] ? "WORKING ✅" : "BROKEN ❌");
    $response['steps'][] = "• Addressbook Population: " . ($response['complete_flow_test']['addressbook_items'] > 0 ? "WORKING ✅" : "BROKEN ❌");
    
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