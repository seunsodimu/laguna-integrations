<?php
/**
 * 3DCart API Connection Test
 * 
 * This script tests the 3DCart API connection and provides detailed error information.
 */

require_once __DIR__ . '/vendor/autoload.php';

use Laguna\Integration\Services\ThreeDCartService;

echo "=== 3DCart API Connection Test ===\n";

// Load credentials
$credentials = require __DIR__ . '/config/credentials.php';
$threeDCartCreds = $credentials['3dcart'];

echo "Store URL: " . $threeDCartCreds['store_url'] . "\n";
echo "Secure URL: " . $threeDCartCreds['secure_url'] . "\n";
echo "Private Key: " . substr($threeDCartCreds['private_key'], 0, 10) . "...\n";
echo "Token: " . substr($threeDCartCreds['token'], 0, 10) . "...\n\n";

// Show the correct API URL being used
$baseUrl = 'https://apirest.3dcart.com/3dCartWebAPI/v1';
echo "API Base URL: " . $baseUrl . "\n";
echo "Full Test URL: " . $baseUrl . '/Orders?limit=1' . "\n";
echo "Bearer Token: " . substr($threeDCartCreds['bearer_token'], 0, 10) . "...\n\n";

// Test the service
echo "Testing 3DCart connection...\n";
try {
    $service = new ThreeDCartService();
    $result = $service->testConnection();
    
    if ($result['success']) {
        echo "✅ SUCCESS! 3DCart API is working.\n";
        echo "Status Code: " . $result['status_code'] . "\n";
        echo "Response Time: " . $result['response_time'] . "\n";
        if (isset($result['final_url'])) {
            echo "Final URL: " . $result['final_url'] . "\n";
        }
    } else {
        echo "❌ FAILED! 3DCart API connection failed.\n";
        echo "Status Code: " . ($result['status_code'] ?? 'N/A') . "\n";
        
        if (isset($result['details'])) {
            echo "Error: " . $result['error'] . "\n";
            
            if (isset($result['details']['response_status'])) {
                echo "Response Status: " . $result['details']['response_status'] . "\n";
            }
            
            if (isset($result['details']['response_body'])) {
                $body = $result['details']['response_body'];
                if (strlen($body) > 200) {
                    echo "Response Body: " . substr($body, 0, 200) . "...\n";
                } else {
                    echo "Response Body: " . $body . "\n";
                }
            }
        }
        
        echo "\n💡 TROUBLESHOOTING TIPS:\n";
        
        if (strpos($result['error'], 'redirect') !== false) {
            echo "1. Try using the secure URL: " . $threeDCartCreds['secure_url'] . "\n";
            echo "2. The store might be redirecting multiple times\n";
            echo "3. Check if the store URL is correct\n";
        } elseif (strpos($result['error'], 'SSL') !== false || strpos($result['error'], 'certificate') !== false) {
            echo "1. SSL certificate issue - this is common in development\n";
            echo "2. The code already disables SSL verification\n";
            echo "3. Try accessing the store URL in a browser\n";
        } elseif (strpos($result['error'], '401') !== false || strpos($result['error'], 'Unauthorized') !== false) {
            echo "1. Check the Private Key and Token in 3DCart admin\n";
            echo "2. Ensure API access is enabled for your account\n";
            echo "3. Verify the credentials haven't expired\n";
        } elseif (strpos($result['error'], '404') !== false) {
            echo "1. Check that the store URL is correct\n";
            echo "2. Verify the 3DCart API is enabled\n";
            echo "3. Try the secure URL instead\n";
        } elseif (strpos($result['error'], '429') !== false) {
            echo "1. This is a rate limit error - wait a few minutes\n";
            echo "2. Your credentials are correct, but requests are being limited\n";
            echo "3. Cloudflare or 3DCart is protecting against too many requests\n";
        } else {
            echo "1. Check that the 3DCart store is accessible\n";
            echo "2. Verify API credentials in 3DCart admin panel\n";
            echo "3. Try different endpoints (/Store, /Products)\n";
        }
        
        echo "4. Test the same request in Postman for comparison\n";
    }
    
} catch (Exception $e) {
    echo "❌ EXCEPTION: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
echo "\nTo test in Postman:\n";
echo "URL: " . $baseUrl . "/Orders?limit=1\n";
echo "Headers:\n";
echo "  Accept: application/json\n";
echo "  SecureURL: " . $threeDCartCreds['secure_url'] . "\n";
echo "  PrivateKey: " . $threeDCartCreds['private_key'] . "\n";
echo "  Token: " . $threeDCartCreds['token'] . "\n";
echo "  Authorization: Bearer " . $threeDCartCreds['bearer_token'] . "\n";