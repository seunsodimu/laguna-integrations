<?php
/**
 * Test JSON Response
 * 
 * This script simulates the exact POST request to test JSON response
 */

echo "🧪 Testing JSON Response\n";
echo "========================\n\n";

// Simulate the POST request
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'action' => 'fetch_orders',
    'start_date' => date('Y-m-d', strtotime('-2 days')), // Small range
    'end_date' => date('Y-m-d'),
    'status' => ''
];

echo "📋 Simulating POST Request:\n";
echo "   Action: " . $_POST['action'] . "\n";
echo "   Start Date: " . $_POST['start_date'] . "\n";
echo "   End Date: " . $_POST['end_date'] . "\n";
echo "   Status: " . ($_POST['status'] ?: 'All') . "\n";

echo "\n🔄 Capturing Response...\n";
echo str_repeat('-', 40) . "\n";

// Capture the output
ob_start();

// Include the order-sync.php file (it will process the POST and output JSON)
include __DIR__ . '/public/order-sync.php';

$response = ob_get_clean();

echo "📊 Response Analysis:\n";
echo "   Length: " . strlen($response) . " bytes\n";

if (empty($response)) {
    echo "   Status: ❌ Empty response (this causes 'Unexpected end of JSON input')\n";
} else {
    echo "   Status: ✅ Response received\n";
    
    // Test if it's valid JSON
    $decoded = json_decode($response, true);
    
    if ($decoded === null) {
        echo "   JSON Status: ❌ Invalid JSON - " . json_last_error_msg() . "\n";
        echo "   First 500 chars: " . substr($response, 0, 500) . "\n";
    } else {
        echo "   JSON Status: ✅ Valid JSON\n";
        echo "   Success: " . ($decoded['success'] ? 'Yes' : 'No') . "\n";
        
        if ($decoded['success']) {
            echo "   Orders Count: " . count($decoded['orders'] ?? []) . "\n";
            echo "   Date Range: " . ($decoded['date_range'] ?? 'N/A') . "\n";
            
            if (isset($decoded['warning'])) {
                echo "   Warning: " . $decoded['warning'] . "\n";
            }
        } else {
            echo "   Error: " . ($decoded['error'] ?? 'Unknown error') . "\n";
        }
    }
}

echo "\n🎯 Test Results:\n";
if (!empty($response) && json_decode($response, true) !== null) {
    echo "✅ JSON response is working correctly!\n";
    echo "   The 'Unexpected end of JSON input' error is likely:\n";
    echo "   • A browser timeout issue\n";
    echo "   • A server configuration issue\n";
    echo "   • A network connectivity issue\n";
    echo "\n💡 Try using a smaller date range (1-2 days) in the UI\n";
} else {
    echo "❌ JSON response is not working\n";
    echo "   This explains the 'Unexpected end of JSON input' error\n";
    echo "   Check the response content above for clues\n";
}

echo "\n🎯 JSON response test completed!\n";
?>