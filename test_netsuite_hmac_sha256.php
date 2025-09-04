<?php
/**
 * Test script to verify NetSuite HMAC-SHA256 signature implementation
 */

require_once __DIR__ . '/vendor/autoload.php';

use Laguna\Integration\Services\NetSuiteService;
use Laguna\Integration\Utils\Logger;

echo "<h1>NetSuite HMAC-SHA256 Signature Test</h1>\n";
echo "<pre>\n";

try {
    // Initialize the NetSuite service
    $netsuiteService = new NetSuiteService();
    
    echo "✓ NetSuite service initialized successfully\n";
    echo "✓ Using signature method: " . $netsuiteService->getSignatureMethod() . "\n\n";
    
    // Test connection to NetSuite
    echo "Testing NetSuite connection...\n";
    $connectionResult = $netsuiteService->testConnection();
    
    if ($connectionResult['success']) {
        echo "✓ NetSuite connection successful!\n";
        echo "  Status Code: " . $connectionResult['status_code'] . "\n";
        echo "  Response Time: " . $connectionResult['response_time'] . "\n";
        
        // Test signature generation by making a simple API call
        echo "\n✓ HMAC-SHA256 signature is working correctly\n";
        echo "✓ OAuth authentication successful\n";
        
    } else {
        echo "✗ NetSuite connection failed!\n";
        echo "  Error: " . $connectionResult['error'] . "\n";
        
        if (isset($connectionResult['status_code'])) {
            echo "  Status Code: " . $connectionResult['status_code'] . "\n";
        }
        
        if (isset($connectionResult['details'])) {
            echo "  Details:\n";
            foreach ($connectionResult['details'] as $key => $value) {
                echo "    {$key}: {$value}\n";
            }
        }
        
        // Check if it's a signature-related error
        if (strpos($connectionResult['error'], 'signature') !== false || 
            strpos($connectionResult['error'], 'oauth') !== false ||
            strpos($connectionResult['error'], 'unauthorized') !== false) {
            echo "\n⚠️  This appears to be a signature-related error.\n";
            echo "   Please verify:\n";
            echo "   1. Your NetSuite credentials are correct\n";
            echo "   2. Your NetSuite integration supports HMAC-SHA256\n";
            echo "   3. The token-based authentication is properly configured\n";
        }
    }
    
} catch (Exception $e) {
    echo "✗ Error during test: " . $e->getMessage() . "\n";
    echo "  Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n</pre>\n";

// Display signature method information
echo "<h2>Signature Method Information</h2>\n";
echo "<pre>\n";
echo "Current signature method: HMAC-SHA256\n";
echo "Previous signature method: HMAC-SHA1\n\n";

echo "Benefits of HMAC-SHA256:\n";
echo "• Enhanced security with stronger cryptographic hash\n";
echo "• Better resistance to collision attacks\n";
echo "• Compliance with modern security standards\n";
echo "• Required by some NetSuite configurations\n\n";

echo "If you encounter authentication errors:\n";
echo "1. Verify your NetSuite integration record supports HMAC-SHA256\n";
echo "2. Check that your consumer key and token are active\n";
echo "3. Ensure your NetSuite account has the required permissions\n";
echo "4. Verify the account ID and base URL are correct\n";
echo "</pre>\n";
?>