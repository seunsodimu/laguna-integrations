<?php
/**
 * NetSuite API Connection Test
 * 
 * This script tests the NetSuite API connection and provides detailed error information.
 */

require_once __DIR__ . '/vendor/autoload.php';

use Laguna\Integration\Services\NetSuiteService;

echo "=== NetSuite API Connection Test ===\n";

// Load credentials
$credentials = require __DIR__ . '/config/credentials.php';
$netSuiteCreds = $credentials['netsuite'];

echo "Account ID: " . $netSuiteCreds['account_id'] . "\n";
echo "Base URL: " . $netSuiteCreds['base_url'] . "\n";
echo "API Version: " . $netSuiteCreds['rest_api_version'] . "\n";
echo "Signature Method: " . ($netSuiteCreds['signature_method'] ?? 'HMAC-SHA256') . "\n\n";

// Test the service
echo "Testing NetSuite connection...\n";
try {
    $service = new NetSuiteService();
    $result = $service->testConnection();
    
    if ($result['success']) {
        echo "✅ SUCCESS! NetSuite API is working.\n";
        echo "Status Code: " . $result['status_code'] . "\n";
        echo "Response Time: " . $result['response_time'] . "\n";
    } else {
        echo "❌ FAILED! NetSuite API connection failed.\n";
        echo "Status Code: " . $result['status_code'] . "\n";
        
        if (isset($result['details']['response_body'])) {
            $responseData = json_decode($result['details']['response_body'], true);
            if (isset($responseData['o:errorDetails'][0]['o:errorCode'])) {
                echo "Error Code: " . $responseData['o:errorDetails'][0]['o:errorCode'] . "\n";
                echo "Error Detail: " . $responseData['o:errorDetails'][0]['detail'] . "\n";
            }
        }
        
        echo "\n💡 TROUBLESHOOTING TIPS:\n";
        echo "1. Check that the Integration Application is enabled in NetSuite\n";
        echo "2. Verify that the Access Token is not expired\n";
        echo "3. Ensure the Token has the required permissions (REST Web Services)\n";
        echo "4. Check the Login Audit Trail in NetSuite for more details\n";
        echo "5. Verify that the Consumer Key and Token ID are correct\n";
        echo "6. Ensure your NetSuite integration supports HMAC-SHA256 signature method\n";
        echo "7. If using older NetSuite version, you may need to use HMAC-SHA1 instead\n";
    }
    
} catch (Exception $e) {
    echo "❌ EXCEPTION: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";