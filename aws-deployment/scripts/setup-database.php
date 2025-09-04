<?php
/**
 * Database Setup Script for AWS Deployment
 * 
 * This script sets up the database schema and initial data for the
 * 3DCart NetSuite Integration system on AWS.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

// Load AWS configuration
$config = require __DIR__ . '/../config/aws-config.php';

class DatabaseSetup {
    private $pdo;
    private $config;
    
    public function __construct($config) {
        $this->config = $config;
        $this->connectDatabase();
    }
    
    private function connectDatabase() {
        $dbConfig = $this->config['database'];
        
        $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};charset={$dbConfig['charset']}";
        
        try {
            $this->pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options'] ?? []);
            echo "✓ Connected to MySQL server\n";
        } catch (PDOException $e) {
            die("✗ Database connection failed: " . $e->getMessage() . "\n");
        }
    }
    
    public function createDatabase() {
        $dbName = $this->config['database']['database'];
        
        try {
            $this->pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $this->pdo->exec("USE `{$dbName}`");
            echo "✓ Database '{$dbName}' created/selected\n";
        } catch (PDOException $e) {
            die("✗ Failed to create database: " . $e->getMessage() . "\n");
        }
    }
    
    public function createTables() {
        $sql = file_get_contents(__DIR__ . '/../../database/user_auth_schema.sql');
        
        // Remove database creation commands as we handle that separately
        $sql = preg_replace('/^-- CREATE DATABASE.*$/m', '', $sql);
        $sql = preg_replace('/^-- USE.*$/m', '', $sql);
        
        // Split SQL into individual statements
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        foreach ($statements as $statement) {
            if (empty($statement) || strpos($statement, '--') === 0) {
                continue;
            }
            
            try {
                $this->pdo->exec($statement);
            } catch (PDOException $e) {
                // Ignore "already exists" errors
                if (strpos($e->getMessage(), 'already exists') === false) {
                    echo "⚠ Warning executing statement: " . $e->getMessage() . "\n";
                }
            }
        }
        
        echo "✓ Database tables created/updated\n";
    }
    
    public function createAdminUser() {
        // Check if admin user already exists
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin'");
        $stmt->execute();
        
        if ($stmt->fetchColumn() > 0) {
            echo "✓ Admin user already exists\n";
            return;
        }
        
        // Create admin user
        $password = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("
            INSERT INTO users (username, email, password_hash, role, first_name, last_name) 
            VALUES ('admin', 'admin@lagunatools.com', ?, 'admin', 'System', 'Administrator')
        ");
        
        if ($stmt->execute([$password])) {
            echo "✓ Admin user created (username: admin, password: admin123)\n";
            echo "⚠ IMPORTANT: Change the default admin password immediately!\n";
        } else {
            echo "✗ Failed to create admin user\n";
        }
    }
    
    public function createIndexes() {
        $indexes = [
            "CREATE INDEX IF NOT EXISTS idx_users_username ON users(username)",
            "CREATE INDEX IF NOT EXISTS idx_users_email ON users(email)",
            "CREATE INDEX IF NOT EXISTS idx_users_active ON users(is_active)",
            "CREATE INDEX IF NOT EXISTS idx_sessions_user ON user_sessions(user_id)",
            "CREATE INDEX IF NOT EXISTS idx_sessions_expires ON user_sessions(expires_at)",
            "CREATE INDEX IF NOT EXISTS idx_activity_user ON user_activity_log(user_id)",
            "CREATE INDEX IF NOT EXISTS idx_activity_created ON user_activity_log(created_at)",
            "CREATE INDEX IF NOT EXISTS idx_integration_user ON integration_activity_log(user_id)",
            "CREATE INDEX IF NOT EXISTS idx_integration_created ON integration_activity_log(created_at)",
            "CREATE INDEX IF NOT EXISTS idx_integration_status ON integration_activity_log(status)",
        ];
        
        foreach ($indexes as $index) {
            try {
                $this->pdo->exec($index);
            } catch (PDOException $e) {
                // Ignore "already exists" errors
                if (strpos($e->getMessage(), 'already exists') === false) {
                    echo "⚠ Warning creating index: " . $e->getMessage() . "\n";
                }
            }
        }
        
        echo "✓ Database indexes created/updated\n";
    }
    
    public function optimizeDatabase() {
        $tables = ['users', 'user_sessions', 'user_activity_log', 'integration_activity_log'];
        
        foreach ($tables as $table) {
            try {
                $this->pdo->exec("OPTIMIZE TABLE `{$table}`");
            } catch (PDOException $e) {
                echo "⚠ Warning optimizing table {$table}: " . $e->getMessage() . "\n";
            }
        }
        
        echo "✓ Database tables optimized\n";
    }
    
    public function testConnection() {
        try {
            $stmt = $this->pdo->query("SELECT COUNT(*) as user_count FROM users");
            $result = $stmt->fetch();
            echo "✓ Database connection test successful (Users: {$result['user_count']})\n";
            return true;
        } catch (PDOException $e) {
            echo "✗ Database connection test failed: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function setupCleanupJobs() {
        // Create cleanup procedures if they don't exist
        $procedures = [
            "DROP PROCEDURE IF EXISTS CleanupExpiredSessions",
            "CREATE PROCEDURE CleanupExpiredSessions()
             BEGIN
                 DELETE FROM user_sessions WHERE expires_at < NOW() OR is_active = 0;
                 SELECT ROW_COUNT() as sessions_cleaned;
             END",
            
            "DROP PROCEDURE IF EXISTS CleanupOldActivityLogs",
            "CREATE PROCEDURE CleanupOldActivityLogs()
             BEGIN
                 DELETE FROM user_activity_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
                 DELETE FROM integration_activity_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
                 SELECT ROW_COUNT() as logs_cleaned;
             END",
        ];
        
        foreach ($procedures as $procedure) {
            try {
                $this->pdo->exec($procedure);
            } catch (PDOException $e) {
                echo "⚠ Warning creating procedure: " . $e->getMessage() . "\n";
            }
        }
        
        echo "✓ Cleanup procedures created\n";
    }
}

// Main execution
echo "=== 3DCart NetSuite Integration - Database Setup ===\n\n";

try {
    $setup = new DatabaseSetup($config);
    
    echo "Setting up database...\n";
    $setup->createDatabase();
    $setup->createTables();
    $setup->createIndexes();
    $setup->createAdminUser();
    $setup->setupCleanupJobs();
    $setup->optimizeDatabase();
    
    echo "\nTesting database connection...\n";
    if ($setup->testConnection()) {
        echo "\n✓ Database setup completed successfully!\n";
        
        echo "\nNext steps:\n";
        echo "1. Change the default admin password\n";
        echo "2. Configure your API credentials\n";
        echo "3. Test the application\n";
        echo "4. Set up monitoring and backups\n";
    } else {
        echo "\n✗ Database setup completed with errors. Please check the connection.\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "\n✗ Database setup failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== Database Setup Complete ===\n";