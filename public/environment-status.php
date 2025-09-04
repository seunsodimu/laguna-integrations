<?php
/**
 * NetSuite Environment Status Page
 * 
 * Displays current NetSuite environment configuration and provides
 * instructions for switching between environments.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Utils\NetSuiteEnvironmentManager;
use Laguna\Integration\Services\NetSuiteService;
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

$envManager = NetSuiteEnvironmentManager::getInstance();
$envInfo = $envManager->getEnvironmentInfo();
$validation = $envManager->validateEnvironment();
$switchingInstructions = $envManager->getSwitchingInstructions();

// Test connection if requested
$connectionTest = null;
if (isset($_GET['test_connection'])) {
    try {
        $netsuiteService = new NetSuiteService();
        $connectionTest = $netsuiteService->testConnection();
    } catch (Exception $e) {
        $connectionTest = [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

$statusColor = $validation['valid'] ? '#4caf50' : '#f44336';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NetSuite Environment Status - 3DCart Integration</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
            color: #333;
        }
        .container {
            max-width: 1000px;
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
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .info-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
        }
        .info-card.success {
            border-left: 4px solid #4caf50;
            background: #f8fff8;
        }
        .info-card.error {
            border-left: 4px solid #f44336;
            background: #fff8f8;
        }
        .info-card.warning {
            border-left: 4px solid #ff9800;
            background: #fffbf0;
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
            font-family: monospace;
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
            border: none;
            cursor: pointer;
        }
        .btn:hover {
            background: #5a6fd8;
        }
        .btn-success {
            background: #4caf50;
        }
        .btn-success:hover {
            background: #45a049;
        }
        .code-block {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            padding: 10px;
            font-family: monospace;
            font-size: 0.9em;
            margin: 10px 0;
            overflow-x: auto;
        }
        .environment-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: bold;
            text-transform: uppercase;
        }
        .env-production {
            background: #ffebee;
            color: #c62828;
        }
        .env-sandbox {
            background: #e8f5e8;
            color: #2e7d32;
        }
        .validation-issues {
            background: #ffebee;
            border: 1px solid #ffcdd2;
            border-radius: 4px;
            padding: 15px;
            margin: 15px 0;
        }
        .validation-issues ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        .validation-issues li {
            color: #c62828;
            margin: 5px 0;
        }
        .instructions {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .nav-links {
            text-align: center;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🌐 NetSuite Environment Status</h1>
            <p>Current Environment: <span class="environment-badge env-<?php echo $envInfo['environment']; ?>"><?php echo $envInfo['environment']; ?></span></p>
            <p>Configuration: <?php echo $validation['valid'] ? '✅ Valid' : '❌ Issues Found'; ?></p>
        </div>
        
        <div class="content">
            <div class="nav-links">
                <a href="<?php echo UrlHelper::url('status.php'); ?>" class="btn">📊 System Status</a>
                <a href="<?php echo UrlHelper::url('environment-status.php?test_connection=1'); ?>" class="btn btn-success">🔗 Test Connection</a>
                <a href="<?php echo UrlHelper::url('index.php'); ?>" class="btn">🏠 Dashboard</a>
            </div>
            
            <div class="info-grid">
                <!-- Current Environment Info -->
                <div class="info-card <?php echo $validation['valid'] ? 'success' : 'error'; ?>">
                    <h3>🎯 Current Environment</h3>
                    <div class="metric">
                        <span class="metric-label">Environment:</span>
                        <span class="metric-value"><?php echo $envInfo['environment']; ?></span>
                    </div>
                    <div class="metric">
                        <span class="metric-label">Account ID:</span>
                        <span class="metric-value"><?php echo $envInfo['account_id']; ?></span>
                    </div>
                    <div class="metric">
                        <span class="metric-label">Base URL:</span>
                        <span class="metric-value"><?php echo $envInfo['base_url']; ?></span>
                    </div>
                    <div class="metric">
                        <span class="metric-label">Environment Source:</span>
                        <span class="metric-value"><?php echo $envManager->getEnvironmentSource(); ?></span>
                    </div>
                    <div class="metric">
                        <span class="metric-label">Variable Set:</span>
                        <span class="metric-value"><?php echo $envManager->isEnvironmentVariableSet() ? 'Yes' : 'No'; ?></span>
                    </div>
                </div>
                
                <!-- Available Environments -->
                <div class="info-card">
                    <h3>🔄 Available Environments</h3>
                    <?php foreach ($envInfo['available_environments'] as $env): ?>
                    <div class="metric">
                        <span class="metric-label"><?php echo ucfirst($env); ?>:</span>
                        <span class="metric-value">
                            <?php if ($env === $envInfo['environment']): ?>
                                <strong>✅ Active</strong>
                            <?php else: ?>
                                Available
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <?php if (!$validation['valid']): ?>
            <div class="validation-issues">
                <h3>⚠️ Configuration Issues</h3>
                <p>The following issues were found with the current environment configuration:</p>
                <ul>
                    <?php foreach ($validation['issues'] as $issue): ?>
                    <li><?php echo htmlspecialchars($issue); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <?php if ($connectionTest): ?>
            <div class="info-card <?php echo $connectionTest['success'] ? 'success' : 'error'; ?>">
                <h3>🔗 Connection Test Results</h3>
                <div class="metric">
                    <span class="metric-label">Status:</span>
                    <span class="metric-value"><?php echo $connectionTest['success'] ? 'Success' : 'Failed'; ?></span>
                </div>
                <?php if ($connectionTest['success']): ?>
                    <div class="metric">
                        <span class="metric-label">Status Code:</span>
                        <span class="metric-value"><?php echo $connectionTest['status_code']; ?></span>
                    </div>
                    <div class="metric">
                        <span class="metric-label">Response Time:</span>
                        <span class="metric-value"><?php echo $connectionTest['response_time']; ?></span>
                    </div>
                <?php else: ?>
                    <div class="metric">
                        <span class="metric-label">Error:</span>
                        <span class="metric-value"><?php echo htmlspecialchars($connectionTest['error']); ?></span>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="instructions">
                <h3>🔧 Environment Switching Instructions</h3>
                <p>To switch between NetSuite environments, set the <code>NETSUITE_ENVIRONMENT</code> environment variable:</p>
                
                <h4>Windows Command Prompt:</h4>
                <div class="code-block">set NETSUITE_ENVIRONMENT=production</div>
                
                <h4>Windows PowerShell:</h4>
                <div class="code-block">$env:NETSUITE_ENVIRONMENT="production"</div>
                
                <h4>Linux/Mac Terminal:</h4>
                <div class="code-block">export NETSUITE_ENVIRONMENT=production</div>
                
                <h4>Using .env file:</h4>
                <div class="code-block">NETSUITE_ENVIRONMENT=production</div>
                
                <p><strong>Available values:</strong> <code>production</code>, <code>sandbox</code></p>
                <p><strong>Default:</strong> <code>sandbox</code> (for safety)</p>
                <p><strong>Note:</strong> Web server restart may be required for environment variable changes to take effect.</p>
            </div>
        </div>
    </div>
</body>
</html>