<?php
/**
 * Fix Admin Password Script
 */

require_once __DIR__ . '/vendor/autoload.php';

use PDO;
use PDOException;

echo "🔧 Fix Admin Password Script\n";
echo "============================\n\n";

try {
    // Load configuration
    $config = require __DIR__ . '/config/config.php';
    $dbConfig = $config['database'];
    
    // Connect to database
    $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    echo "✅ Connected to database\n\n";
    
    // Check current admin user
    echo "🔍 Checking current admin user...\n";
    $stmt = $pdo->prepare("SELECT id, username, email, password_hash FROM users WHERE username = 'admin'");
    $stmt->execute();
    $admin = $stmt->fetch();
    
    if ($admin) {
        echo "✅ Admin user found:\n";
        echo "   ID: " . $admin['id'] . "\n";
        echo "   Username: " . $admin['username'] . "\n";
        echo "   Email: " . $admin['email'] . "\n";
        echo "   Current hash: " . substr($admin['password_hash'], 0, 20) . "...\n\n";
    } else {
        echo "❌ Admin user not found\n";
        exit(1);
    }
    
    // Generate new password hash
    echo "🔐 Generating new password hash for 'admin123'...\n";
    $newPassword = 'admin123';
    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    
    echo "✅ New hash generated: " . substr($newHash, 0, 20) . "...\n\n";
    
    // Test the new hash
    echo "🧪 Testing new hash...\n";
    if (password_verify($newPassword, $newHash)) {
        echo "✅ Hash verification successful\n\n";
    } else {
        echo "❌ Hash verification failed\n";
        exit(1);
    }
    
    // Update admin password
    echo "💾 Updating admin password...\n";
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = 'admin'");
    $stmt->execute([$newHash]);
    
    echo "✅ Admin password updated successfully\n\n";
    
    // Verify update
    echo "🔍 Verifying update...\n";
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE username = 'admin'");
    $stmt->execute();
    $updatedAdmin = $stmt->fetch();
    
    if ($updatedAdmin && password_verify($newPassword, $updatedAdmin['password_hash'])) {
        echo "✅ Password update verified successfully\n\n";
    } else {
        echo "❌ Password update verification failed\n";
        exit(1);
    }
    
    echo "🎉 Admin password fixed successfully!\n";
    echo "=====================================\n\n";
    
    echo "📋 Login Credentials:\n";
    echo "Username: admin\n";
    echo "Password: admin123\n\n";
    
    echo "🔒 IMPORTANT: Change this password after first login!\n";
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>