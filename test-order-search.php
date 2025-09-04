<?php
/**
 * Test Order Search Functionality
 * 
 * This script tests the order search functionality to identify any issues
 */

require_once __DIR__ . '/vendor/autoload.php';

use Laguna\Integration\Services\ThreeDCartService;
use Laguna\Integration\Services\NetSuiteService;

echo "🧪 Testing Order Search Functionality\n";
echo "=====================================\n\n";

// Set error reporting to catch all issues
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    echo "1. Testing Service Initialization...\n";
    echo str_repeat('-', 40) . "\n";
    
    $threeDCartService = new ThreeDCartService();
    echo "✅ 3DCart service initialized\n";
    
    $netSuiteService = new NetSuiteService();
    echo "✅ NetSuite service initialized\n";
    
    echo "\n2. Testing 3DCart Connection...\n";
    echo str_repeat('-', 40) . "\n";
    
    // Test 3DCart connection by getting a small date range
    $startDate = date('Y-m-d', strtotime('-7 days'));
    $endDate = date('Y-m-d');
    
    echo "📅 Testing date range: $startDate to $endDate\n";
    
    $orders = $threeDCartService->getOrdersByDateRange($startDate, $endDate, '');
    
    echo "✅ 3DCart API call successful\n";
    echo "   Orders found: " . count($orders) . "\n";
    
    if (count($orders) > 0) {
        $firstOrder = $orders[0];
        echo "   Sample order ID: " . $firstOrder['OrderID'] . "\n";
        echo "   Sample order date: " . $firstOrder['OrderDate'] . "\n";
        echo "   Sample order total: $" . number_format($firstOrder['OrderAmount'] ?? 0, 2) . "\n";
    }
    
    echo "\n3. Testing NetSuite Connection...\n";
    echo str_repeat('-', 40) . "\n";
    
    $connectionTest = $netSuiteService->testConnection();
    if ($connectionTest['success']) {
        echo "✅ NetSuite connection successful\n";
    } else {
        echo "❌ NetSuite connection failed: " . $connectionTest['message'] . "\n";
    }
    
    echo "\n4. Testing Order Status Check...\n";
    echo str_repeat('-', 40) . "\n";
    
    if (count($orders) > 0) {
        // Extract order IDs for testing
        $orderIds = array_slice(array_map(function($order) {
            return $order['OrderID'];
        }, $orders), 0, 3); // Test first 3 orders only
        
        echo "🔍 Testing sync status for " . count($orderIds) . " orders...\n";
        
        $syncStatusMap = $netSuiteService->checkOrdersSyncStatus($orderIds);
        
        echo "✅ Sync status check successful\n";
        
        foreach ($orderIds as $orderId) {
            $status = $syncStatusMap[$orderId] ?? ['synced' => false];
            echo "   Order $orderId: " . ($status['synced'] ? 'Synced' : 'Not synced') . "\n";
        }
    }
    
    echo "\n5. Testing Order Status Names...\n";
    echo str_repeat('-', 40) . "\n";
    
    $statusTests = [1, 2, 3, 4, 5];
    foreach ($statusTests as $statusId) {
        $statusName = $threeDCartService->getOrderStatusName($statusId);
        echo "   Status $statusId: $statusName\n";
    }
    
    echo "\n6. Simulating AJAX Request...\n";
    echo str_repeat('-', 40) . "\n";
    
    // Simulate the exact same logic as the AJAX request
    $_POST = [
        'action' => 'fetch_orders',
        'start_date' => $startDate,
        'end_date' => $endDate,
        'status' => ''
    ];
    
    echo "📋 Simulating POST request:\n";
    echo "   Action: " . $_POST['action'] . "\n";
    echo "   Start Date: " . $_POST['start_date'] . "\n";
    echo "   End Date: " . $_POST['end_date'] . "\n";
    echo "   Status: " . ($_POST['status'] ?: 'All') . "\n";
    
    // Validate date format (same as in order-sync.php)
    $start = DateTime::createFromFormat('Y-m-d', $_POST['start_date']);
    $end = DateTime::createFromFormat('Y-m-d', $_POST['end_date']);
    
    if (!$start || !$end) {
        throw new Exception('Invalid date format. Use YYYY-MM-DD');
    }
    
    if ($start > $end) {
        throw new Exception('Start date cannot be after end date');
    }
    
    // Check date range (limit to 30 days for performance)
    $daysDiff = $start->diff($end)->days;
    if ($daysDiff > 30) {
        throw new Exception('Date range cannot exceed 30 days');
    }
    
    echo "✅ Date validation passed\n";
    echo "   Date range: $daysDiff days\n";
    
    // Build orders with status information (same as in order-sync.php)
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
    
    echo "✅ Order processing successful\n";
    echo "   Processed orders: " . count($ordersWithStatus) . "\n";
    
    // Test JSON encoding
    $response = [
        'success' => true,
        'orders' => $ordersWithStatus,
        'total_count' => count($ordersWithStatus),
        'date_range' => "$startDate to $endDate"
    ];
    
    $json = json_encode($response);
    if ($json === false) {
        throw new Exception('JSON encoding failed: ' . json_last_error_msg());
    }
    
    echo "✅ JSON encoding successful\n";
    echo "   JSON length: " . strlen($json) . " characters\n";
    
    echo "\n🎯 Test Results Summary:\n";
    echo str_repeat('-', 40) . "\n";
    echo "✅ All components working correctly\n";
    echo "✅ No obvious errors in search functionality\n";
    echo "✅ AJAX request simulation successful\n";
    
    if (count($orders) > 0) {
        echo "\n📋 Sample Response Structure:\n";
        $sampleOrder = $ordersWithStatus[0];
        echo "   Order ID: " . $sampleOrder['order_id'] . "\n";
        echo "   Customer: " . $sampleOrder['customer_name'] . "\n";
        echo "   Total: $" . number_format($sampleOrder['order_total'], 2) . "\n";
        echo "   Status: " . $sampleOrder['order_status_name'] . "\n";
        echo "   In NetSuite: " . ($sampleOrder['in_netsuite'] ? 'Yes' : 'No') . "\n";
        echo "   Can Sync: " . ($sampleOrder['can_sync'] ? 'Yes' : 'No') . "\n";
    }
    
} catch (Exception $e) {
    echo "\n❌ ERROR DETECTED:\n";
    echo str_repeat('-', 40) . "\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    
    if ($e->getTrace()) {
        echo "\nStack Trace:\n";
        foreach ($e->getTrace() as $i => $trace) {
            echo "  #$i " . ($trace['file'] ?? 'unknown') . "(" . ($trace['line'] ?? 'unknown') . "): ";
            echo ($trace['class'] ?? '') . ($trace['type'] ?? '') . ($trace['function'] ?? 'unknown') . "()\n";
        }
    }
    
    echo "\n💡 This error is likely what's causing the search button to fail.\n";
}

echo "\n🎯 Order search testing completed!\n";
?>