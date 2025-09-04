<?php
/**
 * Test Discount Sync for Order 1140673
 * 
 * This script tests the complete discount sync process:
 * 1. Pull order from 3DCart
 * 2. Sync to NetSuite
 * 3. Retrieve NetSuite order
 * 4. Compare amounts and identify discrepancies
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Controllers\WebhookController;
use Laguna\Integration\Services\ThreeDCartService;
use Laguna\Integration\Services\NetSuiteService;
use Laguna\Integration\Utils\Logger;
use Laguna\Integration\Middleware\AuthMiddleware;

// Start output buffering
ob_start();

// Set JSON content type
header('Content-Type: application/json');

// Log file
$logFile = __DIR__ . '/../logs/test-discount-sync.log';

function debugLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

try {
    debugLog("=== DISCOUNT SYNC TEST START ===");
    
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
    $threeDCartService = new ThreeDCartService();
    $netSuiteService = new NetSuiteService();
    $webhookController = new WebhookController();
    $logger = Logger::getInstance();
    
    debugLog("Services initialized");
    
    // Test order ID
    $orderId = '1140673';
    debugLog("Testing discount sync for order ID: $orderId");
    
    $response = [
        'success' => true,
        'order_id' => $orderId,
        'steps' => [],
        'comparison' => [],
        'discrepancies' => []
    ];
    
    // Step 1: Pull order from 3DCart
    debugLog("Step 1: Pulling order from 3DCart");
    $response['steps'][] = 'Pulling order from 3DCart...';
    
    try {
        $threeDCartOrder = $threeDCartService->getOrder($orderId);
        debugLog("3DCart order retrieved successfully");
        
        $response['steps'][] = '✅ 3DCart order retrieved successfully';
        $response['threeDCart_order'] = $threeDCartOrder;
        
        // Extract key amounts from 3DCart order
        $threeDCartAmounts = [
            'subtotal' => floatval($threeDCartOrder['OrderAmount'] ?? 0),
            'discount_amount' => floatval($threeDCartOrder['OrderDiscount'] ?? 0),
            'tax_amount' => floatval($threeDCartOrder['SalesTax'] ?? 0),
            'shipping_amount' => floatval($threeDCartOrder['ShippingAmount'] ?? 0),
            'total' => floatval($threeDCartOrder['OrderTotal'] ?? 0),
            'items' => []
        ];
        
        // Extract item details
        if (isset($threeDCartOrder['OrderItemList']) && is_array($threeDCartOrder['OrderItemList'])) {
            foreach ($threeDCartOrder['OrderItemList'] as $item) {
                $threeDCartAmounts['items'][] = [
                    'sku' => $item['CatalogID'] ?? '',
                    'quantity' => intval($item['ItemQuantity'] ?? 0),
                    'unit_price' => floatval($item['ItemUnitPrice'] ?? 0),
                    'total_price' => floatval($item['ItemPrice'] ?? 0),
                    'discount' => floatval($item['ItemDiscount'] ?? 0)
                ];
            }
        }
        
        $response['threeDCart_amounts'] = $threeDCartAmounts;
        debugLog("3DCart amounts extracted: " . json_encode($threeDCartAmounts));
        
        // Highlight the discount amount for this test
        $response['steps'][] = "✅ 3DCart order has discount of \${$threeDCartAmounts['discount_amount']} - this should be transferred to NetSuite";
        
    } catch (Exception $e) {
        debugLog("Failed to retrieve 3DCart order: " . $e->getMessage());
        $response['steps'][] = '❌ Failed to retrieve 3DCart order: ' . $e->getMessage();
        throw $e;
    }
    
    // Step 2: Sync order to NetSuite (using real 3DCart data)
    debugLog("Step 2: Syncing order to NetSuite using real 3DCart data");
    $response['steps'][] = 'Syncing order to NetSuite using real 3DCart data...';
    
    try {
        // Use the processOrder method which will fetch complete data from 3DCart API
        // This avoids validation issues with incomplete webhook data
        $syncResult = $webhookController->processOrder($orderId);
        debugLog("Order sync result: " . json_encode($syncResult));
        
        if ($syncResult['success']) {
            $response['steps'][] = '✅ Order synced to NetSuite successfully';
            $response['sync_result'] = $syncResult;
            $netSuiteOrderId = $syncResult['netsuite_order_id'] ?? null;
            
            // Add a brief pause to ensure the order is fully created in NetSuite
            $response['steps'][] = 'Waiting 2 seconds for NetSuite order to be fully created...';
            sleep(2);
        } else {
            throw new Exception('Sync failed: ' . ($syncResult['error'] ?? 'Unknown error'));
        }
        
    } catch (Exception $e) {
        debugLog("Failed to sync order to NetSuite: " . $e->getMessage());
        $response['steps'][] = '❌ Failed to sync order to NetSuite: ' . $e->getMessage();
        throw $e;
    }
    
    // Step 3: Retrieve NetSuite order details
    debugLog("Step 3: Retrieving NetSuite order details");
    $response['steps'][] = 'Retrieving NetSuite order details...';
    
    try {
        $netSuiteOrder = null;
        
        // First try to get by external ID
        debugLog("Trying to retrieve by external ID: 3DCART_$orderId");
        $netSuiteOrder = $netSuiteService->getSalesOrderByExternalId('3DCART_' . $orderId);
        
        if (!$netSuiteOrder && isset($netSuiteOrderId)) {
            // If not found by external ID, try by internal ID using SuiteQL
            debugLog("Trying to retrieve by internal ID: $netSuiteOrderId");
            $query = "SELECT id, tranid, entity, total, subtotal, taxtotal, shippingcost, discounttotal, externalid FROM salesorder WHERE id = '$netSuiteOrderId'";
            $queryResult = $netSuiteService->executeSuiteQLQuery($query);
            
            if (isset($queryResult['items']) && count($queryResult['items']) > 0) {
                $netSuiteOrder = $queryResult['items'][0];
                debugLog("Found order by internal ID via SuiteQL");
            }
        }
        
        // If still not found, search by external ID using SuiteQL
        if (!$netSuiteOrder) {
            debugLog("Trying SuiteQL search by external ID");
            $query = "SELECT id, tranid, entity, total, subtotal, taxtotal, shippingcost, discounttotal, externalid FROM salesorder WHERE externalid = '3DCART_$orderId'";
            $queryResult = $netSuiteService->executeSuiteQLQuery($query);
            
            if (isset($queryResult['items']) && count($queryResult['items']) > 0) {
                $netSuiteOrder = $queryResult['items'][0];
                debugLog("Found order by external ID via SuiteQL");
            }
        }
        
        // Last resort: search for recent orders with similar external ID pattern
        if (!$netSuiteOrder) {
            debugLog("Last resort: searching for recent orders with 3DCART external ID pattern");
            $query = "SELECT id, tranid, entity, total, subtotal, taxtotal, shippingcost, discounttotal, externalid, datecreated FROM salesorder WHERE externalid LIKE '3DCART_%' ORDER BY datecreated DESC LIMIT 10";
            $queryResult = $netSuiteService->executeSuiteQLQuery($query);
            
            if (isset($queryResult['items']) && count($queryResult['items']) > 0) {
                debugLog("Found " . count($queryResult['items']) . " recent 3DCart orders");
                
                // Look for our specific order in the recent results
                foreach ($queryResult['items'] as $recentOrder) {
                    if ($recentOrder['externalid'] === '3DCART_' . $orderId) {
                        $netSuiteOrder = $recentOrder;
                        debugLog("Found order in recent orders list");
                        break;
                    }
                }
                
                // If not found exactly, log what we did find for debugging
                if (!$netSuiteOrder) {
                    $recentExternalIds = array_column($queryResult['items'], 'externalid');
                    debugLog("Recent external IDs found: " . implode(', ', $recentExternalIds));
                }
            }
        }
        
        // If we found the order, get more detailed information using REST API
        if ($netSuiteOrder && isset($netSuiteOrder['id'])) {
            debugLog("Getting detailed order information via REST API for ID: " . $netSuiteOrder['id']);
            
            try {
                // Use the new method to get full order details
                $detailedOrderData = $netSuiteService->getSalesOrderById($netSuiteOrder['id']);
                
                if ($detailedOrderData) {
                    $netSuiteOrder = array_merge($netSuiteOrder, $detailedOrderData);
                    debugLog("Enhanced order data with REST API details");
                } else {
                    debugLog("No detailed data returned from REST API");
                }
            } catch (Exception $restException) {
                debugLog("REST API call failed, using SuiteQL data only: " . $restException->getMessage());
            }
        }
        
        if ($netSuiteOrder) {
            debugLog("NetSuite order retrieved successfully");
            $response['steps'][] = '✅ NetSuite order retrieved successfully';
            $response['netSuite_order'] = $netSuiteOrder;
            
            // Log the structure for debugging
            debugLog("NetSuite order structure: " . json_encode(array_keys($netSuiteOrder)));
            
            // Extract key amounts from NetSuite order (handle different field names)
            $netSuiteAmounts = [
                'subtotal' => floatval($netSuiteOrder['subtotal'] ?? $netSuiteOrder['subTotal'] ?? 0),
                'discount_amount' => floatval($netSuiteOrder['discounttotal'] ?? $netSuiteOrder['discountTotal'] ?? $netSuiteOrder['discountAmount'] ?? 0),
                'tax_amount' => floatval($netSuiteOrder['taxtotal'] ?? $netSuiteOrder['taxTotal'] ?? $netSuiteOrder['tax'] ?? 0),
                'shipping_amount' => floatval($netSuiteOrder['shippingcost'] ?? $netSuiteOrder['shippingCost'] ?? $netSuiteOrder['shipping'] ?? 0),
                'total' => floatval($netSuiteOrder['total'] ?? 0),
                'items' => []
            ];
            
            // Get line items if available (handle different structures)
            $items = $netSuiteOrder['item'] ?? $netSuiteOrder['items'] ?? [];
            if (is_array($items)) {
                foreach ($items as $item) {
                    $netSuiteAmounts['items'][] = [
                        'sku' => $item['item']['name'] ?? $item['item'] ?? $item['sku'] ?? '',
                        'quantity' => intval($item['quantity'] ?? 0),
                        'unit_price' => floatval($item['rate'] ?? $item['unitPrice'] ?? 0),
                        'total_price' => floatval($item['amount'] ?? $item['totalPrice'] ?? 0)
                    ];
                }
            }
            
            // Log all available fields for debugging
            debugLog("Available NetSuite order fields: " . implode(', ', array_keys($netSuiteOrder)));
            debugLog("NetSuite amounts extracted: " . json_encode($netSuiteAmounts));
            
            $response['netSuite_amounts'] = $netSuiteAmounts;
            debugLog("NetSuite amounts extracted: " . json_encode($netSuiteAmounts));
            
        } else {
            throw new Exception('NetSuite order not found');
        }
        
    } catch (Exception $e) {
        debugLog("Failed to retrieve NetSuite order: " . $e->getMessage());
        $response['steps'][] = '❌ Failed to retrieve NetSuite order: ' . $e->getMessage();
        throw $e;
    }
    
    // Step 4: Compare amounts and identify discrepancies
    debugLog("Step 4: Comparing amounts and identifying discrepancies");
    $response['steps'][] = 'Comparing amounts and identifying discrepancies...';
    
    $comparison = [
        'subtotal' => [
            'threeDCart' => $threeDCartAmounts['subtotal'],
            'netSuite' => $netSuiteAmounts['subtotal'],
            'difference' => $netSuiteAmounts['subtotal'] - $threeDCartAmounts['subtotal'],
            'match' => abs($netSuiteAmounts['subtotal'] - $threeDCartAmounts['subtotal']) < 0.01
        ],
        'discount' => [
            'threeDCart' => $threeDCartAmounts['discount_amount'],
            'netSuite' => $netSuiteAmounts['discount_amount'],
            'difference' => $netSuiteAmounts['discount_amount'] - $threeDCartAmounts['discount_amount'],
            'match' => abs($netSuiteAmounts['discount_amount'] - $threeDCartAmounts['discount_amount']) < 0.01
        ],
        'tax' => [
            'threeDCart' => $threeDCartAmounts['tax_amount'],
            'netSuite' => $netSuiteAmounts['tax_amount'],
            'difference' => $netSuiteAmounts['tax_amount'] - $threeDCartAmounts['tax_amount'],
            'match' => abs($netSuiteAmounts['tax_amount'] - $threeDCartAmounts['tax_amount']) < 0.01
        ],
        'shipping' => [
            'threeDCart' => $threeDCartAmounts['shipping_amount'],
            'netSuite' => $netSuiteAmounts['shipping_amount'],
            'difference' => $netSuiteAmounts['shipping_amount'] - $threeDCartAmounts['shipping_amount'],
            'match' => abs($netSuiteAmounts['shipping_amount'] - $threeDCartAmounts['shipping_amount']) < 0.01
        ],
        'total' => [
            'threeDCart' => $threeDCartAmounts['total'],
            'netSuite' => $netSuiteAmounts['total'],
            'difference' => $netSuiteAmounts['total'] - $threeDCartAmounts['total'],
            'match' => abs($netSuiteAmounts['total'] - $threeDCartAmounts['total']) < 0.01
        ]
    ];
    
    $response['comparison'] = $comparison;
    
    // Identify discrepancies
    $discrepancies = [];
    foreach ($comparison as $field => $data) {
        if (!$data['match']) {
            $discrepancies[] = [
                'field' => $field,
                'threeDCart_value' => $data['threeDCart'],
                'netSuite_value' => $data['netSuite'],
                'difference' => $data['difference'],
                'severity' => abs($data['difference']) > 1 ? 'high' : 'low'
            ];
        }
    }
    
    $response['discrepancies'] = $discrepancies;
    $response['has_discrepancies'] = count($discrepancies) > 0;
    
    if (count($discrepancies) > 0) {
        $response['steps'][] = '⚠️ Found ' . count($discrepancies) . ' discrepancies';
        debugLog("Found discrepancies: " . json_encode($discrepancies));
        
        // Check specifically for discount discrepancy
        $discountDiscrepancy = null;
        foreach ($discrepancies as $disc) {
            if ($disc['field'] === 'discount') {
                $discountDiscrepancy = $disc;
                break;
            }
        }
        
        if ($discountDiscrepancy) {
            $response['steps'][] = "❌ DISCOUNT FIX VERIFICATION: Discount still not working - 3DCart: \${$discountDiscrepancy['threeDCart_value']}, NetSuite: \${$discountDiscrepancy['netSuite_value']}";
            $response['discount_fix_status'] = 'FAILED - Discount still not being transferred';
        } else {
            $response['steps'][] = "✅ DISCOUNT FIX VERIFICATION: Discount is working correctly!";
            $response['discount_fix_status'] = 'SUCCESS - Discount fix is working';
        }
    } else {
        $response['steps'][] = '✅ All amounts match perfectly';
        $response['steps'][] = "🎉 DISCOUNT FIX VERIFICATION: All amounts including discount match perfectly!";
        $response['discount_fix_status'] = 'SUCCESS - All amounts match perfectly';
        debugLog("All amounts match perfectly");
    }
    
    debugLog("Discount sync test completed successfully");
    
    ob_clean();
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    debugLog("Exception: " . $e->getMessage());
    
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'order_id' => $orderId ?? 'unknown',
        'steps' => $response['steps'] ?? [],
        'debug' => true
    ]);
}

debugLog("=== DISCOUNT SYNC TEST END ===");
?>