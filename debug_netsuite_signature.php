<?php
/**
 * Debug script to show NetSuite HMAC-SHA256 signature generation process
 */

require_once __DIR__ . '/vendor/autoload.php';

echo "<h1>NetSuite HMAC-SHA256 Signature Debug</h1>\n";
echo "<pre>\n";

try {
    // Load credentials
    $credentials = require __DIR__ . '/config/credentials.php';
    $netsuiteCredentials = $credentials['netsuite'];
    
    echo "=== NetSuite Credentials (masked) ===\n";
    echo "Account ID: " . substr($netsuiteCredentials['account_id'], 0, 4) . "***\n";
    echo "Consumer Key: " . substr($netsuiteCredentials['consumer_key'], 0, 8) . "***\n";
    echo "Token ID: " . substr($netsuiteCredentials['token_id'], 0, 8) . "***\n";
    echo "Base URL: " . $netsuiteCredentials['base_url'] . "\n";
    echo "API Version: " . $netsuiteCredentials['rest_api_version'] . "\n\n";
    
    // Simulate signature generation
    $method = 'GET';
    $baseUrl = rtrim($netsuiteCredentials['base_url'], '/') . '/services/rest/record/' . $netsuiteCredentials['rest_api_version'];
    $endpoint = '/customer';
    $fullUrl = $baseUrl . $endpoint;
    $params = ['limit' => 1];
    
    echo "=== Request Details ===\n";
    echo "Method: {$method}\n";
    echo "URL: {$fullUrl}\n";
    echo "Query Params: " . json_encode($params) . "\n\n";
    
    // Generate OAuth parameters
    $oauthParams = [
        'oauth_consumer_key' => $netsuiteCredentials['consumer_key'],
        'oauth_token' => $netsuiteCredentials['token_id'],
        'oauth_signature_method' => 'HMAC-SHA256',
        'oauth_timestamp' => time(),
        'oauth_nonce' => bin2hex(random_bytes(16)),
        'oauth_version' => '1.0'
    ];
    
    echo "=== OAuth Parameters ===\n";
    foreach ($oauthParams as $key => $value) {
        if (strpos($key, 'oauth_') === 0 && !in_array($key, ['oauth_consumer_key', 'oauth_token'])) {
            echo "{$key}: {$value}\n";
        } else {
            echo "{$key}: " . substr($value, 0, 8) . "***\n";
        }
    }
    echo "\n";
    
    // Merge all parameters
    $allParams = array_merge($oauthParams, $params);
    ksort($allParams);
    
    echo "=== All Parameters (sorted) ===\n";
    foreach ($allParams as $key => $value) {
        if (in_array($key, ['oauth_consumer_key', 'oauth_token'])) {
            echo "{$key}: " . substr($value, 0, 8) . "***\n";
        } else {
            echo "{$key}: {$value}\n";
        }
    }
    echo "\n";
    
    // Create parameter string
    $paramString = http_build_query($allParams, '', '&', PHP_QUERY_RFC3986);
    echo "=== Parameter String ===\n";
    echo $paramString . "\n\n";
    
    // Create signature base string
    $baseString = strtoupper($method) . '&' . rawurlencode($fullUrl) . '&' . rawurlencode($paramString);
    echo "=== Signature Base String ===\n";
    echo $baseString . "\n\n";
    
    // Create signing key
    $signingKey = rawurlencode($netsuiteCredentials['consumer_secret']) . '&' . rawurlencode($netsuiteCredentials['token_secret']);
    echo "=== Signing Key (masked) ===\n";
    echo substr($signingKey, 0, 16) . "***&***" . substr($signingKey, -8) . "\n\n";
    
    // Generate signature
    $signature = base64_encode(hash_hmac('sha256', $baseString, $signingKey, true));
    echo "=== Generated Signature (HMAC-SHA256) ===\n";
    echo $signature . "\n\n";
    
    // Build authorization header
    $oauthParams['oauth_signature'] = $signature;
    $authHeader = 'OAuth realm="' . $netsuiteCredentials['account_id'] . '"';
    foreach ($oauthParams as $key => $value) {
        $authHeader .= ', ' . $key . '="' . rawurlencode($value) . '"';
    }
    
    echo "=== Authorization Header ===\n";
    echo $authHeader . "\n\n";
    
    echo "=== Signature Method Comparison ===\n";
    $sha1Signature = base64_encode(hash_hmac('sha1', $baseString, $signingKey, true));
    echo "HMAC-SHA1 signature:   {$sha1Signature}\n";
    echo "HMAC-SHA256 signature: {$signature}\n";
    echo "Signatures are different: " . ($sha1Signature !== $signature ? 'YES' : 'NO') . "\n\n";
    
    echo "✓ Signature generation completed successfully\n";
    echo "✓ Using HMAC-SHA256 method\n";
    
} catch (Exception $e) {
    echo "✗ Error during signature generation: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "</pre>\n";
?>