<?php
require_once __DIR__ . '/vendor/autoload.php';

echo "Testing Manual Sync for Order 1144809\n";
echo "=====================================\n\n";

// Simulate the manual sync request that was in the logs
$orderId = '1144809';

echo "Making manual sync request for order {$orderId}...\n\n";

// Use cURL to make the request to the manual sync endpoint
$url = 'http://localhost/laguna_3dcart_netsuite/public/manual-sync.php';
$postData = json_encode(['order_id' => $orderId]);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($postData)
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "❌ cURL Error: {$error}\n";
} else {
    echo "HTTP Response Code: {$httpCode}\n";
    echo "Response:\n";
    echo $response . "\n\n";
}

echo "=== CHECK LOGS ===\n";
echo "Now check the latest log file for:\n";
echo "1. 'NetSuite customer creation failed - Full error details'\n";
echo "2. 'NetSuite customer creation failed - Request payload'\n";
echo "3. Any field validation warnings\n\n";

// Also try to read the latest log file
$logDir = __DIR__ . '/logs';
$logFiles = glob($logDir . '/app-*.log');
if (!empty($logFiles)) {
    $latestLog = max($logFiles);
    echo "Latest log file: " . basename($latestLog) . "\n";
    echo "Tail of log (last 20 lines):\n";
    echo "----------------------------\n";
    
    $lines = file($latestLog);
    $lastLines = array_slice($lines, -20);
    foreach ($lastLines as $line) {
        echo $line;
    }
}
?>