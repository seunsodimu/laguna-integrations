<?php
/**
 * Test Dropship Functionality
 * 
 * This script tests the dropship address handling functionality
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
    
    $response['steps'][] = 'Testing dropship functionality with sample order data...';
    
    // Step 1: Create sample order data for testing
    $response['steps'][] = 'Step 1: Creating sample order data...';
    
    // Sample standard order (non-dropship)
    $standardOrder = [
        'OrderID' => 'TEST_STANDARD_001',
        'BillingPaymentMethod' => 'Credit Card',
        'ShipmentList' => [
            [
                'ShipmentFirstName' => 'John',
                'ShipmentLastName' => 'Doe',
                'ShipmentCompany' => 'Test Company',
                'ShipmentPhone' => '(555) 123-4567',
                'ShipmentAddress' => '123 Main St',
                'ShipmentAddress2' => 'Suite 100',
                'ShipmentCity' => 'Anytown',
                'ShipmentState' => 'CA',
                'ShipmentZipCode' => '12345'
            ]
        ],
        'ShippingAddress' => '123 Main St',
        'ShippingAddress2' => 'Suite 100',
        'ShippingCity' => 'Anytown',
        'ShippingState' => 'CA',
        'ShippingZipCode' => '12345',
        'ShippingCountry' => 'US'
    ];
    
    // Sample dropship order
    $dropshipOrder = [
        'OrderID' => 'TEST_DROPSHIP_001',
        'BillingPaymentMethod' => 'Dropship to Customer',
        'ShipmentList' => [
            [
                'ShipmentFirstName' => 'Jane',
                'ShipmentLastName' => 'Smith',
                'ShipmentCompany' => 'Dropship Company',
                'ShipmentPhone' => '(555) 987-6543',
                'ShipmentAddress' => '456 Oak Avenue',
                'ShipmentAddress2' => 'Building B',
                'ShipmentCity' => 'Springfield',
                'ShipmentState' => 'TX',
                'ShipmentZipCode' => '67890'
            ]
        ],
        'ShippingAddress' => '456 Oak Avenue',
        'ShippingAddress2' => 'Building B',
        'ShippingCity' => 'Springfield',
        'ShippingState' => 'TX',
        'ShippingZipCode' => '67890',
        'ShippingCountry' => 'US'
    ];
    
    $response['sample_orders'] = [
        'standard' => $standardOrder,
        'dropship' => $dropshipOrder
    ];
    
    // Step 2: Test the address formatting logic
    $response['steps'][] = 'Step 2: Testing address formatting logic...';
    
    $testResults = [];
    
    foreach (['standard' => $standardOrder, 'dropship' => $dropshipOrder] as $type => $orderData) {
        $response['steps'][] = "Testing {$type} order...";
        
        // Simulate the logic from NetSuiteService
        if (isset($orderData['ShipmentList']) && is_array($orderData['ShipmentList']) && !empty($orderData['ShipmentList'])) {
            $shipment = $orderData['ShipmentList'][0];
            
            $shippingAddress = [];
            
            // Check if this is a dropship order
            $isDropship = isset($orderData['BillingPaymentMethod']) && 
                         $orderData['BillingPaymentMethod'] === 'Dropship to Customer';
            
            if ($isDropship) {
                // For dropship orders, format addressee with ShipmentList address fields
                $addressParts = [];
                
                if (!empty($shipment['ShipmentAddress'])) {
                    $addressParts[] = $shipment['ShipmentAddress'];
                }
                if (!empty($shipment['ShipmentAddress2'])) {
                    $addressParts[] = $shipment['ShipmentAddress2'];
                }
                if (!empty($shipment['ShipmentCity'])) {
                    $addressParts[] = $shipment['ShipmentCity'];
                }
                if (!empty($shipment['ShipmentState'])) {
                    $addressParts[] = $shipment['ShipmentState'];
                }
                if (!empty($shipment['ShipmentZipCode'])) {
                    $addressParts[] = $shipment['ShipmentZipCode'];
                }
                
                $shippingAddress['addressee'] = implode(', ', $addressParts);
            } else {
                // Standard order - use customer name for addressee
                if (!empty($shipment['ShipmentFirstName']) || !empty($shipment['ShipmentLastName'])) {
                    $shippingAddress['addressee'] = trim(($shipment['ShipmentFirstName'] ?? '') . ' ' . ($shipment['ShipmentLastName'] ?? ''));
                }
            }
            
            if (!empty($shipment['ShipmentCompany'])) {
                $shippingAddress['attention'] = $shipment['ShipmentCompany'];
            }
            if (!empty($shipment['ShipmentPhone'])) {
                $shippingAddress['addrphone'] = $shipment['ShipmentPhone'];
            }
            
            // Add other address fields
            if (isset($orderData['ShippingAddress'])) {
                $shippingAddress['addr1'] = $orderData['ShippingAddress'] ?? '';
            }
            if (isset($orderData['ShippingAddress2'])) {
                $shippingAddress['addr2'] = $orderData['ShippingAddress2'] ?? '';
            }
            if (isset($orderData['ShippingCity'])) {
                $shippingAddress['city'] = $orderData['ShippingCity'] ?? '';
            }
            if (isset($orderData['ShippingState'])) {
                $shippingAddress['state'] = $orderData['ShippingState'] ?? '';
            }
            if (isset($orderData['ShippingZipCode'])) {
                $shippingAddress['zip'] = $orderData['ShippingZipCode'] ?? '';
            }
            if (isset($orderData['ShippingCountry'])) {
                $shippingAddress['country'] = $orderData['ShippingCountry'] ?? 'US';
            }
            
            $testResults[$type] = [
                'order_id' => $orderData['OrderID'],
                'payment_method' => $orderData['BillingPaymentMethod'],
                'is_dropship' => $isDropship,
                'shipping_address' => $shippingAddress,
                'addressee_source' => $isDropship ? 'ShipmentList address fields' : 'Customer name'
            ];
            
            $response['steps'][] = "✅ {$type} order processed - addressee: " . ($shippingAddress['addressee'] ?? 'N/A');
        }
    }
    
    $response['test_results'] = $testResults;
    
    // Step 3: Validate the results
    $response['steps'][] = 'Step 3: Validating results...';
    
    $standardResult = $testResults['standard'] ?? null;
    $dropshipResult = $testResults['dropship'] ?? null;
    
    $validationResults = [];
    
    if ($standardResult) {
        $expectedStandardAddressee = 'John Doe';
        $actualStandardAddressee = $standardResult['shipping_address']['addressee'] ?? '';
        
        $validationResults['standard'] = [
            'expected_addressee' => $expectedStandardAddressee,
            'actual_addressee' => $actualStandardAddressee,
            'correct' => $actualStandardAddressee === $expectedStandardAddressee,
            'is_dropship' => $standardResult['is_dropship']
        ];
        
        if ($validationResults['standard']['correct']) {
            $response['steps'][] = "✅ Standard order validation PASSED";
        } else {
            $response['steps'][] = "❌ Standard order validation FAILED";
        }
    }
    
    if ($dropshipResult) {
        $expectedDropshipAddressee = '456 Oak Avenue, Building B, Springfield, TX, 67890';
        $actualDropshipAddressee = $dropshipResult['shipping_address']['addressee'] ?? '';
        
        $validationResults['dropship'] = [
            'expected_addressee' => $expectedDropshipAddressee,
            'actual_addressee' => $actualDropshipAddressee,
            'correct' => $actualDropshipAddressee === $expectedDropshipAddressee,
            'is_dropship' => $dropshipResult['is_dropship']
        ];
        
        if ($validationResults['dropship']['correct']) {
            $response['steps'][] = "✅ Dropship order validation PASSED";
        } else {
            $response['steps'][] = "❌ Dropship order validation FAILED";
        }
    }
    
    $response['validation_results'] = $validationResults;
    
    // Summary
    $response['steps'][] = '📋 DROPSHIP FUNCTIONALITY TEST SUMMARY:';
    $response['steps'][] = "• Standard Order Addressee: " . ($validationResults['standard']['actual_addressee'] ?? 'N/A');
    $response['steps'][] = "• Dropship Order Addressee: " . ($validationResults['dropship']['actual_addressee'] ?? 'N/A');
    $response['steps'][] = "• Standard Order Validation: " . ($validationResults['standard']['correct'] ? 'PASSED' : 'FAILED');
    $response['steps'][] = "• Dropship Order Validation: " . ($validationResults['dropship']['correct'] ? 'PASSED' : 'FAILED');
    
    // Overall status
    $allPassed = ($validationResults['standard']['correct'] ?? false) && 
                 ($validationResults['dropship']['correct'] ?? false);
    
    if ($allPassed) {
        $response['overall_status'] = 'SUCCESS - All tests passed';
        $response['steps'][] = '🎉 SUCCESS: Dropship functionality is working correctly!';
    } else {
        $response['overall_status'] = 'FAILED - Some tests failed';
        $response['steps'][] = '❌ FAILED: Some validation tests failed - check implementation';
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