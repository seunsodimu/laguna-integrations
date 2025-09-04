<?php
/**
 * Main Entry Point
 * 
 * This is the main entry point for the 3DCart to NetSuite integration system.
 * It provides a simple dashboard with links to all available functionality.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Middleware\AuthMiddleware;
use Laguna\Integration\Utils\UrlHelper;
use Laguna\Integration\Utils\NetSuiteEnvironmentManager;

// Set timezone
date_default_timezone_set('America/New_York');

$config = require __DIR__ . '/../config/config.php';

// Get environment information
$envManager = NetSuiteEnvironmentManager::getInstance();
$envInfo = $envManager->getEnvironmentInfo();

// Require authentication
$auth = new AuthMiddleware();
$currentUser = $auth->requireAuth();
if (!$currentUser) {
    exit; // Middleware handles redirect
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $config['app']['name']; ?> - Dashboard</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
            color: #333;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
        }
        .user-info {
            position: absolute;
            top: 20px;
            right: 30px;
            text-align: right;
            font-size: 0.9em;
        }
        .user-info a {
            color: white;
            text-decoration: none;
            margin-left: 15px;
            opacity: 0.9;
        }
        .user-info a:hover {
            opacity: 1;
            text-decoration: underline;
        }
        .header h1 {
            margin: 0;
            font-size: 2.5em;
            font-weight: 300;
        }
        .header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
            font-size: 1.1em;
        }
        .content {
            padding: 40px;
        }
        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }
        .feature-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 25px;
            text-align: center;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .feature-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .feature-icon {
            font-size: 3em;
            margin-bottom: 15px;
        }
        .feature-card h3 {
            margin: 0 0 15px 0;
            color: #333;
        }
        .feature-card p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.2s;
            font-weight: 500;
        }
        .btn:hover {
            background: #5a6fd8;
        }
        .btn-secondary {
            background: #6c757d;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .status-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 25px;
            margin-top: 30px;
        }
        .status-section h3 {
            margin-top: 0;
            color: #333;
        }
        .quick-links {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin: 20px 0;
            border-radius: 0 4px 4px 0;
        }
        .info-box h4 {
            margin: 0 0 10px 0;
            color: #1976d2;
        }
        .footer {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            color: #666;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="user-info">
                Welcome, <?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?>
                (<?php echo htmlspecialchars($currentUser['role']); ?>)
                <?php if ($currentUser['role'] === 'admin'): ?>
                    <a href="<?php echo UrlHelper::url('user-management.php'); ?>">👥 Users</a>
                <?php endif; ?>
                <a href="<?php echo UrlHelper::url('logout.php'); ?>">🚪 Logout</a>
            </div>
            <h1><?php echo $config['app']['name']; ?></h1>
            <p>Multi-platform integration hub for automated business processes</p>
            <p style="font-size: 0.9em; margin-top: 15px;">
                NetSuite Environment: 
                <span style="background: <?php echo $envInfo['is_production'] ? '#ffebee' : '#e8f5e8'; ?>; 
                             color: <?php echo $envInfo['is_production'] ? '#c62828' : '#2e7d32'; ?>; 
                             padding: 4px 8px; border-radius: 4px; font-weight: bold; text-transform: uppercase;">
                    <?php echo $envInfo['environment']; ?>
                </span>
            </p>
        </div>
        
        <div class="content">
            <h2 style="text-align: center; margin-bottom: 30px; color: #333;">Available Integrations</h2>
            
            <div class="features">
                <!-- 3DCart to NetSuite Integration -->
                <div class="feature-card" style="border-left: 4px solid #667eea;">
                    <div class="feature-icon">🛒</div>
                    <h3>3DCart → NetSuite</h3>
                    <p>Automated order processing from 3DCart to NetSuite. Real-time webhook integration for orders, customer management, and sales order creation.</p>
                    <div style="margin-top: 15px;">
                        <a href="<?php echo UrlHelper::url('status.php'); ?>" class="btn">📊 Status</a>
                        <a href="<?php echo UrlHelper::url('order-sync.php'); ?>" class="btn">🔄 Sync Orders</a>
                        <a href="<?php echo UrlHelper::url('upload.php'); ?>" class="btn">📤 Upload</a>
                    </div>
                </div>
                
                <!-- HubSpot to NetSuite Integration -->
                <div class="feature-card" style="border-left: 4px solid #ff7a59;">
                    <div class="feature-icon">🎯</div>
                    <h3>HubSpot → NetSuite</h3>
                    <p>Lead synchronization from HubSpot to NetSuite. Automatically creates leads in NetSuite when contact properties change in HubSpot.</p>
                    <div style="margin-top: 15px;">
                        <a href="<?php echo UrlHelper::url('hubspot-status.php'); ?>" class="btn">📊 Status</a>
                        <a href="<?php echo UrlHelper::url('hubspot-webhook.php'); ?>" class="btn">🔗 Webhook</a>
                    </div>
                </div>
                
                <!-- System Management -->
                <div class="feature-card" style="border-left: 4px solid #4caf50;">
                    <div class="feature-icon">⚙️</div>
                    <h3>System Management</h3>
                    <p>Monitor all integrations, check connection status, manage webhooks, and configure system settings from a centralized dashboard.</p>
                    <div style="margin-top: 15px;">
                        <a href="<?php echo UrlHelper::url('status.php'); ?>" class="btn">📊 All Status</a>
                        <a href="<?php echo UrlHelper::url('webhook-settings.php'); ?>" class="btn">⚙️ Settings</a>
                    </div>
                </div>
                
                <!-- Email Notifications -->
                <div class="feature-card" style="border-left: 4px solid #ff9800;">
                    <div class="feature-icon">📧</div>
                    <h3>Email Notifications</h3>
                    <p>Automated email notifications for integration events, errors, and status updates. Supports multiple email providers.</p>
                    <div style="margin-top: 15px;">
                        <a href="<?php echo UrlHelper::url('test-email.php'); ?>" class="btn">📧 Test Email</a>
                        <a href="<?php echo UrlHelper::url('email-provider-config.php'); ?>" class="btn">⚙️ Config</a>
                    </div>
                </div>
            </div>
            
            <div class="info-box">
                <h4>🚀 Getting Started</h4>
                <p>
                    <strong>New to the system?</strong> Start by checking the connection status to ensure all services are properly configured. 
                    Then review the documentation for webhook setup and API credentials configuration.
                </p>
            </div>
            
            <div class="status-section">
                <h3>Quick Actions</h3>
                <div class="quick-links">
                    <a href="<?php echo UrlHelper::url('webhook-settings.php'); ?>" class="btn btn-secondary">⚙️ Webhook Settings</a>
                    <a href="<?php echo UrlHelper::url('status.php?format=json'); ?>" class="btn btn-secondary">API Status (JSON)</a>
                    <a href="<?php echo UrlHelper::projectUrl('documentation/setup/SETUP.md'); ?>" class="btn btn-secondary">Setup Guide</a>
                    <a href="<?php echo UrlHelper::projectUrl('documentation/setup/API_CREDENTIALS.md'); ?>" class="btn btn-secondary">API Credentials</a>
                    <a href="<?php echo UrlHelper::projectUrl('logs/'); ?>" class="btn btn-secondary">View Logs</a>
                </div>
            </div>
            
            <div class="info-box">
                <h4>📋 System Information</h4>
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <li><strong>Version:</strong> <?php echo $config['app']['version']; ?></li>
                    <li><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></li>
                    <li><strong>Server Time:</strong> <?php echo date('Y-m-d H:i:s T'); ?></li>
                    <li><strong>Timezone:</strong> <?php echo date_default_timezone_get(); ?></li>
                    <li><strong>Debug Mode:</strong> <?php echo $config['app']['debug'] ? 'Enabled' : 'Disabled'; ?></li>
                </ul>
            </div>
            
            <div class="info-box">
                <h4>🔧 Configuration Status</h4>
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <li><strong>Credentials File:</strong> <?php echo file_exists(__DIR__ . '/../config/credentials.php') ? '✅ Configured' : '❌ Missing'; ?></li>
                    <li><strong>Logs Directory:</strong> <?php echo is_writable(__DIR__ . '/../logs') ? '✅ Writable' : '❌ Not writable'; ?></li>
                    <li><strong>Uploads Directory:</strong> <?php echo is_writable(__DIR__ . '/../uploads') ? '✅ Writable' : '❌ Not writable'; ?></li>
                    <li><strong>Email Notifications:</strong> <?php echo $config['notifications']['enabled'] ? '✅ Enabled' : '❌ Disabled'; ?></li>
                    <li><strong>Webhook Processing:</strong> <?php echo $config['webhook']['enabled'] ? '✅ Enabled' : '⚠️ Disabled'; ?></li>
                </ul>
            </div>
        </div>
        
        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> Laguna Integrations Platform. Built with PHP.</p>
        </div>
    </div>
</body>
</html>