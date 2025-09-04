<?php
/**
 * Test Discount Fix
 * 
 * This script tests the discount fix by creating a new sales order with discount
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Controllers\WebhookController;
use Laguna\Integration\Services\NetSuiteService;
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
    
    // Initialize services
    $webhookController = new WebhookController();
    $netSuiteService = new NetSuiteService();
    
    // Test order ID - use a new test order ID to avoid conflicts
    $testOrderId = '1140673_TEST_' . time();
    
    $response = [
        'success' => true,
        'test_order_id' => $testOrderId,
        'steps' => []
    ];
    
    $response['steps'][] = 'Testing discount fix with order ID: ' . $testOrderId;
    
    // Create test order data with discount
    $testOrderData = [
        'OrderID' => $testOrderId,
        'CustomerID' => 376,
        'BillingEmail' => 'test-discount@example.com',
        'BillingFirstName' => 'Test',
        'BillingLastName' => 'Discount',
        'BillingCompany' => 'Test Discount Company',
        'OrderAmount' => 4928.62,
        'OrderDiscount' => 1393.34, // This is the key field
        'OrderTotal' => 3535.28, // Amount after discount
        'SalesTax' => 0,
        'ShippingCost' => 0,
        'OrderItemList' => [
            [
                'CatalogID' => '240',
                'ItemQuantity' => 1,
                'ItemUnitPrice' => 2699.00,
                'ItemPrice' => 2699.00,
                'ItemDiscount' => 0
            ],
            [
                'CatalogID' => '177',
                'ItemQuantity' => 1,
                'ItemUnitPrice' => 2999.00,
                'ItemPrice' => 2999.00,
                'ItemDiscount' => 0
            ]
        ]
    ];
    
    $response['test_order_data'] = $testOrderData;
    $response['steps'][] = 'Created test order data with $1,393.34 discount';
    
    // Test the webhook processing
    try {
        $syncResult = $webhookController->processOrderFromWebhookData($testOrderData);
        $response['sync_result'] = $syncResult;
        
        if ($syncResult['success']) {
            $response['steps'][] = '✅ Order sync completed successfully';
            $netSuiteOrderId = $syncResult['netsuite_order_id'] ?? null;
            
            // Now retrieve the created order to verify discount was applied
            if ($netSuiteOrderId) {
                $response['steps'][] = 'Retrieving created NetSuite order to verify discount...';
                
                try {
                    $createdOrder = $netSuiteService->getSalesOrderById($netSuiteOrderId);
                    
                    if ($createdOrder) {
                        $response['created_order'] = $createdOrder;
                        $response['steps'][] = '✅ Retrieved created NetSuite order';
                        
                        // Check discount application
                        $netSuiteDiscount = floatval($createdOrder['discountTotal'] ?? 0);
                        $expectedDiscount = 1393.34;
                        
                        $response['discount_verification'] = [
                            'expected_discount' => $expectedDiscount,
                            'netsuite_discount' => $netSuiteDiscount,
                            'discount_applied' => abs($netSuiteDiscount - $expectedDiscount) < 0.01,
                            'difference' => $netSuiteDiscount - $expectedDiscount
                        ];
                        
                        if (abs($netSuiteDiscount - $expectedDiscount) < 0.01) {
                            $response['steps'][] = '🎉 SUCCESS: Discount correctly applied to NetSuite order!';
                            $response['fix_status'] = 'SUCCESS - Discount fix is working';
                        } else {
                            $response['steps'][] = '❌ FAILED: Discount not correctly applied';
                            $response['fix_status'] = 'FAILED - Discount fix needs more work';
                        }
                        
                        // Check total calculation
                        $netSuiteTotal = floatval($createdOrder['total'] ?? 0);
                        $expectedTotal = 3535.28; // After discount
                        
                        $response['total_verification'] = [
                            'expected_total' => $expectedTotal,
                            'netsuite_total' => $netSuiteTotal,
                            'total_correct' => abs($netSuiteTotal - $expectedTotal) < 0.01,
                            'difference' => $netSuiteTotal - $expectedTotal
                        ];
                        
                    } else {
                        $response['steps'][] = '❌ Could not retrieve created NetSuite order';
                        $response['fix_status'] = 'UNKNOWN - Could not verify discount application';
                    }
                    
                } catch (Exception $retrieveException) {
                    $response['steps'][] = '❌ Error retrieving NetSuite order: ' . $retrieveException->getMessage();
                    $response['fix_status'] = 'ERROR - Could not verify discount application';
                }
            } else {
                $response['steps'][] = '❌ No NetSuite order ID returned from sync';
                $response['fix_status'] = 'ERROR - No order ID returned';
            }
            
        } else {
            $response['steps'][] = '❌ Order sync failed: ' . ($syncResult['error'] ?? 'Unknown error');
            $response['fix_status'] = 'FAILED - Order sync failed';
        }
        
    } catch (Exception $syncException) {
        $response['steps'][] = '❌ Sync exception: ' . $syncException->getMessage();
        $response['fix_status'] = 'ERROR - Sync exception occurred';
        $response['sync_error'] = $syncException->getMessage();
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