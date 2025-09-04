<?php
/**
 * Test ensurePersonCustomer method with address creation
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Services\NetSuiteService;
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
    
    $response['steps'][] = 'Testing ensurePersonCustomer method with address creation...';
    
    // Step 1: Initialize NetSuite service
    $response['steps'][] = 'Step 1: Initializing NetSuite service...';
    
    $netsuiteService = new NetSuiteService();
    $response['steps'][] = "✅ NetSuite service initialized successfully";
    
    // Step 2: Find a company customer to test with
    $response['steps'][] = 'Step 2: Finding a company customer to test with...';
    
    $companyQuery = "SELECT id, firstName, lastName, email, companyName, phone, isperson FROM customer WHERE isperson = 'F' LIMIT 1";
    $companyResult = $netsuiteService->executeSuiteQLQuery($companyQuery);
    
    if (empty($companyResult['items'])) {
        throw new Exception("No company customers found to test with");
    }
    
    $companyCustomer = $companyResult['items'][0];
    $response['company_customer'] = $companyCustomer;
    $response['steps'][] = "✅ Found company customer: ID {$companyCustomer['id']}, Company: " . ($companyCustomer['companyName'] ?? 'N/A');
    
    // Step 3: Create mock order data with address information
    $response['steps'][] = 'Step 3: Creating mock order data with address information...';
    
    $timestamp = time();
    $mockOrderData = [
        'OrderID' => 'TEST_ENSURE_' . $timestamp,
        'BillingFirstName' => 'TestEnsure',
        'BillingLastName' => 'Person' . $timestamp,
        'BillingCompany' => 'Test Ensure Company ' . $timestamp,
        'BillingEmailAddress' => 'testensure.' . $timestamp . '@example.com',
        'BillingAddress' => '123 Test Ensure Street',
        'BillingAddress2' => 'Suite 456',
        'BillingCity' => 'Test City',
        'BillingState' => 'CA',
        'BillingZipCode' => '90210',
        'BillingCountry' => 'US',
        'BillingPhoneNumber' => '555-ENSURE-' . substr($timestamp, -4),
        'ShipmentList' => [
            [
                'ShipmentCompany' => 'Test Shipping Company ' . $timestamp,
                'ShipmentAddress' => '789 Shipping Lane',
                'ShipmentAddress2' => '',
                'ShipmentCity' => 'Ship City',
                'ShipmentState' => 'NY',
                'ShipmentZipCode' => '10001',
                'ShipmentCountry' => 'US'
            ]
        ]
    ];
    
    $response['mock_order_data'] = $mockOrderData;
    $response['steps'][] = "✅ Mock order data created with billing and shipping addresses";
    
    // Step 4: Test ensurePersonCustomer method
    $response['steps'][] = 'Step 4: Testing ensurePersonCustomer method...';
    
    $startTime = microtime(true);
    $personCustomer = $netsuiteService->ensurePersonCustomer($companyCustomer, $mockOrderData);
    $duration = (microtime(true) - $startTime) * 1000;
    
    $response['person_customer_result'] = $personCustomer;
    $response['execution_time_ms'] = $duration;
    
    if ($personCustomer && isset($personCustomer['id'])) {
        $response['steps'][] = "✅ ensurePersonCustomer completed successfully";
        $response['steps'][] = "   Person Customer ID: {$personCustomer['id']}";
        $response['steps'][] = "   Execution time: " . number_format($duration, 2) . "ms";
        
        // Check if it's a new person customer or existing one
        if ($personCustomer['id'] != $companyCustomer['id']) {
            $response['steps'][] = "✅ Created/found person customer (different from company)";
            $response['created_new_person'] = true;
            
            // Step 5: Verify the person customer has addresses
            $response['steps'][] = 'Step 5: Verifying person customer addresses...';
            
            $fullPersonCustomer = $netsuiteService->findCustomerByEmail($personCustomer['email'], true);
            $response['full_person_customer'] = $fullPersonCustomer;
            
            if ($fullPersonCustomer) {
                $response['address_verification'] = [
                    'has_default_address' => !empty($fullPersonCustomer['defaultAddress']),
                    'default_address_value' => $fullPersonCustomer['defaultAddress'] ?? 'MISSING',
                    'has_addressbook' => !empty($fullPersonCustomer['addressbook']),
                    'addressbook_items_count' => count($fullPersonCustomer['addressbook']['items'] ?? []),
                    'addressbook_items' => $fullPersonCustomer['addressbook']['items'] ?? []
                ];
                
                // Check if addresses match expected format
                $expectedDefaultAddress = "123 Test Ensure Street, Test City, CA, 90210";
                $actualDefaultAddress = $fullPersonCustomer['defaultAddress'] ?? '';
                
                $response['address_comparison'] = [
                    'expected_default_address' => $expectedDefaultAddress,
                    'actual_default_address' => $actualDefaultAddress,
                    'default_address_matches' => ($actualDefaultAddress === $expectedDefaultAddress),
                    'expected_addressbook_items' => 2,
                    'actual_addressbook_items' => count($fullPersonCustomer['addressbook']['items'] ?? []),
                    'addressbook_count_matches' => (count($fullPersonCustomer['addressbook']['items'] ?? []) === 2)
                ];
                
                if ($response['address_comparison']['default_address_matches'] && 
                    $response['address_comparison']['addressbook_count_matches']) {
                    $response['final_result'] = 'SUCCESS - ensurePersonCustomer created person with addresses!';
                    $response['status'] = 'success';
                } else {
                    $response['final_result'] = 'PARTIAL SUCCESS - Person created but address issues found';
                    $response['status'] = 'partial_success';
                }
            } else {
                $response['final_result'] = 'ERROR - Could not retrieve person customer for verification';
                $response['status'] = 'verification_failed';
            }
        } else {
            $response['steps'][] = "⚠️ Returned original company customer (no person customer needed)";
            $response['created_new_person'] = false;
            $response['final_result'] = 'INFO - Company customer was already suitable (no person customer created)';
            $response['status'] = 'no_action_needed';
        }
    } else {
        $response['steps'][] = "❌ ensurePersonCustomer failed";
        $response['final_result'] = 'FAILED - ensurePersonCustomer method failed';
        $response['status'] = 'failed';
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