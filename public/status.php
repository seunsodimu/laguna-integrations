<?php
/**
 * Status Dashboard
 * 
 * Displays the connection status and health of all integrated services.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Controllers\StatusController;
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

$controller = new StatusController();
$config = require __DIR__ . '/../config/config.php';

// Check if JSON format is requested
if (isset($_GET['format']) && $_GET['format'] === 'json') {
    $controller->getStatusJson();
    exit;
}

if (isset($_GET['detailed']) && $_GET['detailed'] === 'true') {
    $status = $controller->getDetailedStatus();
} else {
    $status = $controller->getStatus();
}

$overallStatus = $status['overall_status'];
$statusColor = $overallStatus === 'healthy' ? '#4caf50' : ($overallStatus === 'degraded' ? '#ff9800' : '#f44336');

// Calculate service summary
$totalServices = count($status['services']);
$healthyServices = array_sum(array_column($status['services'], 'success'));
$failedServices = $totalServices - $healthyServices;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Status - Laguna Integrations</title>
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
        .service-card.warning {
            border-left: 4px solid #ff9800;
            background: #fffbf0;
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
        .status-warning { background-color: #ff9800; }
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
        .system-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .health-checks {
            margin-top: 30px;
        }
        .health-check {
            display: flex;
            align-items: center;
            padding: 10px;
            margin: 5px 0;
            border-radius: 5px;
            background: #f8f9fa;
        }
        .health-check.ok {
            background: #e8f5e8;
            border-left: 3px solid #4caf50;
        }
        .health-check.warning {
            background: #fff3e0;
            border-left: 3px solid #ff9800;
        }
        .health-check.error {
            background: #ffebee;
            border-left: 3px solid #f44336;
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
        .btn-small {
            padding: 5px 10px;
            font-size: 0.8em;
        }

        .refresh-info {
            text-align: center;
            margin: 20px 0;
            color: #666;
            font-size: 0.9em;
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
        .tabs {
            display: flex;
            border-bottom: 1px solid #e0e0e0;
            margin-bottom: 20px;
        }
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
        }
        .tab.active {
            border-bottom-color: #667eea;
            color: #667eea;
            font-weight: 600;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
    </style>
    <script>
        function switchTab(tabName) {
            // Hide all tab contents
            const contents = document.querySelectorAll('.tab-content');
            contents.forEach(content => content.classList.remove('active'));
            
            // Remove active class from all tabs
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Show selected tab content
            document.getElementById(tabName + '-content').classList.add('active');
            document.getElementById(tabName + '-tab').classList.add('active');
        }
        
        function refreshStatus() {
            window.location.reload();
        }
    </script>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 System Status</h1>
            <p>Overall Status: <strong style="color: <?php echo $statusColor; ?>"><?php echo ucfirst($overallStatus); ?></strong></p>
            <p>Services: <span style="color: #4caf50;"><?php echo $healthyServices; ?> healthy</span> • <span style="color: #f44336;"><?php echo $failedServices; ?> failed</span> • <?php echo $totalServices; ?> total</p>
            <p style="font-size: 0.9em; opacity: 0.9;">
                Monitoring <?php echo count($config['integrations']); ?> integrations: 
                <?php 
                $integrationNames = array_map(function($integration) { return $integration['name']; }, $config['integrations']);
                echo implode(' • ', $integrationNames);
                ?>
            </p>
            <p>Last Updated: <?php echo date('Y-m-d H:i:s T', strtotime($status['timestamp'])); ?></p>
        </div>
        
        <div class="content">
            <div class="tabs">
                <div class="tab active" id="services-tab" onclick="switchTab('services')">Services</div>
                <div class="tab" id="system-tab" onclick="switchTab('system')">System Info</div>
                <div class="tab" id="health-tab" onclick="switchTab('health')">Health Checks</div>
                <?php if (isset($status['recent_activity'])): ?>
                <div class="tab" id="activity-tab" onclick="switchTab('activity')">Recent Activity</div>
                <?php endif; ?>
            </div>
            
            <!-- Services Tab -->
            <div class="tab-content active" id="services-content">
                <div style="margin-bottom: 20px; text-align: center;">
                    <button class="btn" onclick="refreshStatus()">🔄 Refresh Status</button>
                    <a href="<?php echo UrlHelper::url('hubspot-status.php'); ?>" class="btn">🎯 HubSpot Status</a>
                    <a href="<?php echo UrlHelper::url('webhook-settings.php'); ?>" class="btn">⚙️ Webhooks</a>
                    <a href="<?php echo UrlHelper::url('test-email.php'); ?>" class="btn">📧 Test Email</a>
                    <a href="<?php echo UrlHelper::url('environment-status.php'); ?>" class="btn" style="margin-left: 10px; text-decoration: none; display: inline-block;">🌐 Environment</a>
                </div>
                
                <div class="status-grid">
                    <?php foreach ($status['services'] as $serviceName => $serviceStatus): ?>
                    <div class="service-card <?php echo $serviceStatus['success'] ? 'healthy' : 'error'; ?>">
                        <div class="service-header">
                            <div class="status-indicator <?php echo $serviceStatus['success'] ? 'status-healthy' : 'status-error'; ?>"></div>
                            <h3 class="service-name"><?php echo htmlspecialchars($serviceName); ?></h3>
                        </div>
                        
                        <div class="service-details">
                            <div class="metric">
                                <span class="metric-label">Status:</span>
                                <span class="metric-value"><?php echo $serviceStatus['success'] ? 'Connected' : 'Failed'; ?></span>
                            </div>
                            
                            <?php if (isset($serviceStatus['status_code'])): ?>
                            <div class="metric">
                                <span class="metric-label">Status Code:</span>
                                <span class="metric-value"><?php echo $serviceStatus['status_code']; ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (isset($serviceStatus['response_time'])): ?>
                            <div class="metric">
                                <span class="metric-label">Response Time:</span>
                                <span class="metric-value"><?php echo $serviceStatus['response_time']; ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="metric">
                                <span class="metric-label">Last Checked:</span>
                                <span class="metric-value"><?php echo date('H:i:s', strtotime($serviceStatus['last_checked'])); ?></span>
                            </div>
                            
                            <?php if (!$serviceStatus['success'] && isset($serviceStatus['error'])): ?>
                            <div class="error-details">
                                <strong>Error:</strong> <?php echo htmlspecialchars($serviceStatus['error']); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- System Info Tab -->
            <div class="tab-content" id="system-content">
                <div class="system-info">
                    <h3>🖥️ System Information</h3>
                    <div class="metric">
                        <span class="metric-label">PHP Version:</span>
                        <span class="metric-value"><?php echo $status['system_info']['php_version']; ?></span>
                    </div>
                    <div class="metric">
                        <span class="metric-label">Server Time:</span>
                        <span class="metric-value"><?php echo $status['system_info']['server_time']; ?></span>
                    </div>
                    <div class="metric">
                        <span class="metric-label">Timezone:</span>
                        <span class="metric-value"><?php echo $status['system_info']['timezone']; ?></span>
                    </div>
                    <div class="metric">
                        <span class="metric-label">Memory Usage:</span>
                        <span class="metric-value"><?php echo $status['system_info']['memory_usage']['current']; ?> (Peak: <?php echo $status['system_info']['memory_usage']['peak']; ?>)</span>
                    </div>
                    <div class="metric">
                        <span class="metric-label">Memory Limit:</span>
                        <span class="metric-value"><?php echo $status['system_info']['memory_usage']['limit']; ?></span>
                    </div>
                    <div class="metric">
                        <span class="metric-label">Disk Space:</span>
                        <span class="metric-value"><?php echo $status['system_info']['disk_space']['free']; ?> free of <?php echo $status['system_info']['disk_space']['total']; ?></span>
                    </div>
                </div>
                
                <div class="system-info">
                    <h3>🔧 PHP Extensions</h3>
                    <?php foreach ($status['system_info']['extensions'] as $ext => $loaded): ?>
                    <div class="metric">
                        <span class="metric-label"><?php echo ucfirst($ext); ?>:</span>
                        <span class="metric-value"><?php echo $loaded ? '✅ Loaded' : '❌ Missing'; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="system-info">
                    <h3>⚙️ Configuration</h3>
                    <?php foreach ($status['configuration'] as $key => $value): ?>
                    <div class="metric">
                        <span class="metric-label"><?php echo ucwords(str_replace('_', ' ', $key)); ?>:</span>
                        <span class="metric-value"><?php echo $value ? '✅ Yes' : '❌ No'; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Health Checks Tab -->
            <div class="tab-content" id="health-content">
                <div class="health-checks">
                    <h3>🏥 Health Checks</h3>
                    <?php foreach ($status['health_checks'] as $checkName => $check): ?>
                    <div class="health-check <?php echo $check['status']; ?>">
                        <div style="flex: 1;">
                            <strong><?php echo ucwords(str_replace('_', ' ', $checkName)); ?>:</strong>
                            <?php echo $check['message']; ?>
                            <?php if (isset($check['size'])): ?>
                                (<?php echo $check['size']; ?>)
                            <?php endif; ?>
                            <?php if (isset($check['file_count'])): ?>
                                (<?php echo $check['file_count']; ?> files)
                            <?php endif; ?>
                            <?php if (isset($check['usage_percent'])): ?>
                                (<?php echo $check['usage_percent']; ?>%)
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Recent Activity Tab -->
            <?php if (isset($status['recent_activity'])): ?>
            <div class="tab-content" id="activity-content">
                <div class="system-info">
                    <h3>📈 Recent Activity (Last 24 Hours)</h3>
                    <div class="metric">
                        <span class="metric-label">Orders Processed:</span>
                        <span class="metric-value"><?php echo $status['recent_activity']['last_24_hours']['orders_processed']; ?></span>
                    </div>
                    <div class="metric">
                        <span class="metric-label">Errors:</span>
                        <span class="metric-value"><?php echo $status['recent_activity']['last_24_hours']['errors']; ?></span>
                    </div>
                    <div class="metric">
                        <span class="metric-label">API Calls:</span>
                        <span class="metric-value"><?php echo $status['recent_activity']['last_24_hours']['api_calls']; ?></span>
                    </div>
                </div>
                
                <div class="system-info">
                    <h3>⏰ Recent Activity (Last Hour)</h3>
                    <div class="metric">
                        <span class="metric-label">Orders Processed:</span>
                        <span class="metric-value"><?php echo $status['recent_activity']['last_hour']['orders_processed']; ?></span>
                    </div>
                    <div class="metric">
                        <span class="metric-label">Errors:</span>
                        <span class="metric-value"><?php echo $status['recent_activity']['last_hour']['errors']; ?></span>
                    </div>
                    <div class="metric">
                        <span class="metric-label">API Calls:</span>
                        <span class="metric-value"><?php echo $status['recent_activity']['last_hour']['api_calls']; ?></span>
                    </div>
                </div>
                
                <?php if (isset($status['performance'])): ?>
                <div class="system-info">
                    <h3>⚡ Performance Metrics</h3>
                    <div class="metric">
                        <span class="metric-label">Avg Order Processing Time:</span>
                        <span class="metric-value"><?php echo $status['performance']['average_order_processing_time']; ?></span>
                    </div>
                    <div class="metric">
                        <span class="metric-label">Success Rate (24h):</span>
                        <span class="metric-value"><?php echo $status['performance']['success_rate_24h']; ?></span>
                    </div>
                    <?php foreach ($status['performance']['api_response_times'] as $api => $time): ?>
                    <div class="metric">
                        <span class="metric-label"><?php echo $api; ?> Response Time:</span>
                        <span class="metric-value"><?php echo $time; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="refresh-info">
                <p>
                    <button onclick="refreshStatus()" class="btn btn-small">🔄 Refresh Now</button>
                    <a href="?format=json" class="btn btn-small">📄 JSON Format</a>
                    <a href="?detailed=true" class="btn btn-small">📊 Detailed View</a>
                    <a href="<?php echo UrlHelper::url('index.php'); ?>" class="btn btn-small">🏠 Dashboard</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>