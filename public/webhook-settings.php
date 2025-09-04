<?php
/**
 * Webhook Settings Page
 * 
 * Displays webhook URLs and configuration for all integrations.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Middleware\AuthMiddleware;
use Laguna\Integration\Utils\UrlHelper;

// Set timezone
date_default_timezone_set('America/New_York');

// Require authentication
$auth = new AuthMiddleware();
$currentUser = $auth->requireAuth();
if (!$currentUser) {
    exit; // Middleware handles redirect
}

$config = require __DIR__ . '/../config/config.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Webhook Settings - Laguna Integrations</title>
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
        .content {
            padding: 30px;
        }
        .integration-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            background: #fafafa;
        }
        .integration-card h3 {
            margin: 0 0 15px 0;
            color: #333;
            display: flex;
            align-items: center;
        }
        .integration-card h3 .icon {
            margin-right: 10px;
            font-size: 1.2em;
        }
        .webhook-url {
            background: #f1f3f4;
            padding: 15px;
            border-radius: 4px;
            font-family: monospace;
            word-break: break-all;
            margin: 10px 0;
            border-left: 4px solid #667eea;
        }
        .btn {
            display: inline-block;
            padding: 8px 16px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin: 5px 5px 5px 0;
            font-size: 0.9em;
            border: none;
            cursor: pointer;
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
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 500;
            margin-left: 10px;
        }
        .status-enabled {
            background: #d4edda;
            color: #155724;
        }
        .status-disabled {
            background: #f8d7da;
            color: #721c24;
        }
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin: 20px 0;
            border-radius: 0 4px 4px 0;
        }
        .setup-instructions {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 15px 0;
            border-radius: 0 4px 4px 0;
        }
        .setup-instructions h4 {
            margin: 0 0 10px 0;
            color: #856404;
        }
        .setup-instructions ol {
            margin: 10px 0;
            padding-left: 20px;
        }
    </style>
    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert('URL copied to clipboard!');
            });
        }
    </script>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="user-info">
                Welcome, <?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?>
                <a href="<?php echo UrlHelper::url('index.php'); ?>">🏠 Dashboard</a>
                <a href="<?php echo UrlHelper::url('logout.php'); ?>">🚪 Logout</a>
            </div>
            <h1>⚙️ Webhook Settings</h1>
            <p>Configure webhook endpoints for all integrations</p>
        </div>
        
        <div class="content">
            <div class="info-box">
                <h4>📋 Webhook Configuration</h4>
                <p>Webhooks allow external systems to notify our integration platform when events occur. Each integration has its own webhook endpoint that should be configured in the respective external system.</p>
            </div>
            
            <?php foreach ($config['integrations'] as $key => $integration): ?>
            <div class="integration-card">
                <h3>
                    <span class="icon"><?php echo $key === '3dcart_netsuite' ? '🛒' : '🎯'; ?></span>
                    <?php echo htmlspecialchars($integration['name']); ?>
                    <span class="status-badge <?php echo $integration['enabled'] ? 'status-enabled' : 'status-disabled'; ?>">
                        <?php echo $integration['enabled'] ? 'Enabled' : 'Disabled'; ?>
                    </span>
                </h3>
                
                <p><?php echo htmlspecialchars($integration['description']); ?></p>
                
                <div>
                    <strong>Webhook URL:</strong>
                    <div class="webhook-url"><?php echo UrlHelper::url($integration['webhook_endpoint'], true); ?></div>
                    <button class="btn" onclick="copyToClipboard('<?php echo UrlHelper::url($integration['webhook_endpoint'], true); ?>')">
                        📋 Copy URL
                    </button>
                    <a href="<?php echo UrlHelper::url($integration['status_page']); ?>" class="btn btn-secondary">
                        📊 View Status
                    </a>
                </div>
                
                <?php if ($key === '3dcart_netsuite'): ?>
                <div class="setup-instructions">
                    <h4>3DCart Setup Instructions:</h4>
                    <ol>
                        <li>Log into your 3DCart admin panel</li>
                        <li>Go to Settings → General → Checkout</li>
                        <li>Scroll down to "Webhook URL" section</li>
                        <li>Enter the webhook URL above</li>
                        <li>Select "Order Placed" as the trigger event</li>
                        <li>Save the settings</li>
                    </ol>
                </div>
                <?php elseif ($key === 'hubspot_netsuite'): ?>
                <div class="setup-instructions">
                    <h4>HubSpot Setup Instructions:</h4>
                    <ol>
                        <li>Go to your HubSpot account settings</li>
                        <li>Navigate to Integrations → Private Apps</li>
                        <li>Create or edit your private app</li>
                        <li>Go to the Webhooks tab</li>
                        <li>Add the webhook URL above</li>
                        <li>Subscribe to "Contact property change" events</li>
                        <li>Set the property filter to "hubspot_owner_id" (or leave blank for all properties)</li>
                        <li>Save the webhook configuration</li>
                    </ol>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            
            <div class="info-box">
                <h4>🔒 Security Notes</h4>
                <ul>
                    <li><strong>HTTPS Required:</strong> All webhook URLs use HTTPS for secure communication</li>
                    <li><strong>Signature Verification:</strong> Webhooks verify signatures when configured</li>
                    <li><strong>Authentication:</strong> Webhook endpoints are publicly accessible but validate payloads</li>
                    <li><strong>Rate Limiting:</strong> Consider implementing rate limiting for production use</li>
                </ul>
            </div>
            
            <div class="info-box">
                <h4>🔧 Testing Webhooks</h4>
                <p>You can test webhook endpoints using tools like:</p>
                <ul>
                    <li><strong>Postman:</strong> Send POST requests with sample payloads</li>
                    <li><strong>cURL:</strong> Command-line testing with custom headers</li>
                    <li><strong>Webhook.site:</strong> Online webhook testing service</li>
                    <li><strong>ngrok:</strong> Local development tunnel for testing</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>