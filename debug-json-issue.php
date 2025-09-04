<?php
/**
 * Debug JSON Issue
 * 
 * This script helps identify what's causing the "Invalid JSON response from server" error
 */

echo "🔍 Debugging JSON Response Issue\n";
echo "=================================\n\n";

// Test 1: Check if the SuiteQL fixes resolved NetSuite errors
echo "1. Testing NetSuite SuiteQL Fixes...\n";
echo str_repeat('-', 40) . "\n";

require_once __DIR__ . '/vendor/autoload.php';

use Laguna\Integration\Services\NetSuiteService;

try {
    $netSuiteService = new NetSuiteService();
    
    // Test connection
    $connectionTest = $netSuiteService->testConnection();
    if ($connectionTest['success']) {
        echo "✅ NetSuite connection successful\n";
    } else {
        echo "❌ NetSuite connection failed: " . $connectionTest['message'] . "\n";
    }
    
    // Test SuiteQL query with corrected syntax
    echo "Testing SuiteQL query (without 'total' column)...\n";
    $testOrderIds = ['1057113']; // Use our test order
    $syncStatus = $netSuiteService->checkOrdersSyncStatus($testOrderIds);
    echo "✅ SuiteQL query successful\n";
    echo "   Result: " . json_encode($syncStatus) . "\n";
    
} catch (Exception $e) {
    echo "❌ NetSuite error: " . $e->getMessage() . "\n";
    echo "   This could be causing the JSON response to fail\n";
}

echo "\n2. Testing JSON Response Structure...\n";
echo str_repeat('-', 40) . "\n";

// Test the exact response structure that order-sync.php creates
$testResponse = [
    'success' => true,
    'orders' => [
        [
            'order_id' => 1057113,
            'order_date' => '2025-08-10T06:40:06',
            'customer_name' => 'Logan Williams',
            'customer_company' => 'OakTree Supply',
            'order_total' => 6778.65,
            'order_status' => 2,
            'order_status_name' => 'Processing',
            'in_netsuite' => false,
            'netsuite_id' => null,
            'netsuite_tranid' => null,
            'netsuite_status' => null,
            'netsuite_total' => null, // Fixed: removed total field
            'sync_date' => null,
            'can_sync' => true,
            'sync_error' => null
        ]
    ],
    'total_count' => 1,
    'date_range' => '2025-08-12 to 2025-08-14'
];

$jsonTest = json_encode($testResponse);
if ($jsonTest === false) {
    echo "❌ JSON encoding failed: " . json_last_error_msg() . "\n";
} else {
    echo "✅ JSON encoding successful (" . strlen($jsonTest) . " bytes)\n";
    echo "   Sample: " . substr($jsonTest, 0, 100) . "...\n";
}

echo "\n3. Checking Common JSON Issues...\n";
echo str_repeat('-', 40) . "\n";

// Check for common issues that cause JSON problems
$issues = [];

// Check PHP error reporting
if (ini_get('display_errors')) {
    $issues[] = "display_errors is ON - PHP errors may interfere with JSON";
}

// Check output buffering
if (!ob_get_level()) {
    echo "⚠️  Output buffering not active (this is OK for CLI)\n";
} else {
    echo "✅ Output buffering active\n";
}

// Check memory limit
$memoryLimit = ini_get('memory_limit');
echo "Memory limit: $memoryLimit\n";

// Check execution time limit
$timeLimit = ini_get('max_execution_time');
echo "Execution time limit: {$timeLimit}s\n";

if (empty($issues)) {
    echo "✅ No obvious PHP configuration issues found\n";
} else {
    echo "⚠️  Potential issues found:\n";
    foreach ($issues as $issue) {
        echo "   • $issue\n";
    }
}

echo "\n4. Recommendations...\n";
echo str_repeat('-', 40) . "\n";

echo "Based on the 'Invalid JSON response from server' error:\n\n";

echo "✅ **FIXES APPLIED:**\n";
echo "   • Fixed SuiteQL syntax (removed 'total' column)\n";
echo "   • Fixed authentication to return JSON errors\n";
echo "   • Added response size limiting\n";
echo "   • Added proper error handling\n\n";

echo "🔍 **DEBUGGING STEPS:**\n";
echo "1. Open browser Developer Tools (F12)\n";
echo "2. Go to Network tab\n";
echo "3. Try the search in order-sync page\n";
echo "4. Look at the actual response from order-sync.php\n";
echo "5. Check if response is:\n";
echo "   • Empty (causes 'Unexpected end of JSON input')\n";
echo "   • HTML error page (causes 'Invalid JSON')\n";
echo "   • Partial JSON (causes parsing errors)\n\n";

echo "💡 **LIKELY CAUSES:**\n";
echo "   • PHP fatal error before JSON output\n";
echo "   • Authentication redirect returning HTML\n";
echo "   • NetSuite API timeout/error\n";
echo "   • 3DCart API timeout/error\n";
echo "   • Server configuration issue\n\n";

echo "🎯 **NEXT STEPS:**\n";
echo "1. Check browser Network tab for actual response\n";
echo "2. Check PHP error logs\n";
echo "3. Try with smaller date range (1 day)\n";
echo "4. Verify you're logged in to the system\n\n";

echo "🎯 Debug completed! Check the browser Network tab for the actual response.\n";
?>