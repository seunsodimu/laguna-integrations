<?php
/**
 * Test Total Adjustment Logic
 * 
 * This script tests the new total adjustment logic that forces NetSuite totals to match 3DCart
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Controllers\WebhookController;
use Laguna\Integration\Services\NetSuiteService;
use Laguna\Integration\Services\ThreeDCartService;
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
    $threeDCartService = new ThreeDCartService();
    
    // Test with real order 1140673
    $orderId = '1140673';
    
    $response = [
        'success' => true,
        'order_id' => $orderId,
        'steps' => []
    ];
    
    $response['steps'][] = 'Testing total adjustment logic with order ' . $orderId;
    
    // Step 1: Get 3DCart order data
    $response['steps'][] = 'Retrieving 3DCart order data...';
    
    try {
        $threeDCartOrder = $threeDCartService->getOrder($orderId);
        $response['threeDCart_order'] = $threeDCartOrder;
        
        // Extract key financial data
        $orderAmount = (float)($threeDCartOrder['OrderAmount'] ?? $threeDCartOrder['OrderTotal'] ?? 0);
        $discountAmount = (float)($threeDCartOrder['OrderDiscount'] ?? 0);
        $taxAmount = (float)($threeDCartOrder['SalesTax'] ?? 0);
        $shippingCost = (float)($threeDCartOrder['ShippingCost'] ?? 0);
        $targetSubtotal = $orderAmount - $taxAmount - $shippingCost;
        
        $response['threeDCart_financials'] = [
            'order_amount' => $orderAmount,
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
            'shipping_cost' => $shippingCost,
            'target_subtotal' => $targetSubtotal
        ];
        
        $response['steps'][] = "✅ 3DCart order retrieved - Amount: \$$orderAmount, Discount: \$$discountAmount, Target Subtotal: \$$targetSubtotal";
        
    } catch (Exception $e) {
        $response['steps'][] = '❌ Failed to retrieve 3DCart order: ' . $e->getMessage();
        throw $e;
    }
    
    // Step 2: Calculate what individual items would total without adjustment
    $response['steps'][] = 'Calculating individual item totals...';
    
    $itemTotal = 0;
    if (isset($threeDCartOrder['OrderItemList']) && is_array($threeDCartOrder['OrderItemList'])) {
        foreach ($threeDCartOrder['OrderItemList'] as $item) {
            $quantity = (float)($item['ItemQuantity'] ?? 0);
            $unitPrice = (float)($item['ItemUnitPrice'] ?? $item['ItemPrice'] ?? 0);
            $itemSubtotal = $quantity * $unitPrice;
            $itemTotal += $itemSubtotal;
        }
    }
    
    $adjustmentNeeded = $targetSubtotal - $itemTotal;
    
    $response['item_calculation'] = [
        'individual_item_total' => $itemTotal,
        'target_subtotal' => $targetSubtotal,
        'adjustment_needed' => $adjustmentNeeded,
        'adjustment_percentage' => $itemTotal > 0 ? ($adjustmentNeeded / $itemTotal) * 100 : 0
    ];
    
    $response['steps'][] = "📊 Item total: \$$itemTotal, Adjustment needed: \$$adjustmentNeeded (" . number_format(($itemTotal > 0 ? ($adjustmentNeeded / $itemTotal) * 100 : 0), 2) . "%)";
    
    // Step 3: Test the sync with adjustment logic
    $response['steps'][] = 'Syncing order with total adjustment logic...';
    
    try {
        $syncResult = $webhookController->processOrder($orderId);
        $response['sync_result'] = $syncResult;
        
        if ($syncResult['success']) {
            $response['steps'][] = '✅ Order synced successfully with adjustment logic';
            $netSuiteOrderId = $syncResult['netsuite_order_id'] ?? null;
            
            // Step 4: Retrieve and verify NetSuite order
            if ($netSuiteOrderId) {
                $response['steps'][] = 'Retrieving NetSuite order to verify totals...';
                sleep(2); // Wait for order to be fully created
                
                try {
                    $netSuiteOrder = $netSuiteService->getSalesOrderById($netSuiteOrderId);
                    
                    if ($netSuiteOrder) {
                        $response['netSuite_order'] = $netSuiteOrder;
                        
                        // Extract NetSuite totals
                        $nsSubtotal = (float)($netSuiteOrder['subtotal'] ?? 0);
                        $nsDiscount = (float)($netSuiteOrder['discountTotal'] ?? 0);
                        $nsTotal = (float)($netSuiteOrder['total'] ?? 0);
                        
                        $response['netSuite_financials'] = [
                            'subtotal' => $nsSubtotal,
                            'discount_total' => $nsDiscount,
                            'total' => $nsTotal
                        ];
                        
                        // Verify the adjustment worked
                        $subtotalMatch = abs($nsSubtotal - $targetSubtotal) < 0.01;
                        $discountMatch = abs($nsDiscount - $discountAmount) < 0.01;
                        $totalMatch = abs($nsTotal - $orderAmount) < 0.01;
                        
                        $response['verification'] = [
                            'subtotal_match' => $subtotalMatch,
                            'discount_match' => $discountMatch,
                            'total_match' => $totalMatch,
                            'subtotal_difference' => $nsSubtotal - $targetSubtotal,
                            'discount_difference' => $nsDiscount - $discountAmount,
                            'total_difference' => $nsTotal - $orderAmount
                        ];
                        
                        if ($subtotalMatch && $discountMatch && $totalMatch) {
                            $response['steps'][] = '🎉 SUCCESS: All totals match perfectly!';
                            $response['adjustment_status'] = 'SUCCESS - Total adjustment logic is working';
                        } else {
                            $response['steps'][] = '⚠️ PARTIAL SUCCESS: Some totals still don\'t match';
                            $response['adjustment_status'] = 'PARTIAL - Some adjustments needed';
                            
                            if (!$subtotalMatch) {
                                $response['steps'][] = "❌ Subtotal mismatch: NetSuite \$$nsSubtotal vs Target \$$targetSubtotal";
                            }
                            if (!$discountMatch) {
                                $response['steps'][] = "❌ Discount mismatch: NetSuite \$$nsDiscount vs 3DCart \$$discountAmount";
                            }
                            if (!$totalMatch) {
                                $response['steps'][] = "❌ Total mismatch: NetSuite \$$nsTotal vs 3DCart \$$orderAmount";
                            }
                        }
                        
                        $response['steps'][] = "📊 Final comparison: NS Subtotal: \$$nsSubtotal, NS Discount: \$$nsDiscount, NS Total: \$$nsTotal";
                        
                    } else {
                        $response['steps'][] = '❌ Could not retrieve NetSuite order for verification';
                        $response['adjustment_status'] = 'UNKNOWN - Could not verify';
                    }
                    
                } catch (Exception $retrieveException) {
                    $response['steps'][] = '❌ Error retrieving NetSuite order: ' . $retrieveException->getMessage();
                    $response['adjustment_status'] = 'ERROR - Verification failed';
                }
            } else {
                $response['steps'][] = '❌ No NetSuite order ID returned';
                $response['adjustment_status'] = 'ERROR - No order created';
            }
            
        } else {
            $response['steps'][] = '❌ Order sync failed: ' . ($syncResult['error'] ?? 'Unknown error');
            $response['adjustment_status'] = 'FAILED - Sync failed';
        }
        
    } catch (Exception $syncException) {
        $response['steps'][] = '❌ Sync exception: ' . $syncException->getMessage();
        $response['adjustment_status'] = 'ERROR - Sync exception';
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