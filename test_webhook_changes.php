<?php
require_once __DIR__ . '/vendor/autoload.php';

use Laguna\Integration\Controllers\WebhookController;

echo "Testing WebhookController Changes\n";
echo "=================================\n\n";

// Create a webhook controller instance
$webhookController = new WebhookController();

// Use reflection to check the getOrCreateCustomer method
$reflection = new ReflectionClass($webhookController);
$method = $reflection->getMethod('getOrCreateCustomer');
$method->setAccessible(true);

// Get the method source code to verify our changes
$filename = $reflection->getFileName();
$startLine = $method->getStartLine();
$endLine = $method->getEndLine();

echo "WebhookController file: $filename\n";
echo "getOrCreateCustomer method lines: $startLine - $endLine\n\n";

// Read the method source
$lines = file($filename);
$methodLines = array_slice($lines, $startLine - 1, $endLine - $startLine + 1);

echo "Method source (first 20 lines):\n";
echo "--------------------------------\n";
for ($i = 0; $i < min(20, count($methodLines)); $i++) {
    echo sprintf("%3d: %s", $startLine + $i, $methodLines[$i]);
}

// Check if our changes are present
$methodSource = implode('', $methodLines);
if (strpos($methodSource, 'rawCustomerData') !== false) {
    echo "\n✅ Changes detected: 'rawCustomerData' found in method\n";
} else {
    echo "\n❌ Changes NOT detected: 'rawCustomerData' not found in method\n";
}

if (strpos($methodSource, 'toNetSuiteFormat') !== false) {
    echo "❌ Old code still present: 'toNetSuiteFormat' found in method\n";
} else {
    echo "✅ Old code removed: 'toNetSuiteFormat' not found in method\n";
}
?>