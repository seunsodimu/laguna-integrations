<?php
/**
 * Test URL Helper Functionality
 */

require_once __DIR__ . '/vendor/autoload.php';

use Laguna\Integration\Utils\UrlHelper;

echo "🔗 URL Helper Test\n";
echo "==================\n\n";

// Simulate different server environments
$testCases = [
    [
        'SCRIPT_NAME' => '/laguna_3dcart_netsuite/public/index.php',
        'DOCUMENT_ROOT' => 'c:/xampp/htdocs',
        'description' => 'XAMPP Local Development'
    ],
    [
        'SCRIPT_NAME' => '/public/index.php',
        'DOCUMENT_ROOT' => '/var/www/html/laguna_3dcart_netsuite',
        'description' => 'Production Server (subdirectory)'
    ],
    [
        'SCRIPT_NAME' => '/index.php',
        'DOCUMENT_ROOT' => '/var/www/html',
        'description' => 'Production Server (document root)'
    ]
];

foreach ($testCases as $i => $testCase) {
    echo "Test Case " . ($i + 1) . ": " . $testCase['description'] . "\n";
    echo str_repeat('-', 50) . "\n";
    
    // Set up environment
    $_SERVER['SCRIPT_NAME'] = $testCase['SCRIPT_NAME'];
    $_SERVER['DOCUMENT_ROOT'] = $testCase['DOCUMENT_ROOT'];
    
    try {
        echo "Base URL: " . UrlHelper::getBaseUrl() . "\n";
        echo "Public URL: " . UrlHelper::getPublicUrl() . "\n";
        echo "Login URL: " . UrlHelper::url('login.php') . "\n";
        echo "Index URL: " . UrlHelper::url('index.php') . "\n";
        echo "Project URL (docs): " . UrlHelper::projectUrl('documentation/setup/SETUP.md') . "\n";
        echo "Is Subdirectory: " . (UrlHelper::isSubdirectory() ? 'Yes' : 'No') . "\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

echo "🎯 Current Environment Test\n";
echo "===========================\n";

// Test current environment
try {
    echo "Current Base URL: " . UrlHelper::getBaseUrl() . "\n";
    echo "Current Public URL: " . UrlHelper::getPublicUrl() . "\n";
    echo "Current Login URL: " . UrlHelper::url('login.php') . "\n";
    echo "Current Index URL: " . UrlHelper::url('index.php') . "\n";
    echo "Current Project URL: " . UrlHelper::projectUrl('logs/') . "\n";
    echo "Is Subdirectory: " . (UrlHelper::isSubdirectory() ? 'Yes' : 'No') . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n✅ URL Helper test completed!\n";
?>