<?php
/**
 * Test 3DCart Order Data Structure
 * 
 * This script examines the actual structure of 3DCart order data to verify field names
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Services\ThreeDCartService;
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
    
    $response['steps'][] = 'Testing 3DCart order data structure to verify ItemID vs OrderItemID usage...';
    
    // Step 1: Initialize 3DCart service
    $response['steps'][] = 'Step 1: Initializing 3DCart service...';
    
    try {
        $threeDCartService = new ThreeDCartService();
        $response['steps'][] = "✅ 3DCart service initialized successfully";
    } catch (Exception $e) {
        $response['steps'][] = "❌ Failed to initialize 3DCart service: " . $e->getMessage();
        throw $e;
    }
    
    // Step 2: Get a recent order to examine structure
    $response['steps'][] = 'Step 2: Fetching recent orders to examine data structure...';
    
    try {
        // Get orders from the last 7 days
        $startDate = date('Y-m-d', strtotime('-7 days'));
        $endDate = date('Y-m-d');
        
        $orders = $threeDCartService->getOrdersByDateRange($startDate, $endDate, 2); // Status 2 = New
        
        if (empty($orders)) {
            $response['steps'][] = "⚠️ No recent orders found, trying with different status...";
            
            // Try with different statuses
            $statuses = [1, 3, 4, 5]; // Different order statuses
            foreach ($statuses as $status) {
                $orders = $threeDCartService->getOrdersByDateRange($startDate, $endDate, $status);
                if (!empty($orders)) {
                    $response['steps'][] = "✅ Found orders with status {$status}";
                    break;
                }
            }
        }
        
        if (empty($orders)) {
            $response['steps'][] = "⚠️ No orders found in last 7 days, trying last 30 days...";
            $startDate = date('Y-m-d', strtotime('-30 days'));
            $orders = $threeDCartService->getOrdersByDateRange($startDate, $endDate, 2);
        }
        
        if (empty($orders)) {
            throw new Exception("No orders found to examine data structure");
        }
        
        $response['orders_found'] = count($orders);
        $response['steps'][] = "✅ Found " . count($orders) . " orders to examine";
        
    } catch (Exception $e) {
        $response['steps'][] = "❌ Failed to fetch orders: " . $e->getMessage();
        throw $e;
    }
    
    // Step 3: Examine order structure
    $response['steps'][] = 'Step 3: Examining order data structure...';
    
    $sampleOrder = $orders[0]; // Take the first order
    $response['sample_order_id'] = $sampleOrder['OrderID'] ?? 'Unknown';
    
    // Check main order fields
    $orderFields = array_keys($sampleOrder);
    $response['order_fields'] = $orderFields;
    $response['steps'][] = "✅ Order has " . count($orderFields) . " main fields";
    
    // Focus on OrderItemList structure
    if (isset($sampleOrder['OrderItemList']) && is_array($sampleOrder['OrderItemList'])) {
        $response['steps'][] = "✅ Found OrderItemList with " . count($sampleOrder['OrderItemList']) . " items";
        
        $firstItem = $sampleOrder['OrderItemList'][0];
        $itemFields = array_keys($firstItem);
        
        $response['item_fields'] = $itemFields;
        $response['steps'][] = "✅ Each item has " . count($itemFields) . " fields";
        
        // Check for ItemID vs OrderItemID
        $hasItemID = in_array('ItemID', $itemFields);
        $hasOrderItemID = in_array('OrderItemID', $itemFields);
        
        $response['has_ItemID'] = $hasItemID;
        $response['has_OrderItemID'] = $hasOrderItemID;
        
        if ($hasItemID && $hasOrderItemID) {
            $response['steps'][] = "⚠️ BOTH ItemID and OrderItemID fields found!";
            $response['ItemID_value'] = $firstItem['ItemID'];
            $response['OrderItemID_value'] = $firstItem['OrderItemID'];
            $response['steps'][] = "ItemID value: " . $firstItem['ItemID'];
            $response['steps'][] = "OrderItemID value: " . $firstItem['OrderItemID'];
        } elseif ($hasItemID) {
            $response['steps'][] = "✅ Found ItemID field (correct)";
            $response['ItemID_value'] = $firstItem['ItemID'];
            $response['steps'][] = "ItemID value: " . $firstItem['ItemID'];
        } elseif ($hasOrderItemID) {
            $response['steps'][] = "❌ Found OrderItemID field but NOT ItemID";
            $response['OrderItemID_value'] = $firstItem['OrderItemID'];
            $response['steps'][] = "OrderItemID value: " . $firstItem['OrderItemID'];
        } else {
            $response['steps'][] = "❌ Neither ItemID nor OrderItemID found!";
        }
        
        // Show sample item structure (sanitized)
        $sampleItemStructure = [];
        foreach ($firstItem as $key => $value) {
            if (is_string($value) && strlen($value) > 50) {
                $sampleItemStructure[$key] = substr($value, 0, 50) . '...';
            } else {
                $sampleItemStructure[$key] = $value;
            }
        }
        
        $response['sample_item_structure'] = $sampleItemStructure;
        
    } else {
        $response['steps'][] = "❌ No OrderItemList found in order data";
    }
    
    // Step 4: Check multiple orders for consistency
    $response['steps'][] = 'Step 4: Checking multiple orders for field consistency...';
    
    $fieldConsistency = [];
    $itemFieldConsistency = [];
    
    foreach (array_slice($orders, 0, 5) as $order) { // Check first 5 orders
        // Check order fields
        foreach (array_keys($order) as $field) {
            $fieldConsistency[$field] = ($fieldConsistency[$field] ?? 0) + 1;
        }
        
        // Check item fields
        if (isset($order['OrderItemList']) && is_array($order['OrderItemList']) && !empty($order['OrderItemList'])) {
            foreach (array_keys($order['OrderItemList'][0]) as $itemField) {
                $itemFieldConsistency[$itemField] = ($itemFieldConsistency[$itemField] ?? 0) + 1;
            }
        }
    }
    
    $response['field_consistency'] = $fieldConsistency;
    $response['item_field_consistency'] = $itemFieldConsistency;
    
    $ordersChecked = min(5, count($orders));
    $response['steps'][] = "✅ Checked {$ordersChecked} orders for field consistency";
    
    // Summary
    $response['steps'][] = '📋 3DCART ORDER STRUCTURE ANALYSIS:';
    $response['steps'][] = "• Orders Examined: {$ordersChecked}";
    $response['steps'][] = "• ItemID Field Present: " . ($response['has_ItemID'] ? 'Yes' : 'No');
    $response['steps'][] = "• OrderItemID Field Present: " . ($response['has_OrderItemID'] ? 'Yes' : 'No');
    
    if (isset($response['ItemID_value'])) {
        $response['steps'][] = "• Sample ItemID Value: " . $response['ItemID_value'];
    }
    if (isset($response['OrderItemID_value'])) {
        $response['steps'][] = "• Sample OrderItemID Value: " . $response['OrderItemID_value'];
    }
    
    // Recommendation
    if ($response['has_ItemID'] && !$response['has_OrderItemID']) {
        $response['recommendation'] = 'CORRECT - Code should use ItemID field';
        $response['steps'][] = '✅ CORRECT: Code should use ItemID field (which it currently does)';
    } elseif (!$response['has_ItemID'] && $response['has_OrderItemID']) {
        $response['recommendation'] = 'NEEDS_FIX - Code should use OrderItemID field';
        $response['steps'][] = '❌ NEEDS FIX: Code should use OrderItemID field instead of ItemID';
    } elseif ($response['has_ItemID'] && $response['has_OrderItemID']) {
        $response['recommendation'] = 'VERIFY - Both fields present, need to determine which is correct';
        $response['steps'][] = '⚠️ VERIFY: Both ItemID and OrderItemID present - need to determine which contains the product SKU';
    } else {
        $response['recommendation'] = 'ERROR - No item identifier field found';
        $response['steps'][] = '❌ ERROR: No item identifier field found in order data';
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