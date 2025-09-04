<?php
/**
 * Debug Fetch Orders Issue
 * 
 * This script simulates the fetch_orders action to identify the JSON issue
 */

require_once __DIR__ . '/vendor/autoload.php';

use Laguna\Integration\Services\ThreeDCartService;
use Laguna\Integration\Services\NetSuiteService;
use Laguna\Integration\Utils\Logger;

echo "🔍 Debugging Fetch Orders Issue\n";
echo "===============================\n\n";

// Set up error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load configuration
$config = require __DIR__ . '/config/config.php';

// Set timezone
date_default_timezone_set($config['app']['timezone']);

// Initialize services
$logger = Logger::getInstance();

echo "1. Initializing Services...\n";
echo str_repeat('-', 40) . "\n";

try {
    $threeDCartService = new ThreeDCartService();
    echo "✅ 3DCart service initialized\n";
    
    $netSuiteService = new NetSuiteService();
    echo "✅ NetSuite service initialized\n";
    
} catch (Exception $e) {
    echo "❌ Service initialization failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n2. Testing 3DCart Connection...\n";
echo str_repeat('-', 40) . "\n";

try {
    // Test with a simple date range (last 7 days)
    $endDate = date('Y-m-d');
    $startDate = date('Y-m-d', strtotime('-7 days'));
    
    echo "📅 Date Range: $startDate to $endDate\n";
    
    echo "🔄 Fetching orders from 3DCart...\n";
    $orders = $threeDCartService->getOrdersByDateRange($startDate, $endDate, '');
    
    echo "✅ Successfully fetched " . count($orders) . " orders\n";
    
    if (count($orders) > 0) {
        $firstOrder = $orders[0];
        echo "📋 First Order Sample:\n";
        echo "   Order ID: " . ($firstOrder['OrderID'] ?? 'N/A') . "\n";
        echo "   Date: " . ($firstOrder['OrderDate'] ?? 'N/A') . "\n";
        echo "   Customer: " . ($firstOrder['BillingFirstName'] ?? '') . " " . ($firstOrder['BillingLastName'] ?? '') . "\n";
        echo "   Total: $" . number_format($firstOrder['OrderAmount'] ?? 0, 2) . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ 3DCart fetch failed: " . $e->getMessage() . "\n";
    echo "   This could be the source of the JSON error\n";
    exit(1);
}

echo "\n3. Testing NetSuite Connection...\n";
echo str_repeat('-', 40) . "\n";

try {
    $connectionTest = $netSuiteService->testConnection();
    if ($connectionTest['success']) {
        echo "✅ NetSuite connection successful\n";
    } else {
        echo "❌ NetSuite connection failed: " . $connectionTest['message'] . "\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "❌ NetSuite connection error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n4. Testing NetSuite Sync Status Check...\n";
echo str_repeat('-', 40) . "\n";

try {
    // Extract order IDs
    $orderIds = array_map(function($order) {
        return $order['OrderID'];
    }, $orders);
    
    echo "🔄 Checking sync status for " . count($orderIds) . " orders...\n";
    
    if (count($orderIds) > 0) {
        $syncStatusMap = $netSuiteService->checkOrdersSyncStatus($orderIds);
        echo "✅ Successfully checked sync status for " . count($syncStatusMap) . " orders\n";
        
        // Show sample status
        $sampleOrderId = $orderIds[0];
        $sampleStatus = $syncStatusMap[$sampleOrderId] ?? ['synced' => false];
        echo "📋 Sample Status (Order $sampleOrderId):\n";
        echo "   Synced: " . ($sampleStatus['synced'] ? 'Yes' : 'No') . "\n";
        echo "   NetSuite ID: " . ($sampleStatus['netsuite_id'] ?? 'N/A') . "\n";
    } else {
        echo "⚠️  No orders to check status for\n";
        $syncStatusMap = [];
    }
    
} catch (Exception $e) {
    echo "❌ NetSuite sync status check failed: " . $e->getMessage() . "\n";
    echo "   This could be the source of the JSON error\n";
    exit(1);
}

echo "\n5. Simulating Full Response...\n";
echo str_repeat('-', 40) . "\n";

try {
    // Build the full response like the order-sync.php does
    $ordersWithStatus = [];
    
    foreach ($orders as $order) {
        $orderId = $order['OrderID'];
        $syncStatus = $syncStatusMap[$orderId] ?? ['synced' => false];
        
        // Extract customer name with fallback options
        $customerName = trim(($order['BillingFirstName'] ?? '') . ' ' . ($order['BillingLastName'] ?? ''));
        if (empty($customerName) || $customerName === ' ') {
            $customerName = $order['BillingCompany'] ?? 'Unknown Customer';
        }
        
        $statusId = $order['OrderStatusID'] ?? 0;
        $statusName = $threeDCartService->getOrderStatusName($statusId);
        
        $ordersWithStatus[] = [
            'order_id' => $orderId,
            'order_date' => $order['OrderDate'],
            'customer_name' => $customerName,
            'customer_company' => $order['BillingCompany'] ?? '',
            'order_total' => $order['OrderAmount'] ?? ($order['OrderTotal'] ?? 0),
            'order_status' => $statusId,
            'order_status_name' => $statusName,
            'in_netsuite' => $syncStatus['synced'],
            'netsuite_id' => $syncStatus['netsuite_id'],
            'netsuite_tranid' => $syncStatus['netsuite_tranid'],
            'netsuite_status' => $syncStatus['status'],
            'netsuite_total' => $syncStatus['total'],
            'sync_date' => $syncStatus['sync_date'],
            'can_sync' => !$syncStatus['synced'],
            'sync_error' => $syncStatus['error'] ?? null,
            'raw_data' => $order
        ];
    }
    
    $response = [
        'success' => true,
        'orders' => $ordersWithStatus,
        'total_count' => count($ordersWithStatus),
        'date_range' => "$startDate to $endDate"
    ];
    
    echo "✅ Built response with " . count($ordersWithStatus) . " orders\n";
    
    // Test JSON encoding
    $jsonResponse = json_encode($response);
    
    if ($jsonResponse === false) {
        echo "❌ JSON encoding failed: " . json_last_error_msg() . "\n";
        echo "   This is the source of the 'Unexpected end of JSON input' error\n";
        
        // Try to identify the problematic data
        echo "\n🔍 Debugging JSON encoding issue...\n";
        
        // Test encoding each order individually
        foreach ($ordersWithStatus as $index => $orderWithStatus) {
            $testJson = json_encode($orderWithStatus);
            if ($testJson === false) {
                echo "❌ Order $index failed JSON encoding: " . json_last_error_msg() . "\n";
                echo "   Order ID: " . $orderWithStatus['order_id'] . "\n";
                
                // Check for problematic fields
                foreach ($orderWithStatus as $field => $value) {
                    if (is_string($value) && !mb_check_encoding($value, 'UTF-8')) {
                        echo "   ⚠️  Field '$field' has encoding issues\n";
                    }
                }
                break;
            }
        }
        
    } else {
        echo "✅ JSON encoding successful\n";
        echo "   Response size: " . strlen($jsonResponse) . " bytes\n";
        echo "   First 200 characters: " . substr($jsonResponse, 0, 200) . "...\n";
    }
    
} catch (Exception $e) {
    echo "❌ Response building failed: " . $e->getMessage() . "\n";
}

echo "\n6. Summary...\n";
echo str_repeat('-', 40) . "\n";

echo "🎯 Diagnostic Results:\n";
echo "   3DCart Service: ✅ Working\n";
echo "   NetSuite Service: ✅ Working\n";
echo "   Order Fetching: ✅ Working\n";
echo "   Sync Status Check: ✅ Working\n";

if (isset($jsonResponse) && $jsonResponse !== false) {
    echo "   JSON Encoding: ✅ Working\n";
    echo "\n💡 The fetch_orders action should work correctly.\n";
    echo "   The 'Unexpected end of JSON input' error might be:\n";
    echo "   • A timeout issue (response cut off)\n";
    echo "   • A server configuration issue\n";
    echo "   • A frontend JavaScript issue\n";
} else {
    echo "   JSON Encoding: ❌ Failed\n";
    echo "\n💡 Found the issue! JSON encoding is failing.\n";
    echo "   This causes empty/invalid JSON responses.\n";
}

echo "\n📞 Next Steps:\n";
echo "1. If JSON encoding works here, check server timeout settings\n";
echo "2. If JSON encoding failed, fix the data encoding issues\n";
echo "3. Test the order-sync page again\n";
echo "4. Check browser network tab for actual response content\n";

echo "\n🎯 Debug completed!\n";
?>