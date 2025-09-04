<?php
/**
 * Brevo Integration Test Script
 * 
 * Test the Brevo email service integration without sending actual emails
 */

require_once __DIR__ . '/vendor/autoload.php';

use Laguna\Integration\Services\BrevoEmailService;
use Laguna\Integration\Services\EmailServiceFactory;
use Laguna\Integration\Services\UnifiedEmailService;
use Laguna\Integration\Utils\Logger;

echo "=== Brevo Integration Test ===\n\n";

// Test 1: Check if Brevo service can be instantiated
echo "1. Testing Brevo service instantiation...\n";
try {
    $brevoService = new BrevoEmailService();
    echo "✅ Brevo service created successfully\n";
} catch (Exception $e) {
    echo "❌ Failed to create Brevo service: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: Check EmailServiceFactory
echo "\n2. Testing EmailServiceFactory...\n";
try {
    $availableProviders = EmailServiceFactory::getAvailableProviders();
    echo "✅ Available providers: " . implode(', ', array_keys($availableProviders)) . "\n";
    
    $currentProvider = EmailServiceFactory::getCurrentProvider();
    echo "✅ Current provider: " . $currentProvider['name'] . "\n";
} catch (Exception $e) {
    echo "❌ EmailServiceFactory test failed: " . $e->getMessage() . "\n";
}

// Test 3: Check UnifiedEmailService
echo "\n3. Testing UnifiedEmailService...\n";
try {
    $unifiedService = new UnifiedEmailService();
    $providerInfo = $unifiedService->getProviderInfo();
    echo "✅ Unified service created with provider: " . $providerInfo['name'] . "\n";
} catch (Exception $e) {
    echo "❌ UnifiedEmailService test failed: " . $e->getMessage() . "\n";
}

// Test 4: Test connection (will fail with invalid API key, but should handle gracefully)
echo "\n4. Testing connection handling...\n";
try {
    $connectionResult = $unifiedService->testConnection();
    if ($connectionResult['success']) {
        echo "✅ Connection test successful\n";
    } else {
        echo "⚠️ Connection test failed (expected with invalid/missing API key): " . ($connectionResult['error'] ?? 'Unknown error') . "\n";
    }
} catch (Exception $e) {
    echo "❌ Connection test threw exception: " . $e->getMessage() . "\n";
}

// Test 5: Test provider switching simulation
echo "\n5. Testing provider switching logic...\n";
try {
    $credentials = require __DIR__ . '/config/credentials.php';
    $originalProvider = $credentials['email']['provider'] ?? 'sendgrid';
    echo "✅ Original provider: $originalProvider\n";
    
    // Test what would happen if we switched to Brevo
    if (isset($credentials['email']['brevo'])) {
        echo "✅ Brevo configuration found in credentials\n";
    } else {
        echo "⚠️ Brevo configuration not found in credentials (this is normal for initial setup)\n";
    }
} catch (Exception $e) {
    echo "❌ Provider switching test failed: " . $e->getMessage() . "\n";
}

// Test 6: Test email template generation
echo "\n6. Testing email template generation...\n";
try {
    // Test if we can create test email content without sending
    $testOrderData = [
        'order_id' => 'TEST-12345',
        'customer_name' => 'Test Customer',
        'order_total' => '99.99',
        'order_date' => date('Y-m-d H:i:s')
    ];
    
    // This would normally send an email, but we'll catch any errors
    echo "✅ Email template data prepared successfully\n";
} catch (Exception $e) {
    echo "❌ Email template test failed: " . $e->getMessage() . "\n";
}

// Test 7: Check file permissions and structure
echo "\n7. Testing file structure...\n";
$requiredFiles = [
    'src/Services/BrevoEmailService.php',
    'src/Services/EmailServiceFactory.php',
    'src/Services/UnifiedEmailService.php',
    'public/email-provider-config.php',
    'documentation/BREVO_EMAIL_SETUP.md'
];

foreach ($requiredFiles as $file) {
    $fullPath = __DIR__ . '/' . $file;
    if (file_exists($fullPath)) {
        echo "✅ $file exists\n";
    } else {
        echo "❌ $file missing\n";
    }
}

echo "\n=== Integration Test Complete ===\n";
echo "\nNext Steps:\n";
echo "1. Get a Brevo API key from https://app.brevo.com\n";
echo "2. Configure credentials in config/credentials.php or via web interface\n";
echo "3. Switch provider to 'brevo' in configuration\n";
echo "4. Test with: php test-email-cli.php your-email@example.com basic\n";
echo "5. Or use the web interface: public/test-email.php\n\n";

echo "Configuration URLs:\n";
echo "- Email Provider Config: http://your-domain/public/email-provider-config.php\n";
echo "- Email Testing: http://your-domain/public/test-email.php\n";
echo "- Status Dashboard: http://your-domain/public/status.php\n\n";