<?php
/**
 * Debug Brevo API Connection
 * 
 * This script was used during development to debug the Brevo API integration.
 * It's kept for reference and future troubleshooting.
 */

require_once __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;

$credentials = require __DIR__ . '/config/credentials.php';
$apiKey = $credentials['email']['brevo']['api_key'];

echo "=== Brevo API Debug Tool ===\n";
echo "This tool helps debug Brevo API connectivity issues.\n\n";

echo "Configuration:\n";
echo "- API Key: " . substr($apiKey, 0, 20) . "...\n";
echo "- From Email: " . $credentials['email']['brevo']['from_email'] . "\n\n";

$client = new Client([
    'timeout' => 30,
    'verify' => false
]);

// Test API key validity
echo "1. Testing API key validity...\n";
try {
    $response = $client->get('https://api.brevo.com/v3/account', [
        'headers' => [
            'Accept' => 'application/json',
            'API-key' => $apiKey
        ]
    ]);
    
    $accountData = json_decode($response->getBody()->getContents(), true);
    echo "✅ API Key is valid!\n";
    echo "- Account Email: " . $accountData['email'] . "\n";
    echo "- Company: " . $accountData['companyName'] . "\n";
    echo "- Plan: " . $accountData['plan'][0]['type'] . "\n";
    echo "- Credits: " . $accountData['plan'][0]['credits'] . "\n\n";
    
} catch (Exception $e) {
    echo "❌ API Key test failed: " . $e->getMessage() . "\n";
    if (method_exists($e, 'getResponse') && $e->getResponse()) {
        echo "Status Code: " . $e->getResponse()->getStatusCode() . "\n";
        echo "Response Body: " . $e->getResponse()->getBody()->getContents() . "\n";
    }
    echo "\n";
    exit(1);
}

// Test email sending
echo "2. Testing email sending...\n";
$testData = [
    'sender' => [
        'name' => 'Laguna 3DCart Integration',
        'email' => 'lagunamarketing@lagunatools.com'
    ],
    'to' => [
        [
            'email' => 'seun_sodimu@lagunatools.com',
            'name' => 'Seun Sodimu'
        ]
    ],
    'subject' => 'Brevo API Debug Test',
    'htmlContent' => '<html><head></head><body><p>Hello,</p><p>This is a debug test email from the Brevo integration.</p><p>If you receive this, the integration is working correctly!</p></body></html>'
];

try {
    $response = $client->post('https://api.brevo.com/v3/smtp/email', [
        'headers' => [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'API-key' => $apiKey
        ],
        'json' => $testData
    ]);
    
    $statusCode = $response->getStatusCode();
    $responseData = json_decode($response->getBody()->getContents(), true);
    
    echo "✅ Email sent successfully!\n";
    echo "- Status Code: $statusCode\n";
    echo "- Message ID: " . $responseData['messageId'] . "\n";
    echo "- Recipient: " . $testData['to'][0]['email'] . "\n\n";
    
    echo "🎉 Brevo integration is working perfectly!\n";
    
} catch (Exception $e) {
    echo "❌ Email sending failed: " . $e->getMessage() . "\n";
    
    if (method_exists($e, 'getResponse') && $e->getResponse()) {
        echo "Status Code: " . $e->getResponse()->getStatusCode() . "\n";
        echo "Response Body: " . $e->getResponse()->getBody()->getContents() . "\n";
    }
}