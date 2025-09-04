<?php
/**
 * Individual Service Testing Demo
 * 
 * This demonstrates the new individual service testing functionality.
 * You can delete this file - it's just for testing purposes.
 */

require_once __DIR__ . '/vendor/autoload.php';

use Laguna\Integration\Controllers\StatusController;

echo "=== Individual Service Testing Demo ===\n\n";
echo "This demonstrates the new individual service testing feature.\n";
echo "The same functionality is available via AJAX on the status page.\n\n";

$services = ['3dcart', 'netsuite', 'sendgrid'];
$controller = new StatusController();

foreach ($services as $service) {
    echo "Testing $service service:\n";
    
    try {
        $startTime = microtime(true);
        $result = $controller->testServiceConnection($service);
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        
        echo "  ✓ Test completed in {$duration}ms\n";
        echo "  Status: " . ($result['success'] ? '✅ SUCCESS' : '❌ FAILED') . "\n";
        
        if (!$result['success']) {
            $error = substr($result['error'], 0, 80);
            echo "  Error: {$error}...\n";
        }
        
        if (isset($result['response_time'])) {
            echo "  Response Time: " . $result['response_time'] . "\n";
        }
        
    } catch (Exception $e) {
        echo "  ❌ EXCEPTION: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

echo "=== Demo Complete ===\n";
echo "Visit /status.php to use the interactive dashboard!\n";