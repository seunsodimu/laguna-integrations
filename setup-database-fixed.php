<?php
/**
 * Fixed Database Setup Script
 * 
 * Sets up the database for user authentication and management.
 */

require_once __DIR__ . '/vendor/autoload.php';

use PDO;
use PDOException;

echo "🗄️ Fixed Database Setup Script\n";
echo "==============================\n\n";

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
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
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
    
    // Execute schema step by step
    echo "🔧 Creating database schema...\n";
    
    // Step 1: Create users table
    echo "  📋 Creating users table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(100) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
            first_name VARCHAR(50),
            last_name VARCHAR(50),
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL,
            failed_login_attempts INT DEFAULT 0,
            locked_until TIMESTAMP NULL
        )
    ");
    echo "  ✅ Users table created\n";
    
    // Step 2: Create user_sessions table
    echo "  📋 Creating user_sessions table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_sessions (
            id VARCHAR(128) PRIMARY KEY,
            user_id INT NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NULL,
            is_active BOOLEAN DEFAULT TRUE,
            
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    echo "  ✅ User sessions table created\n";
    
    // Step 3: Create user_activity_log table
    echo "  📋 Creating user_activity_log table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_activity_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            action VARCHAR(100) NOT NULL,
            description TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )
    ");
    echo "  ✅ User activity log table created\n";
    
    // Step 4: Create integration_activity_log table
    echo "  📋 Creating integration_activity_log table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS integration_activity_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            activity_type ENUM('order_processing', 'customer_creation', 'manual_upload', 'configuration_change') NOT NULL,
            order_id VARCHAR(50),
            customer_id VARCHAR(50),
            status ENUM('success', 'failure', 'pending') NOT NULL,
            details JSON,
            error_message TEXT,
            processing_time_ms INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )
    ");
    echo "  ✅ Integration activity log table created\n";
    
    // Step 5: Create indexes
    echo "  📋 Creating indexes...\n";
    
    $indexes = [
        "CREATE INDEX IF NOT EXISTS idx_username ON users(username)",
        "CREATE INDEX IF NOT EXISTS idx_email ON users(email)",
        "CREATE INDEX IF NOT EXISTS idx_role ON users(role)",
        "CREATE INDEX IF NOT EXISTS idx_active ON users(is_active)",
        
        "CREATE INDEX IF NOT EXISTS idx_user_id_sessions ON user_sessions(user_id)",
        "CREATE INDEX IF NOT EXISTS idx_expires ON user_sessions(expires_at)",
        "CREATE INDEX IF NOT EXISTS idx_active_sessions ON user_sessions(is_active)",
        "CREATE INDEX IF NOT EXISTS idx_sessions_cleanup ON user_sessions(expires_at, is_active)",
        
        "CREATE INDEX IF NOT EXISTS idx_user_id_activity ON user_activity_log(user_id)",
        "CREATE INDEX IF NOT EXISTS idx_action ON user_activity_log(action)",
        "CREATE INDEX IF NOT EXISTS idx_created_at_activity ON user_activity_log(created_at)",
        
        "CREATE INDEX IF NOT EXISTS idx_user_id_integration ON integration_activity_log(user_id)",
        "CREATE INDEX IF NOT EXISTS idx_activity_type ON integration_activity_log(activity_type)",
        "CREATE INDEX IF NOT EXISTS idx_status ON integration_activity_log(status)",
        "CREATE INDEX IF NOT EXISTS idx_created_at_integration ON integration_activity_log(created_at)",
        "CREATE INDEX IF NOT EXISTS idx_order_id ON integration_activity_log(order_id)"
    ];
    
    foreach ($indexes as $index) {
        try {
            $pdo->exec($index);
        } catch (PDOException $e) {
            echo "    ⚠️ Index creation warning: " . $e->getMessage() . "\n";
        }
    }
    echo "  ✅ Indexes created\n";
    
    // Step 6: Insert default admin user
    echo "  📋 Creating default admin user...\n";
    $pdo->exec("
        INSERT INTO users (username, email, password_hash, role, first_name, last_name) 
        VALUES (
            'admin', 
            'admin@lagunatools.com', 
            '$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 
            'admin', 
            'System', 
            'Administrator'
        ) ON DUPLICATE KEY UPDATE 
            password_hash = VALUES(password_hash),
            role = VALUES(role)
    ");
    echo "  ✅ Default admin user created\n";
    
    // Step 7: Create views
    echo "  📋 Creating views...\n";
    
    $pdo->exec("
        CREATE OR REPLACE VIEW active_users AS
        SELECT 
            id,
            username,
            email,
            role,
            first_name,
            last_name,
            created_at,
            last_login,
            CASE 
                WHEN locked_until IS NOT NULL AND locked_until > NOW() THEN 'locked'
                WHEN is_active = 1 THEN 'active'
                ELSE 'inactive'
            END as status
        FROM users
        WHERE is_active = 1
    ");
    
    $pdo->exec("
        CREATE OR REPLACE VIEW user_session_summary AS
        SELECT 
            u.id,
            u.username,
            u.email,
            u.role,
            COUNT(s.id) as active_sessions,
            MAX(s.created_at) as last_session_start
        FROM users u
        LEFT JOIN user_sessions s ON u.id = s.user_id AND s.is_active = 1 AND s.expires_at > NOW()
        GROUP BY u.id, u.username, u.email, u.role
    ");
    echo "  ✅ Views created\n";
    
    echo "\n📊 Schema creation completed successfully!\n\n";
    
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
    
    // Check views
    echo "\n📊 Checking views...\n";
    
    $views = ['active_users', 'user_session_summary'];
    $result = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'");
    $existingViews = [];
    while ($row = $result->fetch(PDO::FETCH_NUM)) {
        $existingViews[] = $row[0];
    }
    
    foreach ($views as $view) {
        if (in_array($view, $existingViews)) {
            echo "  ✅ View '$view' exists\n";
        } else {
            echo "  ❌ View '$view' missing\n";
        }
    }
    
    echo "\n🎉 Database setup completed successfully!\n";
    echo "=========================================\n\n";
    
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