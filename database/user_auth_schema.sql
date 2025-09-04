-- User Authentication Database Schema
-- This schema provides user management and authentication for the 3DCart NetSuite Integration

-- Create database (run this separately if needed)
-- CREATE DATABASE IF NOT EXISTS laguna_integration;
-- USE laguna_integration;

-- Users table
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
    locked_until TIMESTAMP NULL,
    
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_active (is_active)
);

-- User sessions table
CREATE TABLE IF NOT EXISTS user_sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_expires (expires_at),
    INDEX idx_active (is_active)
);

-- User activity log table
CREATE TABLE IF NOT EXISTS user_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
);

-- Integration activity log table (optional - for tracking integration activities)
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
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_activity_type (activity_type),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_order_id (order_id)
);

-- Insert default admin user (password: admin123 - CHANGE THIS IN PRODUCTION!)
INSERT INTO users (username, email, password_hash, role, first_name, last_name) 
VALUES (
    'admin', 
    'admin@lagunatools.com', 
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: admin123
    'admin', 
    'System', 
    'Administrator'
) ON DUPLICATE KEY UPDATE 
    password_hash = VALUES(password_hash),
    role = VALUES(role);

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_sessions_cleanup ON user_sessions(expires_at, is_active);
CREATE INDEX IF NOT EXISTS idx_activity_cleanup ON user_activity_log(created_at);
CREATE INDEX IF NOT EXISTS idx_integration_cleanup ON integration_activity_log(created_at);

-- Create a view for active users
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
WHERE is_active = 1;

-- Create a view for user session summary
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
GROUP BY u.id, u.username, u.email, u.role;

-- Cleanup procedures (run these periodically)
DELIMITER //

-- Procedure to clean up expired sessions
CREATE PROCEDURE IF NOT EXISTS CleanupExpiredSessions()
BEGIN
    DELETE FROM user_sessions 
    WHERE expires_at < NOW() OR is_active = 0;
    
    SELECT ROW_COUNT() as sessions_cleaned;
END //

-- Procedure to clean up old activity logs (keep last 90 days)
CREATE PROCEDURE IF NOT EXISTS CleanupOldActivityLogs()
BEGIN
    DELETE FROM user_activity_log 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
    
    DELETE FROM integration_activity_log 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
    
    SELECT ROW_COUNT() as logs_cleaned;
END //

-- Procedure to unlock users after lockout period
CREATE PROCEDURE IF NOT EXISTS UnlockUsers()
BEGIN
    UPDATE users 
    SET locked_until = NULL, failed_login_attempts = 0
    WHERE locked_until IS NOT NULL AND locked_until < NOW();
    
    SELECT ROW_COUNT() as users_unlocked;
END //

DELIMITER ;

-- Grant permissions (adjust as needed for your setup)
-- GRANT SELECT, INSERT, UPDATE, DELETE ON laguna_integration.* TO 'integration_user'@'localhost';
-- FLUSH PRIVILEGES;

-- Display setup completion message
SELECT 'User authentication schema created successfully!' as message;
SELECT 'Default admin user created with username: admin, password: admin123' as admin_info;
SELECT 'IMPORTANT: Change the default admin password immediately!' as security_warning;