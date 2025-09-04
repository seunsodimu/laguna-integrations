-- Simplified User Authentication Database Schema
-- This schema provides user management and authentication for the 3DCart NetSuite Integration

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
    locked_until TIMESTAMP NULL
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
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
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
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
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
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_username ON users(username);
CREATE INDEX IF NOT EXISTS idx_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_role ON users(role);
CREATE INDEX IF NOT EXISTS idx_active ON users(is_active);

CREATE INDEX IF NOT EXISTS idx_user_id_sessions ON user_sessions(user_id);
CREATE INDEX IF NOT EXISTS idx_expires ON user_sessions(expires_at);
CREATE INDEX IF NOT EXISTS idx_active_sessions ON user_sessions(is_active);
CREATE INDEX IF NOT EXISTS idx_sessions_cleanup ON user_sessions(expires_at, is_active);

CREATE INDEX IF NOT EXISTS idx_user_id_activity ON user_activity_log(user_id);
CREATE INDEX IF NOT EXISTS idx_action ON user_activity_log(action);
CREATE INDEX IF NOT EXISTS idx_created_at_activity ON user_activity_log(created_at);

CREATE INDEX IF NOT EXISTS idx_user_id_integration ON integration_activity_log(user_id);
CREATE INDEX IF NOT EXISTS idx_activity_type ON integration_activity_log(activity_type);
CREATE INDEX IF NOT EXISTS idx_status ON integration_activity_log(status);
CREATE INDEX IF NOT EXISTS idx_created_at_integration ON integration_activity_log(created_at);
CREATE INDEX IF NOT EXISTS idx_order_id ON integration_activity_log(order_id);

-- Insert default admin user (password: admin123 - CHANGE THIS IN PRODUCTION!)
INSERT INTO users (username, email, password_hash, role, first_name, last_name) 
VALUES (
    'admin', 
    'admin@lagunatools.com', 
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 
    'admin', 
    'System', 
    'Administrator'
) ON DUPLICATE KEY UPDATE 
    password_hash = VALUES(password_hash),
    role = VALUES(role);

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