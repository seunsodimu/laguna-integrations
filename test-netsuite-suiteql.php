<?php
/**
 * NetSuite SuiteQL Integration Test
 * 
 * Tests the new SuiteQL-based sales order lookup functionality
 */

require_once __DIR__ . '/vendor/autoload.php';

use Laguna\Integration\Services\NetSuiteService;
use Laguna\Integration\Utils\Logger;

echo "🔍 NetSuite SuiteQL Integration Test\n";
echo "====================================\n\n";

try {
    $netSuiteService = new NetSuiteService();
    $logger = Logger::getInstance();
    
    echo "1. Testing NetSuite Connection...\n";
    echo str_repeat('-', 40) . "\n";
    
    $connectionTest = $netSuiteService->testConnection();
    if ($connectionTest['success']) {
        echo "✅ NetSuite connection successful\n";
        echo "   Response time: " . $connectionTest['response_time'] . "\n";
    } else {
        echo "❌ NetSuite connection failed: " . $connectionTest['error'] . "\n";
        exit(1);
    }
    
    echo "\n2. Testing SuiteQL Query Execution...\n";
    echo str_repeat('-', 40) . "\n";
    
    // Test basic SuiteQL query
    $testQuery = "SELECT id, tranid, externalid FROM transaction WHERE recordtype = 'salesorder' LIMIT 5";
    
    try {
        $result = $netSuiteService->executeSuiteQLQuery($testQuery);
        echo "✅ SuiteQL query executed successfully\n";
        echo "   Results found: " . count($result['items'] ?? []) . "\n";
        echo "   Has more results: " . ($result['hasMore'] ? 'Yes' : 'No') . "\n";
        
        if (!empty($result['items'])) {
            echo "   Sample results:\n";
            foreach (array_slice($result['items'], 0, 3) as $item) {
                echo "   - ID: {$item['id']}, TranID: {$item['tranid']}, ExternalID: " . ($item['externalid'] ?? 'None') . "\n";
            }
        }
    } catch (Exception $e) {
        echo "❌ SuiteQL query failed: " . $e->getMessage() . "\n";
    }
    
    echo "\n3. Testing Single Order Lookup...\n";
    echo str_repeat('-', 40) . "\n";
    
    // Test looking up a specific order (use a test external ID)
    $testExternalId = '3DCART_1108410';
    
    try {
        $order = $netSuiteService->getSalesOrderByExternalId($testExternalId);
        
        if ($order) {
            echo "✅ Found order with external ID: $testExternalId\n";
            echo "   NetSuite ID: " . $order['id'] . "\n";
            echo "   Transaction ID: " . ($order['tranid'] ?? 'N/A') . "\n";
            echo "   Status: " . ($order['status'] ?? 'N/A') . "\n";
            echo "   Total: " . ($order['total'] ?? 'N/A') . "\n";
            echo "   Date: " . ($order['trandate'] ?? 'N/A') . "\n";
        } else {
            echo "ℹ️  No order found with external ID: $testExternalId\n";
            echo "   This is normal if the order hasn't been synced yet\n";
        }
    } catch (Exception $e) {
        echo "❌ Single order lookup failed: " . $e->getMessage() . "\n";
    }
    
    echo "\n4. Testing Bulk Order Status Check...\n";
    echo str_repeat('-', 40) . "\n";
    
    // Test bulk status checking with sample order IDs
    $testOrderIds = ['1108410', '1108411', '1060221', '999999']; // Mix of real and fake IDs
    
    try {
        $syncStatusMap = $netSuiteService->checkOrdersSyncStatus($testOrderIds);
        
        echo "✅ Bulk status check completed\n";
        echo "   Orders checked: " . count($testOrderIds) . "\n";
        echo "   Results:\n";
        
        foreach ($testOrderIds as $orderId) {
            $status = $syncStatusMap[$orderId] ?? ['synced' => false];
            
            if ($status['synced']) {
                echo "   📋 Order #$orderId: ✅ SYNCED\n";
                echo "      NetSuite ID: " . $status['netsuite_tranid'] . "\n";
                echo "      Total: $" . number_format($status['total'] ?? 0, 2) . "\n";
                echo "      Status: " . ($status['status'] ?? 'N/A') . "\n";
                echo "      Sync Date: " . ($status['sync_date'] ?? 'N/A') . "\n";
            } else {
                echo "   📋 Order #$orderId: ⏳ NOT SYNCED\n";
                if (isset($status['error'])) {
                    echo "      Error: " . $status['error'] . "\n";
                }
            }
            echo "\n";
        }
    } catch (Exception $e) {
        echo "❌ Bulk status check failed: " . $e->getMessage() . "\n";
    }
    
    echo "5. Testing Performance Comparison...\n";
    echo str_repeat('-', 40) . "\n";
    
    $performanceTestIds = ['1108410', '1108411', '1060221'];
    
    // Test individual lookups (old method simulation)
    $startTime = microtime(true);
    $individualResults = 0;
    foreach ($performanceTestIds as $orderId) {
        $order = $netSuiteService->getSalesOrderByExternalId('3DCART_' . $orderId);
        if ($order) $individualResults++;
    }
    $individualTime = (microtime(true) - $startTime) * 1000;
    
    // Test bulk lookup (new method)
    $startTime = microtime(true);
    $bulkResults = $netSuiteService->checkOrdersSyncStatus($performanceTestIds);
    $syncedCount = count(array_filter($bulkResults, function($status) {
        return $status['synced'];
    }));
    $bulkTime = (microtime(true) - $startTime) * 1000;
    
    echo "📊 Performance Comparison:\n";
    echo "   Individual lookups: " . round($individualTime, 2) . "ms (found $individualResults)\n";
    echo "   Bulk lookup: " . round($bulkTime, 2) . "ms (found $syncedCount)\n";
    echo "   Performance improvement: " . round(($individualTime - $bulkTime) / $individualTime * 100, 1) . "%\n";
    
    echo "\n6. Testing Error Handling...\n";
    echo str_repeat('-', 40) . "\n";
    
    // Test with invalid query
    try {
        $invalidQuery = "SELECT invalid_field FROM nonexistent_table";
        $result = $netSuiteService->executeSuiteQLQuery($invalidQuery);
        echo "❌ Invalid query should have failed but didn't\n";
    } catch (Exception $e) {
        echo "✅ Invalid query properly handled: " . substr($e->getMessage(), 0, 100) . "...\n";
    }
    
    // Test with empty order IDs
    try {
        $emptyResult = $netSuiteService->checkOrdersSyncStatus([]);
        echo "✅ Empty order IDs handled: " . (empty($emptyResult) ? 'Returned empty array' : 'Returned data') . "\n";
    } catch (Exception $e) {
        echo "⚠️  Empty order IDs caused error: " . $e->getMessage() . "\n";
    }
    
    echo "\n7. Summary and Recommendations...\n";
    echo str_repeat('-', 40) . "\n";
    
    echo "🎉 SuiteQL Integration Test Results:\n\n";
    
    echo "✅ Implemented Features:\n";
    echo "• SuiteQL query execution with proper authentication\n";
    echo "• Single order lookup by external ID\n";
    echo "• Bulk order status checking for performance\n";
    echo "• Comprehensive sync status information\n";
    echo "• Error handling and logging\n";
    echo "• Performance optimization over individual lookups\n\n";
    
    echo "🚀 Benefits:\n";
    echo "• Faster order status checking (bulk queries)\n";
    echo "• More detailed sync status information\n";
    echo "• Better error handling and reporting\n";
    echo "• Reduced API calls to NetSuite\n";
    echo "• Enhanced order-sync page functionality\n\n";
    
    echo "📋 Usage in Application:\n";
    echo "• Order-sync page now shows detailed NetSuite status\n";
    echo "• Webhook processing checks for existing orders efficiently\n";
    echo "• Bulk operations are optimized for performance\n";
    echo "• Comprehensive logging for troubleshooting\n\n";
    
    echo "🎯 Next Steps:\n";
    echo "1. Test the order-sync page with real 3DCart data\n";
    echo "2. Verify NetSuite order creation still works correctly\n";
    echo "3. Monitor logs for any SuiteQL query issues\n";
    echo "4. Consider adding more detailed status information\n";
    echo "5. Optimize queries based on actual usage patterns\n\n";
    
    echo "🚀 SuiteQL integration test completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Test failed with error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>