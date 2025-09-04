<?php

namespace Laguna\Integration\Services;

use PDO;
use PDOException;
use Laguna\Integration\Utils\Logger;

/**
 * User Authentication Service
 * 
 * Handles user authentication, session management, and user administration
 * for the 3DCart to NetSuite integration system.
 */
class AuthService {
    private $pdo;
    private $logger;
    private $config;
    
    // Security constants
    const MAX_LOGIN_ATTEMPTS = 5;
    const LOCKOUT_DURATION = 30; // minutes
    const SESSION_LIFETIME = 8; // hours
    const PASSWORD_MIN_LENGTH = 8;
    
    public function __construct() {
        $this->logger = Logger::getInstance();
        $this->config = require __DIR__ . '/../../config/config.php';
        $this->initializeDatabase();
    }
    
    /**
     * Initialize database connection
     */
    private function initializeDatabase() {
        try {
            $dbConfig = $this->config['database'];
            
            if (!$dbConfig['enabled']) {
                throw new \Exception('Database is not enabled in configuration');
            }
            
            $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
            
            $this->pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            
            $this->logger->info('Database connection established for authentication');
            
        } catch (PDOException $e) {
            $this->logger->error('Failed to connect to database for authentication', [
                'error' => $e->getMessage()
            ]);
            throw new \Exception('Database connection failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Authenticate user login
     */
    public function login($username, $password, $rememberMe = false) {
        try {
            // Check if user exists and is active
            $user = $this->getUserByUsername($username);
            
            if (!$user) {
                $this->logActivity(null, 'login_failed', "Login attempt with invalid username: $username");
                return ['success' => false, 'error' => 'Invalid username or password'];
            }
            
            // Check if user is locked
            if ($this->isUserLocked($user)) {
                $this->logActivity($user['id'], 'login_blocked', 'Login blocked - user is locked');
                return ['success' => false, 'error' => 'Account is temporarily locked. Please try again later.'];
            }
            
            // Verify password
            if (!password_verify($password, $user['password_hash'])) {
                $this->incrementFailedAttempts($user['id']);
                $this->logActivity($user['id'], 'login_failed', 'Invalid password');
                return ['success' => false, 'error' => 'Invalid username or password'];
            }
            
            // Reset failed attempts on successful login
            $this->resetFailedAttempts($user['id']);
            
            // Create session
            $sessionId = $this->createSession($user['id'], $rememberMe);
            
            // Update last login
            $this->updateLastLogin($user['id']);
            
            $this->logActivity($user['id'], 'login_success', 'User logged in successfully');
            
            return [
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name']
                ],
                'session_id' => $sessionId
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Login error', [
                'username' => $username,
                'error' => $e->getMessage()
            ]);
            return ['success' => false, 'error' => 'Login failed due to system error'];
        }
    }
    
    /**
     * Logout user
     */
    public function logout($sessionId) {
        try {
            if ($sessionId) {
                $userId = $this->getUserIdFromSession($sessionId);
                $this->destroySession($sessionId);
                
                if ($userId) {
                    $this->logActivity($userId, 'logout', 'User logged out');
                }
            }
            
            return ['success' => true];
            
        } catch (\Exception $e) {
            $this->logger->error('Logout error', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);
            return ['success' => false, 'error' => 'Logout failed'];
        }
    }
    
    /**
     * Validate session and get user info
     */
    public function validateSession($sessionId) {
        try {
            if (!$sessionId) {
                return null;
            }
            
            $stmt = $this->pdo->prepare("
                SELECT u.*, s.expires_at 
                FROM users u 
                JOIN user_sessions s ON u.id = s.user_id 
                WHERE s.id = ? AND s.is_active = 1 AND s.expires_at > NOW() AND u.is_active = 1
            ");
            $stmt->execute([$sessionId]);
            $result = $stmt->fetch();
            
            if ($result) {
                // Extend session if it's close to expiring
                $this->extendSessionIfNeeded($sessionId);
                
                return [
                    'id' => $result['id'],
                    'username' => $result['username'],
                    'email' => $result['email'],
                    'role' => $result['role'],
                    'first_name' => $result['first_name'],
                    'last_name' => $result['last_name']
                ];
            }
            
            return null;
            
        } catch (\Exception $e) {
            $this->logger->error('Session validation error', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Create new user (admin only)
     */
    public function createUser($userData, $createdByUserId) {
        try {
            // Validate required fields
            $required = ['username', 'email', 'password', 'role'];
            foreach ($required as $field) {
                if (empty($userData[$field])) {
                    return ['success' => false, 'error' => "Field '$field' is required"];
                }
            }
            
            // Validate password strength
            if (strlen($userData['password']) < self::PASSWORD_MIN_LENGTH) {
                return ['success' => false, 'error' => 'Password must be at least ' . self::PASSWORD_MIN_LENGTH . ' characters long'];
            }
            
            // Check if username or email already exists
            if ($this->getUserByUsername($userData['username'])) {
                return ['success' => false, 'error' => 'Username already exists'];
            }
            
            if ($this->getUserByEmail($userData['email'])) {
                return ['success' => false, 'error' => 'Email already exists'];
            }
            
            // Hash password
            $passwordHash = password_hash($userData['password'], PASSWORD_DEFAULT);
            
            // Insert user
            $stmt = $this->pdo->prepare("
                INSERT INTO users (username, email, password_hash, role, first_name, last_name, is_active) 
                VALUES (?, ?, ?, ?, ?, ?, 1)
            ");
            
            $stmt->execute([
                $userData['username'],
                $userData['email'],
                $passwordHash,
                $userData['role'],
                $userData['first_name'] ?? '',
                $userData['last_name'] ?? ''
            ]);
            
            $userId = $this->pdo->lastInsertId();
            
            $this->logActivity($createdByUserId, 'user_created', "Created user: {$userData['username']} (ID: $userId)");
            
            return [
                'success' => true,
                'user_id' => $userId,
                'message' => 'User created successfully'
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('User creation error', [
                'user_data' => $userData,
                'error' => $e->getMessage()
            ]);
            return ['success' => false, 'error' => 'Failed to create user: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get all users (admin only)
     */
    public function getAllUsers() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, username, email, role, first_name, last_name, is_active, 
                       created_at, last_login, failed_login_attempts,
                       CASE 
                           WHEN locked_until IS NOT NULL AND locked_until > NOW() THEN 'locked'
                           WHEN is_active = 1 THEN 'active'
                           ELSE 'inactive'
                       END as status
                FROM users 
                ORDER BY created_at DESC
            ");
            $stmt->execute();
            
            return $stmt->fetchAll();
            
        } catch (\Exception $e) {
            $this->logger->error('Error fetching users', ['error' => $e->getMessage()]);
            return [];
        }
    }
    
    /**
     * Update user (admin only)
     */
    public function updateUser($userId, $userData, $updatedByUserId) {
        try {
            $allowedFields = ['email', 'role', 'first_name', 'last_name', 'is_active'];
            $updates = [];
            $values = [];
            
            foreach ($allowedFields as $field) {
                if (isset($userData[$field])) {
                    $updates[] = "$field = ?";
                    $values[] = $userData[$field];
                }
            }
            
            if (empty($updates)) {
                return ['success' => false, 'error' => 'No valid fields to update'];
            }
            
            $values[] = $userId;
            
            $stmt = $this->pdo->prepare("
                UPDATE users 
                SET " . implode(', ', $updates) . ", updated_at = NOW() 
                WHERE id = ?
            ");
            
            $stmt->execute($values);
            
            $this->logActivity($updatedByUserId, 'user_updated', "Updated user ID: $userId");
            
            return ['success' => true, 'message' => 'User updated successfully'];
            
        } catch (\Exception $e) {
            $this->logger->error('User update error', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return ['success' => false, 'error' => 'Failed to update user'];
        }
    }
    
    /**
     * Change password
     */
    public function changePassword($userId, $currentPassword, $newPassword) {
        try {
            // Get current user
            $user = $this->getUserById($userId);
            if (!$user) {
                return ['success' => false, 'error' => 'User not found'];
            }
            
            // Verify current password
            if (!password_verify($currentPassword, $user['password_hash'])) {
                return ['success' => false, 'error' => 'Current password is incorrect'];
            }
            
            // Validate new password
            if (strlen($newPassword) < self::PASSWORD_MIN_LENGTH) {
                return ['success' => false, 'error' => 'New password must be at least ' . self::PASSWORD_MIN_LENGTH . ' characters long'];
            }
            
            // Update password
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            
            $stmt = $this->pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$passwordHash, $userId]);
            
            $this->logActivity($userId, 'password_changed', 'User changed password');
            
            return ['success' => true, 'message' => 'Password changed successfully'];
            
        } catch (\Exception $e) {
            $this->logger->error('Password change error', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return ['success' => false, 'error' => 'Failed to change password'];
        }
    }
    
    /**
     * Helper methods
     */
    private function getUserByUsername($username) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
        $stmt->execute([$username]);
        return $stmt->fetch();
    }
    
    private function getUserByEmail($email) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch();
    }
    
    private function getUserById($userId) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }
    
    private function isUserLocked($user) {
        return $user['locked_until'] && strtotime($user['locked_until']) > time();
    }
    
    private function incrementFailedAttempts($userId) {
        $stmt = $this->pdo->prepare("
            UPDATE users 
            SET failed_login_attempts = failed_login_attempts + 1,
                locked_until = CASE 
                    WHEN failed_login_attempts + 1 >= ? THEN DATE_ADD(NOW(), INTERVAL ? MINUTE)
                    ELSE locked_until
                END
            WHERE id = ?
        ");
        $stmt->execute([self::MAX_LOGIN_ATTEMPTS, self::LOCKOUT_DURATION, $userId]);
    }
    
    private function resetFailedAttempts($userId) {
        $stmt = $this->pdo->prepare("UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE id = ?");
        $stmt->execute([$userId]);
    }
    
    private function createSession($userId, $rememberMe = false) {
        $sessionId = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + (self::SESSION_LIFETIME * 3600));
        
        if ($rememberMe) {
            $expiresAt = date('Y-m-d H:i:s', time() + (30 * 24 * 3600)); // 30 days
        }
        
        $stmt = $this->pdo->prepare("
            INSERT INTO user_sessions (id, user_id, ip_address, user_agent, expires_at) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $sessionId,
            $userId,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $expiresAt
        ]);
        
        return $sessionId;
    }
    
    private function destroySession($sessionId) {
        $stmt = $this->pdo->prepare("UPDATE user_sessions SET is_active = 0 WHERE id = ?");
        $stmt->execute([$sessionId]);
    }
    
    private function getUserIdFromSession($sessionId) {
        $stmt = $this->pdo->prepare("SELECT user_id FROM user_sessions WHERE id = ?");
        $stmt->execute([$sessionId]);
        $result = $stmt->fetch();
        return $result ? $result['user_id'] : null;
    }
    
    private function extendSessionIfNeeded($sessionId) {
        // Extend session if it expires within 1 hour
        $stmt = $this->pdo->prepare("
            UPDATE user_sessions 
            SET expires_at = DATE_ADD(NOW(), INTERVAL ? HOUR) 
            WHERE id = ? AND expires_at < DATE_ADD(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute([self::SESSION_LIFETIME, $sessionId]);
    }
    
    private function updateLastLogin($userId) {
        $stmt = $this->pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$userId]);
    }
    
    private function logActivity($userId, $action, $description) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO user_activity_log (user_id, action, description, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $userId,
                $action,
                $description,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to log user activity', [
                'user_id' => $userId,
                'action' => $action,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Cleanup expired sessions and old logs
     */
    public function cleanup() {
        try {
            // Clean expired sessions
            $stmt = $this->pdo->prepare("DELETE FROM user_sessions WHERE expires_at < NOW() OR is_active = 0");
            $stmt->execute();
            $sessionsDeleted = $stmt->rowCount();
            
            // Clean old activity logs (keep last 90 days)
            $stmt = $this->pdo->prepare("DELETE FROM user_activity_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
            $stmt->execute();
            $logsDeleted = $stmt->rowCount();
            
            $this->logger->info('Authentication cleanup completed', [
                'sessions_deleted' => $sessionsDeleted,
                'logs_deleted' => $logsDeleted
            ]);
            
            return ['sessions_deleted' => $sessionsDeleted, 'logs_deleted' => $logsDeleted];
            
        } catch (\Exception $e) {
            $this->logger->error('Authentication cleanup failed', ['error' => $e->getMessage()]);
            return ['error' => $e->getMessage()];
        }
    }
}