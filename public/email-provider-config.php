<?php
/**
 * Email Provider Configuration Page
 * 
 * Allows switching between email providers and configuring their settings.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Services\EmailServiceFactory;
use Laguna\Integration\Utils\Logger;
use Laguna\Integration\Middleware\AuthMiddleware;
use Laguna\Integration\Utils\UrlHelper;

// Set timezone
date_default_timezone_set('America/New_York');

// Require admin authentication
$auth = new AuthMiddleware();
$currentUser = $auth->requireAdmin();
if (!$currentUser) {
    exit; // Middleware handles redirect
}

$logger = Logger::getInstance();
$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'switch_provider') {
        $newProvider = $_POST['provider'] ?? '';
        $availableProviders = array_keys(EmailServiceFactory::getAvailableProviders());
        
        if (in_array($newProvider, $availableProviders)) {
            // Read current credentials
            $credentialsFile = __DIR__ . '/../config/credentials.php';
            $credentials = require $credentialsFile;
            
            // Update provider
            $credentials['email']['provider'] = $newProvider;
            
            // Write back to file
            $credentialsContent = "<?php\n\nreturn " . var_export($credentials, true) . ";\n";
            
            if (file_put_contents($credentialsFile, $credentialsContent)) {
                $message = "Email provider switched to {$newProvider} successfully!";
                $messageType = 'success';
                $logger->info('Email provider switched', ['new_provider' => $newProvider]);
            } else {
                $message = "Failed to update configuration file.";
                $messageType = 'error';
                $logger->error('Failed to switch email provider', ['provider' => $newProvider]);
            }
        } else {
            $message = "Invalid provider selected.";
            $messageType = 'error';
        }
    } elseif ($action === 'update_credentials') {
        $provider = $_POST['credential_provider'] ?? '';
        $apiKey = trim($_POST['api_key'] ?? '');
        $fromEmail = trim($_POST['from_email'] ?? '');
        $fromName = trim($_POST['from_name'] ?? '');
        
        if (empty($apiKey) || empty($fromEmail) || empty($fromName)) {
            $message = "All fields are required.";
            $messageType = 'error';
        } elseif (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            $message = "Please enter a valid email address.";
            $messageType = 'error';
        } else {
            // Read current credentials
            $credentialsFile = __DIR__ . '/../config/credentials.php';
            $credentials = require $credentialsFile;
            
            // Update credentials for the specified provider
            if (isset($credentials['email'][$provider])) {
                $credentials['email'][$provider]['api_key'] = $apiKey;
                $credentials['email'][$provider]['from_email'] = $fromEmail;
                $credentials['email'][$provider]['from_name'] = $fromName;
                
                // Write back to file
                $credentialsContent = "<?php\n\nreturn " . var_export($credentials, true) . ";\n";
                
                if (file_put_contents($credentialsFile, $credentialsContent)) {
                    $message = "{$provider} credentials updated successfully!";
                    $messageType = 'success';
                    $logger->info('Email provider credentials updated', ['provider' => $provider]);
                } else {
                    $message = "Failed to update configuration file.";
                    $messageType = 'error';
                    $logger->error('Failed to update email provider credentials', ['provider' => $provider]);
                }
            } else {
                $message = "Invalid provider.";
                $messageType = 'error';
            }
        }
    }
}

// Get current configuration
$credentials = require __DIR__ . '/../config/credentials.php';
$currentProvider = $credentials['email']['provider'] ?? 'sendgrid';
$availableProviders = EmailServiceFactory::getAvailableProviders();
$allProvidersStatus = EmailServiceFactory::testAllProviders();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Provider Configuration - 3DCart Integration</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        .content {
            padding: 30px;
        }
        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .config-section {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .config-section h3 {
            color: #495057;
            margin-bottom: 15px;
            font-size: 1.3em;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #495057;
        }
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
        }
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.25);
        }
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: transform 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        .btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
        }
        .provider-status {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }
        .status-indicator.success {
            background-color: #28a745;
        }
        .status-indicator.error {
            background-color: #dc3545;
        }
        .status-indicator.unknown {
            background-color: #6c757d;
        }
        .current-provider {
            background: #e3f2fd;
            border: 2px solid #2196f3;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .provider-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .provider-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            background: white;
        }
        .provider-card.active {
            border-color: #2196f3;
            background: #f3f8ff;
        }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #007bff;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        .credentials-form {
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚙️ Email Provider Configuration</h1>
            <p>Configure and switch between email service providers</p>
        </div>
        
        <div class="content">
            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Current Provider Status -->
            <div class="current-provider">
                <h3>📧 Current Email Provider</h3>
                <div class="provider-status">
                    <?php 
                    $currentStatus = $allProvidersStatus[$currentProvider] ?? ['success' => false];
                    $statusClass = $currentStatus['success'] ? 'success' : 'error';
                    ?>
                    <div class="status-indicator <?php echo $statusClass; ?>"></div>
                    <strong><?php echo htmlspecialchars($availableProviders[$currentProvider]['name']); ?></strong>
                    <span>(<?php echo $currentStatus['success'] ? 'Connected' : 'Failed'; ?>)</span>
                </div>
                <p><?php echo htmlspecialchars($availableProviders[$currentProvider]['description']); ?></p>
            </div>
            
            <!-- Provider Switching -->
            <div class="config-section">
                <h3>🔄 Switch Email Provider</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="switch_provider">
                    <div class="form-group">
                        <label for="provider">Select Email Provider:</label>
                        <select name="provider" id="provider" class="form-control" required>
                            <?php foreach ($availableProviders as $key => $provider): ?>
                                <option value="<?php echo $key; ?>" <?php echo $key === $currentProvider ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($provider['name']); ?> - <?php echo htmlspecialchars($provider['description']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn">Switch Provider</button>
                </form>
            </div>
            
            <!-- Provider Status Overview -->
            <div class="config-section">
                <h3>📊 All Providers Status</h3>
                <div class="provider-grid">
                    <?php foreach ($availableProviders as $key => $provider): ?>
                        <div class="provider-card <?php echo $key === $currentProvider ? 'active' : ''; ?>">
                            <h4><?php echo htmlspecialchars($provider['name']); ?></h4>
                            <p><?php echo htmlspecialchars($provider['description']); ?></p>
                            
                            <?php if (isset($allProvidersStatus[$key])): ?>
                                <div class="provider-status">
                                    <div class="status-indicator <?php echo $allProvidersStatus[$key]['success'] ? 'success' : 'error'; ?>"></div>
                                    <span><?php echo $allProvidersStatus[$key]['success'] ? 'Connected' : 'Connection Failed'; ?></span>
                                </div>
                                <?php if (!$allProvidersStatus[$key]['success'] && isset($allProvidersStatus[$key]['error'])): ?>
                                    <p><small style="color: #dc3545;"><?php echo htmlspecialchars($allProvidersStatus[$key]['error']); ?></small></p>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="provider-status">
                                    <div class="status-indicator unknown"></div>
                                    <span>Not Configured</span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($key === $currentProvider): ?>
                                <p><strong>✅ Currently Active</strong></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Credentials Configuration -->
            <div class="config-section">
                <h3>🔑 Configure Provider Credentials</h3>
                
                <?php foreach ($availableProviders as $key => $provider): ?>
                    <div class="provider-card">
                        <h4><?php echo htmlspecialchars($provider['name']); ?> Configuration</h4>
                        
                        <div class="credentials-form">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_credentials">
                                <input type="hidden" name="credential_provider" value="<?php echo $key; ?>">
                                
                                <div class="form-group">
                                    <label for="<?php echo $key; ?>_api_key">API Key:</label>
                                    <input type="password" 
                                           name="api_key" 
                                           id="<?php echo $key; ?>_api_key" 
                                           class="form-control" 
                                           value="<?php echo htmlspecialchars($credentials['email'][$key]['api_key'] ?? ''); ?>"
                                           placeholder="Enter your <?php echo $provider['name']; ?> API key"
                                           required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="<?php echo $key; ?>_from_email">From Email:</label>
                                    <input type="email" 
                                           name="from_email" 
                                           id="<?php echo $key; ?>_from_email" 
                                           class="form-control" 
                                           value="<?php echo htmlspecialchars($credentials['email'][$key]['from_email'] ?? ''); ?>"
                                           placeholder="sender@yourdomain.com"
                                           required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="<?php echo $key; ?>_from_name">From Name:</label>
                                    <input type="text" 
                                           name="from_name" 
                                           id="<?php echo $key; ?>_from_name" 
                                           class="form-control" 
                                           value="<?php echo htmlspecialchars($credentials['email'][$key]['from_name'] ?? ''); ?>"
                                           placeholder="Your Company Name"
                                           required>
                                </div>
                                
                                <button type="submit" class="btn">Update <?php echo $provider['name']; ?> Credentials</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div style="text-align: center; margin-top: 30px;">
                <a href="<?php echo UrlHelper::url('status.php'); ?>" class="back-link">← Back to Status Dashboard</a>
                <span style="margin: 0 20px;">|</span>
                <a href="<?php echo UrlHelper::url('test-email.php'); ?>" class="back-link">Test Email →</a>
            </div>
        </div>
    </div>
</body>
</html>