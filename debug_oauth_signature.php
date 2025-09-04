<?php
/**
 * Debug OAuth Signature Generation
 * 
 * This script shows exactly what OAuth signature our PHP code is generating
 * so you can compare it with Postman's signature.
 */

require_once __DIR__ . '/vendor/autoload.php';

// Load credentials
$credentials = require __DIR__ . '/config/credentials.php';
$netSuiteCreds = $credentials['netsuite'];

echo "=== OAuth Signature Debug ===\n\n";

// Test parameters
$method = 'GET';
$url = 'https://11134099.suitetalk.api.netsuite.com/services/rest/record/v1/customer';
$params = ['limit' => '1'];

// OAuth parameters (same as in NetSuiteService)
$oauthParams = [
    'oauth_consumer_key' => $netSuiteCreds['consumer_key'],
    'oauth_token' => $netSuiteCreds['token_id'],
    'oauth_signature_method' => 'HMAC-SHA1',
    'oauth_timestamp' => time(),
    'oauth_nonce' => bin2hex(random_bytes(16)),
    'oauth_version' => '1.0'
];

echo "OAuth Parameters:\n";
foreach ($oauthParams as $key => $value) {
    if ($key === 'oauth_consumer_key' || $key === 'oauth_token') {
        echo "  $key: " . substr($value, 0, 10) . "...\n";
    } else {
        echo "  $key: $value\n";
    }
}
echo "\n";

// Merge OAuth params with request params (excluding realm from signature)
$allParams = array_merge($oauthParams, $params);
ksort($allParams);

echo "All Parameters (sorted):\n";
foreach ($allParams as $key => $value) {
    echo "  $key: $value\n";
}
echo "\n";

// Create parameter string
$paramString = http_build_query($allParams, '', '&', PHP_QUERY_RFC3986);
echo "Parameter String:\n$paramString\n\n";

// Create signature base string
$baseString = strtoupper($method) . '&' . rawurlencode($url) . '&' . rawurlencode($paramString);
echo "Signature Base String:\n$baseString\n\n";

// Create signing key
$signingKey = rawurlencode($netSuiteCreds['consumer_secret']) . '&' . rawurlencode($netSuiteCreds['token_secret']);
echo "Signing Key:\n" . substr($signingKey, 0, 20) . "...\n\n";

// Generate signature
$signature = base64_encode(hash_hmac('sha1', $baseString, $signingKey, true));
$oauthParams['oauth_signature'] = $signature;

echo "Generated Signature:\n$signature\n\n";

// Build authorization header with realm
$authHeader = 'OAuth realm="' . $netSuiteCreds['account_id'] . '"';
foreach ($oauthParams as $key => $value) {
    $authHeader .= ', ' . $key . '="' . rawurlencode($value) . '"';
}

echo "Complete Authorization Header:\n$authHeader\n\n";

// Show the complete curl command for comparison
echo "Equivalent cURL Command:\n";
echo "curl -X GET \\\n";
echo "  '$url?" . http_build_query($params) . "' \\\n";
echo "  -H 'Content-Type: application/json' \\\n";
echo "  -H 'Accept: application/json' \\\n";
echo "  -H 'Authorization: $authHeader'\n\n";

echo "=== Debug Complete ===\n";
echo "\nCompare this Authorization header with what Postman generates.\n";
echo "They should be identical except for timestamp and nonce values.\n";