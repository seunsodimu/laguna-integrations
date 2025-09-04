<?php
/**
 * Test Real Customer Creation
 * 
 * This script actually attempts to create a test person customer to verify the fix works
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Services\NetSuiteService;
use Laguna\Integration\Services\OrderProcessingService;
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
    
    $response['steps'][] = 'Testing real customer creation flow with address fix...';
    
    // Create test order data
    $testOrderData = [
        'OrderID' => 'TEST-' . time(),
        'BillingFirstName' => 'JauntyeeF' . time(),
        'BillingLastName' => 'Elfrroee' . time(),
        'BillingCompany' => 'Jameey Company' . time(),
        'BillingEmailAddress' => 'test-' . time() . '@lagunatest.com',
        'BillingAddress' => '13 Overton RD',
        'BillingAddress2' => '',
        'BillingCity' => 'Boise',
        'BillingState' => 'ID',
        'BillingZipCode' => '83709',
        'BillingCountry' => 'US',
        'BillingPhoneNumber' => '555-123-3333',
        'ShipmentList' => [
            [
                'ShipmentCompany' => 'JamShittp Company',
                'ShipmentAddress' => '5513 Oldverton RD',
                'ShipmentAddress2' => '',
                'ShipmentCity' => 'Boise',
                'ShipmentState' => 'ID',
                'ShipmentZipCode' => '83111',
                'ShipmentCountry' => 'US'
            ]
        ]
    ];
    
    $response['test_order_data'] = $testOrderData;
    
    // Step 1: Extract customer info using OrderProcessingService
    $response['steps'][] = 'Step 1: Extracting customer info via OrderProcessingService...';
    
    $orderProcessingService = new OrderProcessingService();
    $reflection = new ReflectionClass($orderProcessingService);
    $extractMethod = $reflection->getMethod('extractCustomerInfo');
    $extractMethod->setAccessible(true);
    $extractedCustomerInfo = $extractMethod->invoke($orderProcessingService, $testOrderData);
    
    $response['extracted_customer_info'] = $extractedCustomerInfo;
    
    // Step 2: Initialize NetSuite service
    $response['steps'][] = 'Step 2: Initializing NetSuite service...';
    
    $netsuiteService = new NetSuiteService();
    $response['steps'][] = "✅ NetSuite service initialized successfully";
    
    // Step 3: Check if customer already exists
    $response['steps'][] = 'Step 3: Checking if customer already exists...';
    
    $existingCustomer = $netsuiteService->findCustomerByEmail($extractedCustomerInfo['email'], true); // Include addresses
    
    if ($existingCustomer) {
        $response['steps'][] = 'Customer already exists, using existing customer...';
        $response['existing_customer'] = $existingCustomer;
        $response['customer_creation_result'] = $existingCustomer; // Use same format as new customer creation
        $customerForTesting = $existingCustomer;
    } else {
        $response['steps'][] = 'Customer does not exist, creating new customer...';
        
        // Step 4: Create the customer
        $response['steps'][] = 'Step 4: Creating customer via NetSuiteService...';
        
        $customerCreationResult = $netsuiteService->createCustomer($extractedCustomerInfo);
        $response['customer_creation_result'] = $customerCreationResult;
        
        // Check if customer was created successfully (createCustomer returns customer data directly, not success array)
        if ($customerCreationResult && isset($customerCreationResult['id'])) {
            $response['steps'][] = 'Customer created successfully!';
            $customerForTesting = $customerCreationResult;
        } else {
            $response['steps'][] = 'Customer creation failed!';
            $response['final_result'] = 'FAILED - Customer creation failed';
            $response['status'] = 'creation_failed';
            $customerForTesting = null;
        }
    }
    
    // Step 5: Test addresses (for both existing and new customers)
    if ($customerForTesting && isset($customerForTesting['id'])) {
        $response['steps'][] = 'Step 5: Retrieving customer to verify addresses...';
        
        $customerForVerification = $netsuiteService->findCustomerByEmail($extractedCustomerInfo['email'], true); // Include addresses
        $response['customer_verification'] = $customerForVerification;
        
        if ($customerForVerification) {
            $response['address_verification'] = [
                'has_default_address' => !empty($customerForVerification['defaultAddress']),
                'default_address_value' => $customerForVerification['defaultAddress'] ?? 'MISSING',
                'has_addressbook' => !empty($customerForVerification['addressbook']),
                'addressbook_items_count' => count($customerForVerification['addressbook']['items'] ?? []),
                'addressbook_items' => $customerForVerification['addressbook']['items'] ?? []
            ];
            
            // Check if addresses match expected format
            $expectedDefaultAddress = "13 Overton RD, Boise, ID, 83709";
            $actualDefaultAddress = $customerForVerification['defaultAddress'] ?? '';
            
            $response['address_comparison'] = [
                'expected_default_address' => $expectedDefaultAddress,
                'actual_default_address' => $actualDefaultAddress,
                'default_address_matches' => ($actualDefaultAddress === $expectedDefaultAddress),
                'expected_addressbook_items' => 2,
                'actual_addressbook_items' => count($customerForVerification['addressbook']['items'] ?? []),
                'addressbook_count_matches' => (count($customerForVerification['addressbook']['items'] ?? []) === 2)
            ];
            
            if ($response['address_comparison']['default_address_matches'] && 
                $response['address_comparison']['addressbook_count_matches']) {
                $response['final_result'] = 'SUCCESS - Addresses populated correctly!';
                $response['status'] = 'success';
            } else {
                $response['final_result'] = 'PARTIAL SUCCESS - Customer found but address issues detected';
                $response['status'] = 'partial_success';
            }
        } else {
            $response['final_result'] = 'ERROR - Could not retrieve customer for verification';
            $response['status'] = 'verification_failed';
        }
    }
    
    ob_clean();
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => true
    ]);
}
?>