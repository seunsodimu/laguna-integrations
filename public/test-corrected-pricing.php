<?php
/**
 * Test Corrected Item Pricing Logic
 * 
 * This script tests the corrected logic that uses ItemUnitPrice + ItemOptionPrice
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
    
    $response['steps'][] = 'Testing corrected item pricing logic (UnitPrice + OptionPrice) with order ' . $orderId;
    
    // Step 1: Analyze 3DCart order pricing structure
    $response['steps'][] = 'Analyzing 3DCart order pricing structure...';
    
    try {
        $threeDCartOrder = $threeDCartService->getOrder($orderId);
        
        // Analyze item pricing
        $itemAnalysis = [];
        $totalItemCost = 0;
        
        if (isset($threeDCartOrder['OrderItemList']) && is_array($threeDCartOrder['OrderItemList'])) {
            foreach ($threeDCartOrder['OrderItemList'] as $index => $item) {
                $quantity = (float)($item['ItemQuantity'] ?? 0);
                $unitPrice = (float)($item['ItemUnitPrice'] ?? 0);
                $optionPrice = (float)($item['ItemOptionPrice'] ?? 0);
                $totalLinePrice = $unitPrice + $optionPrice;
                $lineTotal = $quantity * $totalLinePrice;
                $totalItemCost += $lineTotal;
                
                $itemAnalysis[] = [
                    'index' => $index,
                    'item_id' => $item['ItemID'] ?? 'N/A',
                    'description' => substr($item['ItemDescription'] ?? 'N/A', 0, 50) . '...',
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'option_price' => $optionPrice,
                    'total_line_price' => $totalLinePrice,
                    'line_total' => $lineTotal
                ];
            }
        }
        
        // Extract financial data
        $orderAmount = (float)($threeDCartOrder['OrderAmount'] ?? 0);
        $orderDiscount = (float)($threeDCartOrder['OrderDiscount'] ?? 0);
        $salesTax = (float)($threeDCartOrder['SalesTax'] ?? 0);
        $shippingCost = (float)($threeDCartOrder['ShippingCost'] ?? 0);
        
        $response['pricing_analysis'] = [
            'total_item_cost' => $totalItemCost,
            'order_amount' => $orderAmount,
            'order_discount' => $orderDiscount,
            'sales_tax' => $salesTax,
            'shipping_cost' => $shippingCost,
            'calculated_discount' => $totalItemCost - $orderAmount,
            'discount_matches' => abs(($totalItemCost - $orderAmount) - $orderDiscount) < 0.01,
            'target_subtotal' => $orderAmount - $salesTax - $shippingCost
        ];
        
        $response['item_analysis'] = $itemAnalysis;
        
        $calculatedDiscount = $totalItemCost - $orderAmount;
        $discountMatches = abs($calculatedDiscount - $orderDiscount) < 0.01;
        
        $response['steps'][] = "📊 Item cost analysis: Total items: \${$totalItemCost}, OrderAmount: \${$orderAmount}, Calculated discount: \${$calculatedDiscount}, OrderDiscount: \${$orderDiscount}";
        $response['steps'][] = $discountMatches ? "✅ Discount calculation matches!" : "⚠️ Discount calculation mismatch";
        
    } catch (Exception $e) {
        $response['steps'][] = '❌ Failed to analyze 3DCart order: ' . $e->getMessage();
        throw $e;
    }
    
    // Step 2: Test the sync with corrected pricing logic
    $response['steps'][] = 'Syncing order with corrected item pricing logic...';
    
    try {
        $syncResult = $webhookController->processOrder($orderId);
        $response['sync_result'] = $syncResult;
        
        if ($syncResult['success']) {
            $response['steps'][] = '✅ Order synced successfully with corrected pricing logic';
            $netSuiteOrderId = $syncResult['netsuite_order_id'] ?? null;
            
            // Step 3: Retrieve and verify NetSuite order
            if ($netSuiteOrderId) {
                $response['steps'][] = 'Retrieving NetSuite order to verify corrected totals...';
                sleep(2); // Wait for order to be fully created
                
                try {
                    $netSuiteOrder = $netSuiteService->getSalesOrderById($netSuiteOrderId);
                    
                    if ($netSuiteOrder) {
                        // Extract NetSuite totals
                        $nsSubtotal = (float)($netSuiteOrder['subtotal'] ?? 0);
                        $nsDiscount = (float)($netSuiteOrder['discountTotal'] ?? 0);
                        $nsTotal = (float)($netSuiteOrder['total'] ?? 0);
                        $nsTax = (float)($netSuiteOrder['taxtotal'] ?? 0);
                        
                        $response['netSuite_totals'] = [
                            'subtotal' => $nsSubtotal,
                            'discount_total' => $nsDiscount,
                            'tax_total' => $nsTax,
                            'total' => $nsTotal
                        ];
                        
                        // Verify the corrected logic worked
                        $targetSubtotal = $orderAmount - $salesTax - $shippingCost;
                        $subtotalMatch = abs($nsSubtotal - $targetSubtotal) < 0.01;
                        $totalMatch = abs($nsTotal - $orderAmount) < 0.01;
                        $discountDisplayMatch = abs($nsDiscount - $orderDiscount) < 0.01;
                        
                        $response['verification'] = [
                            'subtotal_match' => $subtotalMatch,
                            'total_match' => $totalMatch,
                            'discount_display_match' => $discountDisplayMatch,
                            'subtotal_difference' => $nsSubtotal - $targetSubtotal,
                            'total_difference' => $nsTotal - $orderAmount,
                            'discount_difference' => $nsDiscount - $orderDiscount
                        ];
                        
                        if ($subtotalMatch && $totalMatch) {
                            $response['steps'][] = '🎉 SUCCESS: Corrected pricing logic works perfectly!';
                            $response['steps'][] = "✅ NetSuite subtotal (\${$nsSubtotal}) matches 3DCart target (\${$targetSubtotal})";
                            $response['steps'][] = "✅ NetSuite total (\${$nsTotal}) matches 3DCart OrderAmount (\${$orderAmount})";
                            $response['pricing_fix_status'] = 'SUCCESS - Corrected item pricing works perfectly';
                        } else {
                            $response['steps'][] = '⚠️ PARTIAL SUCCESS: Some totals still need adjustment';
                            if (!$subtotalMatch) {
                                $response['steps'][] = "❌ Subtotal mismatch: NetSuite \${$nsSubtotal} vs Target \${$targetSubtotal}";
                            }
                            if (!$totalMatch) {
                                $response['steps'][] = "❌ Total mismatch: NetSuite \${$nsTotal} vs 3DCart \${$orderAmount}";
                            }
                            $response['pricing_fix_status'] = 'PARTIAL - Some adjustments still needed';
                        }
                        
                        if ($discountDisplayMatch) {
                            $response['steps'][] = "✅ Discount display matches: NetSuite \${$nsDiscount} = 3DCart \${$orderDiscount}";
                        } else {
                            $response['steps'][] = "ℹ️ Discount display difference: NetSuite \${$nsDiscount} vs 3DCart \${$orderDiscount} (this is for display only)";
                        }
                        
                    } else {
                        $response['steps'][] = '❌ Could not retrieve NetSuite order for verification';
                        $response['pricing_fix_status'] = 'UNKNOWN - Could not verify';
                    }
                    
                } catch (Exception $retrieveException) {
                    $response['steps'][] = '❌ Error retrieving NetSuite order: ' . $retrieveException->getMessage();
                    $response['pricing_fix_status'] = 'ERROR - Verification failed';
                }
            } else {
                $response['steps'][] = '❌ No NetSuite order ID returned';
                $response['pricing_fix_status'] = 'ERROR - No order created';
            }
            
        } else {
            $response['steps'][] = '❌ Order sync failed: ' . ($syncResult['error'] ?? 'Unknown error');
            $response['pricing_fix_status'] = 'FAILED - Sync failed';
        }
        
    } catch (Exception $syncException) {
        $response['steps'][] = '❌ Sync exception: ' . $syncException->getMessage();
        $response['pricing_fix_status'] = 'ERROR - Sync exception';
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