<?php
/**
 * HubSpot Integration Status Page
 * 
 * Displays the connection status and health of the HubSpot integration.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Services\HubSpotService;
use Laguna\Integration\Services\NetSuiteService;
use Laguna\Integration\Middleware\AuthMiddleware;
use Laguna\Integration\Utils\UrlHelper;
use Laguna\Integration\Utils\Logger;

// Set timezone
date_default_timezone_set('America/New_York');

// Require authentication
$auth = new AuthMiddleware();
$currentUser = $auth->requireAuth();
if (!$currentUser) {
    exit; // Middleware handles redirect
}

$config = require __DIR__ . '/../config/config.php';
$credentials = require __DIR__ . '/../config/credentials.php';

// Initialize services
$hubspotService = new HubSpotService();
$netsuiteService = new NetSuiteService();
$logger = Logger::getInstance();

// Check if JSON format is requested
if (isset($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json');
    
    $status = [
        'timestamp' => date('c'),
        'integration' => 'HubSpot to NetSuite',
        'services' => []
    ];
    
    // Test HubSpot connection
    $hubspotStatus = $hubspotService->testConnection();
    $status['services']['HubSpot'] = $hubspotStatus;
    
    // Test NetSuite connection
    $netsuiteStatus = $netsuiteService->testConnection();
    $status['services']['NetSuite'] = $netsuiteStatus;
    
    $status['overall_status'] = ($hubspotStatus['success'] && $netsuiteStatus['success']) ? 'healthy' : 'degraded';
    
    echo json_encode($status, JSON_PRETTY_PRINT);
    exit;
}

// Get status for display
$hubspotStatus = $hubspotService->testConnection();
$netsuiteStatus = $netsuiteService->testConnection();

$overallHealthy = $hubspotStatus['success'] && $netsuiteStatus['success'];
$statusColor = $overallHealthy ? '#4caf50' : '#f44336';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HubSpot Integration Status - Laguna Integrations</title>
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
            background: linear-gradient(135deg, <?php echo $statusColor; ?> 0%, <?php echo $statusColor; ?>dd 100%);
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
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .service-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            position: relative;
        }
        .service-card.healthy {
            border-left: 4px solid #4caf50;
            background: #f8fff8;
        }
        .service-card.error {
            border-left: 4px solid #f44336;
            background: #fff8f8;
        }
        .service-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 10px;
        }
        .status-healthy { background-color: #4caf50; }
        .status-error { background-color: #f44336; }
        .service-name {
            font-size: 1.2em;
            font-weight: 600;
            margin: 0;
        }
        .service-details {
            font-size: 0.9em;
            color: #666;
        }
        .metric {
            display: flex;
            justify-content: space-between;
            margin: 8px 0;
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }
        .metric:last-child {
            border-bottom: none;
        }
        .metric-label {
            font-weight: 500;
        }
        .metric-value {
            color: #666;
        }
        .error-details {
            background: #ffebee;
            border: 1px solid #ffcdd2;
            border-radius: 4px;
            padding: 10px;
            margin-top: 10px;
            font-size: 0.9em;
            color: #c62828;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 5px;
            font-size: 0.9em;
        }
        .btn:hover {
            background: #5a6fd8;
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
        .webhook-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .webhook-url {
            background: #f1f3f4;
            padding: 10px;
            border-radius: 4px;
            font-family: monospace;
            word-break: break-all;
            margin: 10px 0;
        }
    </style>
    <script>
        function refreshStatus() {
            window.location.reload();
        }
        
        function copyWebhookUrl() {
            const url = document.getElementById('webhook-url').textContent;
            navigator.clipboard.writeText(url).then(() => {
                alert('Webhook URL copied to clipboard!');
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
            <h1>🔗 HubSpot Integration Status</h1>
            <p>Status: <strong><?php echo $overallHealthy ? 'Healthy' : 'Degraded'; ?></strong></p>
            <p>Last Updated: <?php echo date('Y-m-d H:i:s T'); ?></p>
        </div>
        
        <div class="content">
            <div style="margin-bottom: 20px; text-align: center;">
                <button class="btn" onclick="refreshStatus()">🔄 Refresh Status</button>
                <a href="<?php echo UrlHelper::url('hubspot-status.php?format=json'); ?>" class="btn">📊 JSON Status</a>
                <a href="<?php echo UrlHelper::url('status.php'); ?>" class="btn">🔍 All Integrations</a>
            </div>
            
            <div class="status-grid">
                <!-- HubSpot Service -->
                <div class="service-card <?php echo $hubspotStatus['success'] ? 'healthy' : 'error'; ?>">
                    <div class="service-header">
                        <div class="status-indicator <?php echo $hubspotStatus['success'] ? 'status-healthy' : 'status-error'; ?>"></div>
                        <h3 class="service-name">HubSpot API</h3>
                    </div>
                    
                    <div class="service-details">
                        <div class="metric">
                            <span class="metric-label">Status:</span>
                            <span class="metric-value"><?php echo $hubspotStatus['success'] ? 'Connected' : 'Failed'; ?></span>
                        </div>
                        
                        <?php if (isset($hubspotStatus['status_code'])): ?>
                        <div class="metric">
                            <span class="metric-label">Status Code:</span>
                            <span class="metric-value"><?php echo $hubspotStatus['status_code']; ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (isset($hubspotStatus['response_time'])): ?>
                        <div class="metric">
                            <span class="metric-label">Response Time:</span>
                            <span class="metric-value"><?php echo $hubspotStatus['response_time']; ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="metric">
                            <span class="metric-label">Base URL:</span>
                            <span class="metric-value"><?php echo $credentials['hubspot']['base_url']; ?></span>
                        </div>
                        
                        <?php if (!$hubspotStatus['success'] && isset($hubspotStatus['error'])): ?>
                        <div class="error-details">
                            <strong>Error:</strong> <?php echo htmlspecialchars($hubspotStatus['error']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- NetSuite Service -->
                <div class="service-card <?php echo $netsuiteStatus['success'] ? 'healthy' : 'error'; ?>">
                    <div class="service-header">
                        <div class="status-indicator <?php echo $netsuiteStatus['success'] ? 'status-healthy' : 'status-error'; ?>"></div>
                        <h3 class="service-name">NetSuite API</h3>
                    </div>
                    
                    <div class="service-details">
                        <div class="metric">
                            <span class="metric-label">Status:</span>
                            <span class="metric-value"><?php echo $netsuiteStatus['success'] ? 'Connected' : 'Failed'; ?></span>
                        </div>
                        
                        <?php if (isset($netsuiteStatus['status_code'])): ?>
                        <div class="metric">
                            <span class="metric-label">Status Code:</span>
                            <span class="metric-value"><?php echo $netsuiteStatus['status_code']; ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (isset($netsuiteStatus['response_time'])): ?>
                        <div class="metric">
                            <span class="metric-label">Response Time:</span>
                            <span class="metric-value"><?php echo $netsuiteStatus['response_time']; ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="metric">
                            <span class="metric-label">Environment:</span>
                            <span class="metric-value"><?php echo ucfirst($netsuiteService->getEnvironmentInfo()['environment']); ?></span>
                        </div>
                        
                        <?php if (!$netsuiteStatus['success'] && isset($netsuiteStatus['error'])): ?>
                        <div class="error-details">
                            <strong>Error:</strong> <?php echo htmlspecialchars($netsuiteStatus['error']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="webhook-info">
                <h3>🔗 Webhook Configuration</h3>
                <p><strong>Webhook URL:</strong></p>
                <div class="webhook-url" id="webhook-url"><?php echo UrlHelper::url('hubspot-webhook.php', true); ?></div>
                <button class="btn" onclick="copyWebhookUrl()">📋 Copy URL</button>
                
                <h4>HubSpot Webhook Setup Instructions:</h4>
                <ol>
                    <li>Go to your HubSpot account settings</li>
                    <li>Navigate to Integrations → Private Apps</li>
                    <li>Create or edit your private app</li>
                    <li>Go to the Webhooks tab</li>
                    <li>Add the webhook URL above</li>
                    <li>Subscribe to "Contact property change" events</li>
                    <li>Set the property filter to "hubspot_owner_id" (or leave blank for all properties)</li>
                </ol>
            </div>
            
            <div class="info-box">
                <h4>📋 Integration Details</h4>
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <li><strong>Integration Type:</strong> HubSpot Contact → NetSuite Lead</li>
                    <li><strong>Trigger:</strong> Contact property change (hubspot_owner_id)</li>
                    <li><strong>Condition:</strong> Contact lifecycle stage must be "lead"</li>
                    <li><strong>Process:</strong> Create/find campaign, then create lead in NetSuite</li>
                    <li><strong>Webhook Endpoint:</strong> <?php echo UrlHelper::url('hubspot-webhook.php'); ?></li>
                </ul>
            </div>
            
            <div class="info-box">
                <h4>🔧 Configuration Status</h4>
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <li><strong>HubSpot Access Token:</strong> <?php echo !empty($credentials['hubspot']['access_token']) && $credentials['hubspot']['access_token'] !== 'YOUR_HUBSPOT_ACCESS_TOKEN' ? '✅ Configured' : '❌ Not configured'; ?></li>
                    <li><strong>Webhook Secret:</strong> <?php echo !empty($credentials['hubspot']['webhook_secret']) && $credentials['hubspot']['webhook_secret'] !== 'your-hubspot-webhook-secret' ? '✅ Configured' : '⚠️ Using default (recommended to change)'; ?></li>
                    <li><strong>NetSuite Environment:</strong> <?php echo ucfirst($netsuiteService->getEnvironmentInfo()['environment']); ?></li>
                    <li><strong>Integration Enabled:</strong> <?php echo $config['integrations']['hubspot_netsuite']['enabled'] ? '✅ Enabled' : '❌ Disabled'; ?></li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>