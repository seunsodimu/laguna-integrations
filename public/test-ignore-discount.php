<?php
/**
 * Test Ignore Discount Logic
 * 
 * This script tests the updated logic that ignores discount adjustments
 * and uses corrected item pricing (UnitPrice + OptionPrice) without adjustment items
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
    
    $response['steps'][] = 'Testing IGNORE DISCOUNT logic with corrected item pricing for order ' . $orderId;
    
    // Step 1: Analyze 3DCart order pricing structure
    $response['steps'][] = 'Analyzing 3DCart order pricing structure...';
    
    try {
        $threeDCartOrder = $threeDCartService->getOrder($orderId);
        
        // Analyze item pricing with corrected logic
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
            'target_subtotal' => $orderAmount - $salesTax - $shippingCost,
            'expected_difference' => $totalItemCost - ($orderAmount - $salesTax - $shippingCost)
        ];
        
        $response['item_analysis'] = $itemAnalysis;
        
        $calculatedDiscount = $totalItemCost - $orderAmount;
        $discountMatches = abs($calculatedDiscount - $orderDiscount) < 0.01;
        $expectedDifference = $totalItemCost - ($orderAmount - $salesTax - $shippingCost);
        
        $response['steps'][] = "📊 Item cost analysis: Total items: \${$totalItemCost}, OrderAmount: \${$orderAmount}, Calculated discount: \${$calculatedDiscount}";
        $response['steps'][] = $discountMatches ? "✅ Discount calculation matches!" : "⚠️ Discount calculation mismatch";
        $response['steps'][] = "📈 Expected NetSuite vs 3DCart difference: \${$expectedDifference} (items will be higher due to discount being built into OrderAmount)";
        
    } catch (Exception $e) {
        $response['steps'][] = '❌ Failed to analyze 3DCart order: ' . $e->getMessage();
        throw $e;
    }
    
    // Step 2: Test the sync with ignore discount logic
    $response['steps'][] = 'Syncing order with IGNORE DISCOUNT logic (no adjustment items)...';
    
    try {
        $syncResult = $webhookController->processOrder($orderId);
        $response['sync_result'] = $syncResult;
        
        if ($syncResult['success']) {
            $response['steps'][] = '✅ Order synced successfully with ignore discount logic';
            $netSuiteOrderId = $syncResult['netsuite_order_id'] ?? null;
            
            // Step 3: Retrieve and verify NetSuite order
            if ($netSuiteOrderId) {
                $response['steps'][] = 'Retrieving NetSuite order to verify ignore discount logic...';
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
                        
                        // Analyze the results with ignore discount logic
                        $itemTotal = $response['pricing_analysis']['total_item_cost'];
                        $threeDCartOrderAmount = $response['pricing_analysis']['order_amount'];
                        $threeDCartDiscount = $response['pricing_analysis']['order_discount'];
                        
                        $itemTotalMatch = abs($nsSubtotal - $itemTotal) < 0.01;
                        $noDiscountApplied = $nsDiscount == 0;
                        $totalBasedOnItems = abs($nsTotal - $itemTotal) < 0.01;
                        
                        $response['ignore_discount_analysis'] = [
                            'netsuite_uses_item_totals' => $itemTotalMatch,
                            'no_discount_applied' => $noDiscountApplied,
                            'total_based_on_items' => $totalBasedOnItems,
                            'item_total_vs_netsuite' => $nsSubtotal - $itemTotal,
                            'threeDCart_vs_netsuite_difference' => $nsTotal - $threeDCartOrderAmount,
                            'expected_difference' => $itemTotal - $threeDCartOrderAmount // This should equal the discount
                        ];
                        
                        if ($itemTotalMatch && $noDiscountApplied) {
                            $response['steps'][] = '🎉 SUCCESS: Ignore discount logic works perfectly!';
                            $response['steps'][] = "✅ NetSuite subtotal (\${$nsSubtotal}) matches calculated item total (\${$itemTotal})";
                            $response['steps'][] = "✅ No discount applied in NetSuite (as intended)";
                            $response['steps'][] = "ℹ️ NetSuite total (\${$nsTotal}) is higher than 3DCart (\${$threeDCartOrderAmount}) by \$" . number_format($nsTotal - $threeDCartOrderAmount, 2) . " (expected - discount is built into 3DCart amount)";
                            $response['ignore_discount_status'] = 'SUCCESS - Ignore discount logic works perfectly';
                        } else {
                            $response['steps'][] = '⚠️ PARTIAL SUCCESS: Some aspects need review';
                            if (!$itemTotalMatch) {
                                $response['steps'][] = "❌ Item total mismatch: NetSuite \${$nsSubtotal} vs Calculated \${$itemTotal}";
                            }
                            if (!$noDiscountApplied) {
                                $response['steps'][] = "⚠️ Unexpected discount applied: NetSuite shows \${$nsDiscount}";
                            }
                            $response['ignore_discount_status'] = 'PARTIAL - Some adjustments needed';
                        }
                        
                        // Show the memo (should be clean without discount info)
                        if (isset($netSuiteOrder['memo']) && !empty($netSuiteOrder['memo'])) {
                            $response['steps'][] = "📝 Order memo: " . $netSuiteOrder['memo'];
                        } else {
                            $response['steps'][] = "📝 Order memo: Clean (no discount info added as intended)";
                        }
                        
                    } else {
                        $response['steps'][] = '❌ Could not retrieve NetSuite order for verification';
                        $response['ignore_discount_status'] = 'UNKNOWN - Could not verify';
                    }
                    
                } catch (Exception $retrieveException) {
                    $response['steps'][] = '❌ Error retrieving NetSuite order: ' . $retrieveException->getMessage();
                    $response['ignore_discount_status'] = 'ERROR - Verification failed';
                }
            } else {
                $response['steps'][] = '❌ No NetSuite order ID returned';
                $response['ignore_discount_status'] = 'ERROR - No order created';
            }
            
        } else {
            $response['steps'][] = '❌ Order sync failed: ' . ($syncResult['error'] ?? 'Unknown error');
            $response['ignore_discount_status'] = 'FAILED - Sync failed';
        }
        
    } catch (Exception $syncException) {
        $response['steps'][] = '❌ Sync exception: ' . $syncException->getMessage();
        $response['ignore_discount_status'] = 'ERROR - Sync exception';
        $response['sync_error'] = $syncException->getMessage();
    }
    
    // Summary
    $response['steps'][] = '📋 IGNORE DISCOUNT LOGIC SUMMARY:';
    $response['steps'][] = '• Uses corrected item pricing (UnitPrice + OptionPrice)';
    $response['steps'][] = '• Does NOT add adjustment items (avoids invalid item errors)';
    $response['steps'][] = '• NetSuite total will be based on individual item costs';
    $response['steps'][] = '• 3DCart discount effect is built into OrderAmount';
    $response['steps'][] = '• Expected: NetSuite total > 3DCart total by discount amount';
    
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