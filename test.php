<?php
/**
 * Simple Test Runner
 * 
 * A basic test script to verify the installation is working correctly.
 * This can be run from the command line or accessed via web browser.
 */

require_once __DIR__ . '/vendor/autoload.php';

// Set timezone
date_default_timezone_set('America/New_York');

echo "=== 3DCart to NetSuite Integration - Installation Test ===\n\n";

$tests = [];
$passed = 0;
$failed = 0;

// Test 1: Check if autoloader works
try {
    $controller = new \Laguna\Integration\Controllers\WebhookController();
    $tests[] = ['✅ Autoloader', 'Working correctly'];
    $passed++;
} catch (Exception $e) {
    $tests[] = ['❌ Autoloader', 'Failed: ' . $e->getMessage()];
    $failed++;
}

// Test 2: Check configuration
try {
    $config = require __DIR__ . '/config/config.php';
    if (is_array($config) && isset($config['app'])) {
        $tests[] = ['✅ Configuration', 'Loaded successfully'];
        $passed++;
    } else {
        $tests[] = ['❌ Configuration', 'Invalid format'];
        $failed++;
    }
} catch (Exception $e) {
    $tests[] = ['❌ Configuration', 'Failed to load: ' . $e->getMessage()];
    $failed++;
}

// Test 3: Check credentials file
try {
    $credentials = require __DIR__ . '/config/credentials.php';
    if (is_array($credentials) && isset($credentials['3dcart'], $credentials['netsuite'], $credentials['sendgrid'])) {
        $tests[] = ['✅ Credentials', 'File exists and has required sections'];
        $passed++;
    } else {
        $tests[] = ['❌ Credentials', 'Missing required sections'];
        $failed++;
    }
} catch (Exception $e) {
    $tests[] = ['❌ Credentials', 'Failed to load: ' . $e->getMessage()];
    $failed++;
}

// Test 4: Check directories
$logsWritable = is_dir(__DIR__ . '/logs') && is_writable(__DIR__ . '/logs');
$uploadsWritable = is_dir(__DIR__ . '/uploads') && is_writable(__DIR__ . '/uploads');

if ($logsWritable && $uploadsWritable) {
    $tests[] = ['✅ Directories', 'Logs and uploads directories are writable'];
    $passed++;
} else {
    $issues = [];
    if (!$logsWritable) $issues[] = 'logs directory not writable';
    if (!$uploadsWritable) $issues[] = 'uploads directory not writable';
    $tests[] = ['❌ Directories', 'Issues: ' . implode(', ', $issues)];
    $failed++;
}

// Test 5: Check PHP extensions
$requiredExtensions = ['curl', 'json', 'openssl', 'mbstring'];
$missingExtensions = [];

foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $missingExtensions[] = $ext;
    }
}

if (empty($missingExtensions)) {
    $tests[] = ['✅ PHP Extensions', 'All required extensions loaded'];
    $passed++;
} else {
    $tests[] = ['❌ PHP Extensions', 'Missing: ' . implode(', ', $missingExtensions)];
    $failed++;
}

// Test 6: Check Composer dependencies
try {
    $guzzleExists = class_exists('GuzzleHttp\Client');
    $monologExists = class_exists('Monolog\Logger');
    $spreadsheetExists = class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet');
    
    if ($guzzleExists && $monologExists && $spreadsheetExists) {
        $tests[] = ['✅ Dependencies', 'Key Composer packages available'];
        $passed++;
    } else {
        $missing = [];
        if (!$guzzleExists) $missing[] = 'GuzzleHttp';
        if (!$monologExists) $missing[] = 'Monolog';
        if (!$spreadsheetExists) $missing[] = 'PhpSpreadsheet';
        $tests[] = ['❌ Dependencies', 'Missing: ' . implode(', ', $missing)];
        $failed++;
    }
} catch (Exception $e) {
    $tests[] = ['❌ Dependencies', 'Error checking: ' . $e->getMessage()];
    $failed++;
}

// Test 7: Check public files
$publicFiles = ['index.php', 'webhook.php', 'status.php', 'upload.php'];
$missingFiles = [];

foreach ($publicFiles as $file) {
    if (!file_exists(__DIR__ . '/public/' . $file)) {
        $missingFiles[] = $file;
    }
}

if (empty($missingFiles)) {
    $tests[] = ['✅ Public Files', 'All required files exist'];
    $passed++;
} else {
    $tests[] = ['❌ Public Files', 'Missing: ' . implode(', ', $missingFiles)];
    $failed++;
}

// Test 8: Test logger
try {
    $logger = \Laguna\Integration\Utils\Logger::getInstance();
    $logger->info('Installation test log entry');
    $tests[] = ['✅ Logger', 'Logger working correctly'];
    $passed++;
} catch (Exception $e) {
    $tests[] = ['❌ Logger', 'Failed: ' . $e->getMessage()];
    $failed++;
}

// Display results
foreach ($tests as $test) {
    printf("%-20s %s\n", $test[0], $test[1]);
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "Test Results: {$passed} passed, {$failed} failed\n";

if ($failed === 0) {
    echo "🎉 All tests passed! Installation appears to be working correctly.\n";
    echo "\nNext steps:\n";
    echo "1. Configure your API credentials in config/credentials.php\n";
    echo "2. Visit the status page to test API connections\n";
    echo "3. Set up your 3DCart webhook to point to webhook.php\n";
} else {
    echo "⚠️  Some tests failed. Please review the issues above.\n";
    echo "\nCommon solutions:\n";
    echo "- Run 'composer install' to install dependencies\n";
    echo "- Check file permissions on logs/ and uploads/ directories\n";
    echo "- Ensure all required PHP extensions are installed\n";
    echo "- Copy config/credentials.example.php to config/credentials.php\n";
}

echo "\nFor detailed setup instructions, see documentation/setup/SETUP.md\n";
echo "For API credential setup, see documentation/setup/API_CREDENTIALS.md\n";

// If running via web browser, add some HTML formatting
if (isset($_SERVER['HTTP_HOST'])) {
    echo "\n<style>body{font-family:monospace;white-space:pre;}</style>";
}
?>