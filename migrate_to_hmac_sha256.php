<?php
/**
 * Migration script to update credentials.php with HMAC-SHA256 signature method
 */

echo "<h1>NetSuite HMAC-SHA256 Migration Script</h1>\n";
echo "<pre>\n";

$credentialsFile = __DIR__ . '/config/credentials.php';
$backupFile = __DIR__ . '/config/credentials.php.backup.' . date('Y-m-d_H-i-s');

try {
    // Check if credentials file exists
    if (!file_exists($credentialsFile)) {
        echo "❌ Error: credentials.php file not found at {$credentialsFile}\n";
        echo "Please copy credentials.example.php to credentials.php first.\n";
        exit(1);
    }
    
    // Read current credentials
    $credentials = require $credentialsFile;
    
    // Check if NetSuite configuration exists
    if (!isset($credentials['netsuite'])) {
        echo "❌ Error: NetSuite configuration not found in credentials.php\n";
        exit(1);
    }
    
    // Check if signature_method is already set
    if (isset($credentials['netsuite']['signature_method'])) {
        $currentMethod = $credentials['netsuite']['signature_method'];
        echo "ℹ️  Signature method already configured: {$currentMethod}\n";
        
        if ($currentMethod === 'HMAC-SHA256') {
            echo "✅ Already using HMAC-SHA256. No migration needed.\n";
            exit(0);
        } else {
            echo "⚠️  Currently using: {$currentMethod}\n";
            echo "🔄 Updating to HMAC-SHA256...\n";
        }
    } else {
        echo "🔄 Adding HMAC-SHA256 signature method to configuration...\n";
    }
    
    // Create backup
    if (!copy($credentialsFile, $backupFile)) {
        echo "❌ Error: Could not create backup file\n";
        exit(1);
    }
    echo "✅ Backup created: {$backupFile}\n";
    
    // Update configuration
    $credentials['netsuite']['signature_method'] = 'HMAC-SHA256';
    
    // Read the original file content
    $originalContent = file_get_contents($credentialsFile);
    
    // Check if signature_method line already exists
    if (strpos($originalContent, 'signature_method') !== false) {
        // Replace existing signature_method line
        $updatedContent = preg_replace(
            "/('signature_method'\s*=>\s*')[^']*(')/",
            '${1}HMAC-SHA256${2}',
            $originalContent
        );
    } else {
        // Add signature_method line before the closing bracket of netsuite array
        $pattern = "/(\s*'rest_api_version'\s*=>\s*'[^']*',?\s*\n)(\s*\],)/";
        $replacement = "$1        'signature_method' => 'HMAC-SHA256', // Options: 'HMAC-SHA256' (recommended) or 'HMAC-SHA1' (legacy)\n$2";
        $updatedContent = preg_replace($pattern, $replacement, $originalContent);
        
        // If the above pattern didn't match, try a more general approach
        if ($updatedContent === $originalContent) {
            $pattern = "/(\s*\],\s*\/\/.*NetSuite.*\n)/";
            $replacement = "        'signature_method' => 'HMAC-SHA256', // Options: 'HMAC-SHA256' (recommended) or 'HMAC-SHA1' (legacy)\n$1";
            $updatedContent = preg_replace($pattern, $replacement, $originalContent);
        }
    }
    
    // Write updated content
    if (file_put_contents($credentialsFile, $updatedContent) === false) {
        echo "❌ Error: Could not write updated credentials file\n";
        echo "Restoring backup...\n";
        copy($backupFile, $credentialsFile);
        exit(1);
    }
    
    echo "✅ Configuration updated successfully!\n";
    
    // Verify the update
    $updatedCredentials = require $credentialsFile;
    $newMethod = $updatedCredentials['netsuite']['signature_method'] ?? 'NOT_SET';
    
    if ($newMethod === 'HMAC-SHA256') {
        echo "✅ Verification passed: signature_method = {$newMethod}\n";
        
        // Test the connection
        echo "\n🔍 Testing NetSuite connection with HMAC-SHA256...\n";
        
        require_once __DIR__ . '/vendor/autoload.php';
        
        $service = new \Laguna\Integration\Services\NetSuiteService();
        $result = $service->testConnection();
        
        if ($result['success']) {
            echo "✅ Connection test successful!\n";
            echo "   Status Code: " . $result['status_code'] . "\n";
            echo "   Response Time: " . $result['response_time'] . "\n";
            echo "\n🎉 Migration completed successfully!\n";
            echo "\n📋 Summary:\n";
            echo "   • Signature method updated to HMAC-SHA256\n";
            echo "   • Backup created: " . basename($backupFile) . "\n";
            echo "   • Connection test passed\n";
            echo "   • System is ready for production\n";
        } else {
            echo "⚠️  Connection test failed: " . $result['error'] . "\n";
            echo "\n🔧 Troubleshooting:\n";
            echo "   1. Verify your NetSuite integration supports HMAC-SHA256\n";
            echo "   2. Check that your credentials are still valid\n";
            echo "   3. If needed, you can rollback by setting:\n";
            echo "      'signature_method' => 'HMAC-SHA1'\n";
            echo "\n📁 Backup available at: {$backupFile}\n";
        }
    } else {
        echo "❌ Verification failed: signature_method = {$newMethod}\n";
        echo "Restoring backup...\n";
        copy($backupFile, $credentialsFile);
    }
    
} catch (Exception $e) {
    echo "❌ Error during migration: " . $e->getMessage() . "\n";
    
    if (isset($backupFile) && file_exists($backupFile)) {
        echo "Restoring backup...\n";
        copy($backupFile, $credentialsFile);
    }
    
    exit(1);
}

echo "\n=== Migration Complete ===\n";
echo "</pre>\n";
?>