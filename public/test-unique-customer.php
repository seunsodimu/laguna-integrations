<?php
/**
 * Test Unique Customer Creation
 * 
 * This script tests the unique customer creation logic
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Models\Customer;
use Laguna\Integration\Utils\Logger;
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
    
    // Load configuration
    $config = require __DIR__ . '/../config/config.php';
    date_default_timezone_set($config['app']['timezone']);
    
    // Test order data (simulating order 1060221)
    $orderData = [
        'OrderID' => '1060221',
        'CustomerID' => 376,
        'BillingEmail' => 'joe@buffalowoodturningproducts.com',
        'BillingFirstName' => 'Joe',
        'BillingLastName' => 'Wiesnet',
        'BillingCompany' => 'Buffalo Woodturning Products',
        'BillingPhoneNumber' => '(716) 555-0123',
        'BillingAddress' => '123 Main St',
        'BillingCity' => 'Buffalo',
        'BillingState' => 'NY',
        'BillingZipCode' => '14201',
        'BillingCountry' => 'US'
    ];
    
    // Create customer from order data
    $customer = Customer::fromOrderData($orderData);
    
    // Get original NetSuite format
    $originalFormat = $customer->toNetSuiteFormat();
    
    // Simulate the unique company name logic
    $customerData = $originalFormat;
    $orderId = $orderData['OrderID'];
    $email = $customer->getEmail();
    
    // Apply the unique company name logic
    if (!empty($orderId)) {
        if (!empty($customerData['companyName'])) {
            // Company customer - make company name unique
            $originalCompanyName = $customerData['companyName'];
            $customerData['companyName'] = $orderId . ': ' . $originalCompanyName;
            
            $companyLogic = [
                'type' => 'company_customer',
                'original_company' => $originalCompanyName,
                'unique_company' => $customerData['companyName']
            ];
        } else {
            // Individual customer - add order ID as company name for uniqueness
            $customerName = trim(($customerData['firstName'] ?? '') . ' ' . ($customerData['lastName'] ?? ''));
            $customerData['companyName'] = $orderId . ': ' . $customerName;
            $customerData['isPerson'] = false; // Change to company type to use companyName
            
            $companyLogic = [
                'type' => 'individual_converted_to_company',
                'customer_name' => $customerName,
                'unique_company' => $customerData['companyName']
            ];
        }
    }
    
    $response = [
        'success' => true,
        'message' => 'Unique customer creation logic tested',
        'order_id' => $orderId,
        'customer_email' => $email,
        'original_format' => $originalFormat,
        'unique_format' => $customerData,
        'company_logic' => $companyLogic ?? null,
        'changes' => [
            'company_name_changed' => ($originalFormat['companyName'] ?? null) !== ($customerData['companyName'] ?? null),
            'is_person_changed' => ($originalFormat['isPerson'] ?? null) !== ($customerData['isPerson'] ?? null)
        ]
    ];
    
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