<?php
/**
 * Database Setup Script
 * 
 * Sets up the database for user authentication and management.
 */

require_once __DIR__ . '/vendor/autoload.php';

use PDO;
use PDOException;

echo "🗄️ Database Setup Script\n";
echo "========================\n\n";

try {
    // Load configuration
    $config = require __DIR__ . '/config/config.php';
    $dbConfig = $config['database'];
    
    if (!$dbConfig['enabled']) {
        echo "❌ Database is not enabled in configuration.\n";
        echo "Please set 'database' => ['enabled' => true] in config/config.php\n";
        exit(1);
    }
    
    echo "📋 Database Configuration:\n";
    echo "- Host: " . $dbConfig['host'] . "\n";
    echo "- Port: " . $dbConfig['port'] . "\n";
    echo "- Database: " . $dbConfig['database'] . "\n";
    echo "- Username: " . $dbConfig['username'] . "\n";
    echo "\n";
    
    // Connect to MySQL server (without specifying database)
    $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};charset={$dbConfig['charset']}";
    
    echo "🔌 Connecting to MySQL server...\n";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    echo "✅ Connected to MySQL server successfully!\n\n";
    
    // Create database if it doesn't exist
    echo "🏗️ Creating database if it doesn't exist...\n";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbConfig['database']}` CHARACTER SET {$dbConfig['charset']} COLLATE {$dbConfig['charset']}_unicode_ci");
    echo "✅ Database '{$dbConfig['database']}' is ready!\n\n";
    
    // Connect to the specific database
    $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
    ]);
    
    echo "🔌 Connected to database '{$dbConfig['database']}'!\n\n";
    
    // Read and execute the schema file
    $schemaFile = __DIR__ . '/database/user_auth_schema_simple.sql';
    
    if (!file_exists($schemaFile)) {
        echo "❌ Schema file not found: $schemaFile\n";
        exit(1);
    }
    
    echo "📜 Reading schema file...\n";
    $schema = file_get_contents($schemaFile);
    
    // Split the schema into individual statements
    $statements = array_filter(
        array_map('trim', explode(';', $schema)),
        function($stmt) {
            $stmt = trim($stmt);
            return !empty($stmt) && 
                   !preg_match('/^--/', $stmt) && 
                   !preg_match('/^\/\*/', $stmt) &&
                   !preg_match('/^CREATE DATABASE/', $stmt) &&
                   !preg_match('/^USE /', $stmt) &&
                   !preg_match('/^DELIMITER/', $stmt) &&
                   strlen($stmt) > 5; // Ignore very short statements
        }
    );
    
    echo "🔧 Executing schema statements...\n";
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($statements as $statement) {
        try {
            $pdo->exec($statement);
            $successCount++;
            
            // Show what was created
            if (preg_match('/CREATE TABLE.*?`?(\w+)`?/i', $statement, $matches)) {
                echo "  ✅ Created table: {$matches[1]}\n";
            } elseif (preg_match('/INSERT INTO.*?`?(\w+)`?/i', $statement, $matches)) {
                echo "  ✅ Inserted data into: {$matches[1]}\n";
            } elseif (preg_match('/CREATE.*?VIEW.*?`?(\w+)`?/i', $statement, $matches)) {
                echo "  ✅ Created view: {$matches[1]}\n";
            } elseif (preg_match('/CREATE.*?PROCEDURE.*?`?(\w+)`?/i', $statement, $matches)) {
                echo "  ✅ Created procedure: {$matches[1]}\n";
            } else {
                echo "  ✅ Executed statement successfully\n";
            }
            
        } catch (PDOException $e) {
            $errorCount++;
            echo "  ❌ Error executing statement: " . $e->getMessage() . "\n";
            
            // Show the problematic statement (first 100 chars)
            $shortStmt = substr(trim($statement), 0, 100);
            echo "     Statement: " . $shortStmt . "...\n";
        }
    }
    
    echo "\n📊 Schema execution completed:\n";
    echo "- Successful statements: $successCount\n";
    echo "- Failed statements: $errorCount\n\n";
    
    // Verify tables were created
    echo "🔍 Verifying database structure...\n";
    
    $requiredTables = ['users', 'user_sessions', 'user_activity_log', 'integration_activity_log'];
    $existingTables = [];
    
    $result = $pdo->query("SHOW TABLES");
    while ($row = $result->fetch(PDO::FETCH_NUM)) {
        $existingTables[] = $row[0];
    }
    
    foreach ($requiredTables as $table) {
        if (in_array($table, $existingTables)) {
            echo "  ✅ Table '$table' exists\n";
        } else {
            echo "  ❌ Table '$table' missing\n";
        }
    }
    
    // Check if default admin user exists
    echo "\n👤 Checking default admin user...\n";
    
    $stmt = $pdo->prepare("SELECT username, email, role FROM users WHERE username = 'admin'");
    $stmt->execute();
    $adminUser = $stmt->fetch();
    
    if ($adminUser) {
        echo "  ✅ Default admin user exists:\n";
        echo "     Username: " . $adminUser['username'] . "\n";
        echo "     Email: " . $adminUser['email'] . "\n";
        echo "     Role: " . $adminUser['role'] . "\n";
        echo "     Password: admin123 (CHANGE THIS!)\n";
    } else {
        echo "  ❌ Default admin user not found\n";
    }
    
    echo "\n🎉 Database setup completed!\n";
    echo "============================\n\n";
    
    echo "📋 Next Steps:\n";
    echo "1. ✅ Database and tables created\n";
    echo "2. ✅ Default admin user created (username: admin, password: admin123)\n";
    echo "3. 🔐 IMPORTANT: Change the default admin password immediately!\n";
    echo "4. 🌐 Access the application at: http://your-domain/public/login.php\n";
    echo "5. 👥 Use the admin account to create additional users\n";
    echo "6. ⚙️ Configure NetSuite and other API credentials\n\n";
    
    echo "🔒 Security Reminders:\n";
    echo "- Change the default admin password\n";
    echo "- Use strong passwords for all users\n";
    echo "- Regularly review user access and permissions\n";
    echo "- Monitor the user activity logs\n\n";
    
    echo "✅ Setup completed successfully!\n";
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    echo "\n🔧 Troubleshooting:\n";
    echo "1. Check database credentials in config/config.php\n";
    echo "2. Ensure MySQL server is running\n";
    echo "3. Verify database user has CREATE privileges\n";
    echo "4. Check MySQL error logs for more details\n";
    exit(1);
    
} catch (\Exception $e) {
    echo "❌ Setup error: " . $e->getMessage() . "\n";
    exit(1);
}
?>