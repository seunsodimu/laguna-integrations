<?php
/**
 * Command Line Email Test Script
 * 
 * Usage: php test-email-cli.php [email] [type]
 * Example: php test-email-cli.php test@example.com basic
 */

require_once __DIR__ . '/vendor/autoload.php';

use Laguna\Integration\Services\UnifiedEmailService;
use Laguna\Integration\Utils\Logger;

// Set timezone
date_default_timezone_set('America/New_York');

// This line is now handled above

// Get command line arguments
$email = $argv[1] ?? null;
$testType = $argv[2] ?? 'basic';

if (empty($email)) {
    echo "Usage: php test-email-cli.php [email] [type]\n";
    echo "Types: basic, order, error, connection\n";
    echo "Example: php test-email-cli.php test@example.com basic\n\n";
    exit(1);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "Error: Invalid email address format\n";
    exit(1);
}

$validTypes = ['basic', 'order', 'error', 'connection'];
if (!in_array($testType, $validTypes)) {
    echo "Error: Invalid test type. Valid types: " . implode(', ', $validTypes) . "\n";
    exit(1);
}

try {
    $emailService = new UnifiedEmailService();
    $logger = Logger::getInstance();
    $providerInfo = $emailService->getProviderInfo();
    
    echo "=== {$providerInfo['name']} Email Test (CLI) ===\n\n";
    echo "Testing {$providerInfo['name']} connection...\n";
    $connectionTest = $emailService->testConnection();
    
    if (!$connectionTest['success']) {
        echo "❌ {$providerInfo['name']} connection failed: " . ($connectionTest['error'] ?? 'Unknown error') . "\n";
        exit(1);
    }
    
    echo "✅ {$providerInfo['name']} connection successful\n";
    
    echo "Sending test email...\n";
    echo "To: {$email}\n";
    echo "Type: {$testType}\n\n";
    
    $result = $emailService->sendTestEmail($email, $testType);
    
    if ($result['success']) {
        echo "✅ Test email sent successfully!\n";
        echo "Status Code: " . ($result['status_code'] ?? 'N/A') . "\n";
        
        if (isset($result['response_body'])) {
            echo "Response: " . $result['response_body'] . "\n";
        }
        
        echo "\nPlease check your email inbox (and spam folder).\n";
        echo "Check logs/app-" . date('Y-m-d') . ".log for detailed information.\n";
    } else {
        echo "❌ Failed to send test email\n";
        echo "Error: " . ($result['message'] ?? 'Unknown error') . "\n";
        
        if (isset($result['error'])) {
            echo "Details: " . $result['error'] . "\n";
        }
        
        if (isset($result['status_code'])) {
            echo "Status Code: " . $result['status_code'] . "\n";
        }
        
        if (isset($result['response_body'])) {
            echo "Response: " . $result['response_body'] . "\n";
        }
        
        echo "\nCheck logs/app-" . date('Y-m-d') . ".log for more details.\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Check logs for more details.\n";
    exit(1);
}

echo "\n=== Test Complete ===\n";
?>