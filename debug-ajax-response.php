<?php
/**
 * Debug AJAX Response
 * 
 * This script simulates the exact AJAX request to capture the raw response
 */

echo "🔍 Debugging AJAX Response\n";
echo "==========================\n\n";

// Simulate the exact POST request that the frontend makes
$postData = [
    'action' => 'fetch_orders',
    'start_date' => date('Y-m-d', strtotime('-1 days')), // Small range
    'end_date' => date('Y-m-d'),
    'status' => ''
];

echo "📋 Simulating POST Request:\n";
foreach ($postData as $key => $value) {
    echo "   $key: " . ($value ?: 'empty') . "\n";
}

echo "\n🔄 Making Request to order-sync.php...\n";
echo str_repeat('-', 50) . "\n";

// Use cURL to make the exact same request the browser would make
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/laguna_3dcart_netsuite/public/order-sync.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true); // Include headers in response
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // Don't follow redirects
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

// Add session cookie if available (for authentication)
$cookieFile = __DIR__ . '/debug_cookies.txt';
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
curl_close($ch);

if ($response === false) {
    echo "❌ cURL Error: " . curl_error($ch) . "\n";
    exit(1);
}

// Split headers and body
$headers = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);

echo "📊 Response Analysis:\n";
echo "   HTTP Code: $httpCode\n";
echo "   Response Length: " . strlen($body) . " bytes\n";
echo "   Headers Length: " . strlen($headers) . " bytes\n\n";

echo "📋 Response Headers:\n";
echo str_repeat('-', 30) . "\n";
echo $headers . "\n";

echo "📄 Response Body:\n";
echo str_repeat('-', 30) . "\n";

if (empty($body)) {
    echo "❌ EMPTY RESPONSE BODY!\n";
    echo "   This explains the 'Invalid JSON response from server' error.\n";
    echo "   The server is returning no content.\n\n";
    
    echo "💡 Possible causes:\n";
    echo "   • PHP fatal error before any output\n";
    echo "   • Authentication redirect with empty body\n";
    echo "   • Server configuration issue\n";
    echo "   • File permissions issue\n";
    
} else {
    echo "Response Body (" . strlen($body) . " bytes):\n";
    echo substr($body, 0, 1000) . (strlen($body) > 1000 ? "\n... (truncated)" : "") . "\n\n";
    
    // Test if it's valid JSON
    $decoded = json_decode($body, true);
    
    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
        echo "❌ INVALID JSON!\n";
        echo "   JSON Error: " . json_last_error_msg() . "\n";
        echo "   This explains the 'Invalid JSON response from server' error.\n\n";
        
        // Check if it's HTML (redirect page)
        if (stripos($body, '<html') !== false || stripos($body, '<!doctype') !== false) {
            echo "🔍 Response appears to be HTML (likely a redirect page)\n";
            echo "   This suggests an authentication issue.\n";
        }
        
        // Check for PHP errors
        if (stripos($body, 'Fatal error') !== false || stripos($body, 'Parse error') !== false) {
            echo "🔍 Response contains PHP errors\n";
            echo "   Check the PHP error log for details.\n";
        }
        
    } else {
        echo "✅ VALID JSON RESPONSE!\n";
        echo "   The JSON is valid, so the error might be intermittent.\n";
        echo "   Decoded structure:\n";
        print_r($decoded);
    }
}

echo "\n🎯 Diagnosis:\n";
echo str_repeat('-', 30) . "\n";

if ($httpCode !== 200) {
    echo "❌ HTTP Error: Code $httpCode\n";
    echo "   The server returned an error status.\n";
} elseif (empty($body)) {
    echo "❌ Empty Response\n";
    echo "   The server returned no content.\n";
} elseif (json_decode($body, true) === null) {
    echo "❌ Invalid JSON\n";
    echo "   The server returned non-JSON content.\n";
} else {
    echo "✅ Response looks good\n";
    echo "   The issue might be intermittent or browser-specific.\n";
}

echo "\n🔧 Next Steps:\n";
echo "1. Check PHP error logs\n";
echo "2. Verify you're logged into the system\n";
echo "3. Try accessing order-sync.php directly in browser\n";
echo "4. Check file permissions on order-sync.php\n";

echo "\n🎯 Debug completed!\n";
?>