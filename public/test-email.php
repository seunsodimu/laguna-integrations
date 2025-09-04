<?php
/**
 * Email Test Page
 * 
 * Test SendGrid email functionality with different email types.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Services\UnifiedEmailService;
use Laguna\Integration\Services\EmailServiceFactory;
use Laguna\Integration\Utils\Logger;
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

$emailService = new UnifiedEmailService();
$logger = Logger::getInstance();

$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $toEmail = trim($_POST['to_email'] ?? '');
    $testType = $_POST['test_type'] ?? 'basic';
    
    if (empty($toEmail)) {
        $message = 'Please enter a valid email address.';
        $messageType = 'error';
    } elseif (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address format.';
        $messageType = 'error';
    } else {
        try {
            $result = $emailService->sendTestEmail($toEmail, $testType);
            
            if ($result['success']) {
                $message = "Test email sent successfully to {$toEmail}! Status Code: {$result['status_code']}";
                $messageType = 'success';
                
                // Log additional details if available
                if (isset($result['response_body'])) {
                    $logger->info('Test email response details', [
                        'to_email' => $toEmail,
                        'test_type' => $testType,
                        'response_body' => $result['response_body']
                    ]);
                }
            } else {
                $message = "Failed to send test email: " . ($result['message'] ?? 'Unknown error');
                $messageType = 'error';
                
                if (isset($result['error'])) {
                    $message .= " - " . $result['error'];
                }
            }
        } catch (Exception $e) {
            $message = "Error sending test email: " . $e->getMessage();
            $messageType = 'error';
            
            $logger->error('Test email page error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}

// Test email service connection
$connectionTest = $emailService->testConnection();
$connectionStatus = $connectionTest['success'] ? 'Connected' : 'Failed';
$connectionClass = $connectionTest['success'] ? 'success' : 'error';
$currentProvider = $emailService->getProviderInfo();

// Check account status and quota
$accountStatus = $emailService->checkAccountStatus();
$quotaExceeded = $accountStatus['quota_exceeded'] ?? false;

// Get all providers status
$allProvidersStatus = $emailService->getAllProvidersStatus();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Test - 3DCart Integration</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
            color: #333;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .content {
            padding: 30px;
        }
        .status-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            position: relative;
        }
        .status-card.success {
            border-left: 4px solid #4caf50;
            background: #f8fff8;
        }
        .status-card.error {
            border-left: 4px solid #f44336;
            background: #fff8f8;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }
        .btn {
            background: #007bff;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }
        .btn:hover {
            background: #0056b3;
        }
        .btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }
        .message {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .info-box {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .test-types {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        .test-type-option {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #f8f9fa;
            font-size: 12px;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📧 Email Test</h1>
            <p>Test email functionality using <strong><?php echo htmlspecialchars($currentProvider['name']); ?></strong></p>
            <p><small><?php echo htmlspecialchars($currentProvider['description']); ?></small></p>
        </div>
        
        <div class="content">
            <!-- Connection Status -->
            <div class="status-card <?php echo $connectionClass; ?>">
                <h3><?php echo htmlspecialchars($currentProvider['name']); ?> Connection Status</h3>
                <p><strong>Status:</strong> <?php echo $connectionStatus; ?></p>
                <p><strong>Provider:</strong> <?php echo htmlspecialchars($currentProvider['name']); ?></p>
                <?php if (!$connectionTest['success']): ?>
                    <p><strong>Error:</strong> <?php echo htmlspecialchars($connectionTest['error'] ?? 'Unknown error'); ?></p>
                <?php endif; ?>
            </div>
            
            <!-- Account Status -->
            <?php if ($quotaExceeded): ?>
            <div class="status-card error">
                <h3>⚠️ <?php echo htmlspecialchars($currentProvider['name']); ?> Account Status</h3>
                <p><strong>Status:</strong> <span style="color: #dc3545;">Quota Exceeded</span></p>
                <p><strong>Issue:</strong> Your <?php echo htmlspecialchars($currentProvider['name']); ?> account has exceeded its maximum credits/quota.</p>
                <p><strong>Solution:</strong> 
                    <ul>
                        <li>Check your <?php echo htmlspecialchars($currentProvider['name']); ?> dashboard for quota limits</li>
                        <li>Upgrade your <?php echo htmlspecialchars($currentProvider['name']); ?> plan if needed</li>
                        <li>Wait for quota reset (if on free plan)</li>
                        <li>Contact <?php echo htmlspecialchars($currentProvider['name']); ?> support for assistance</li>
                    </ul>
                </p>
            </div>
            <?php elseif ($connectionTest['success']): ?>
            <div class="status-card success">
                <h3>✅ <?php echo htmlspecialchars($currentProvider['name']); ?> Account Status</h3>
                <p><strong>Status:</strong> <span style="color: #28a745;">Active</span></p>
                <p>Your <?php echo htmlspecialchars($currentProvider['name']); ?> account appears to be active and ready to send emails.</p>
            </div>
            <?php endif; ?>
            
            <!-- All Providers Status -->
            <?php if (count($allProvidersStatus) > 1): ?>
            <div class="status-card">
                <h3>📊 All Email Providers Status</h3>
                <?php foreach ($allProvidersStatus as $provider => $status): ?>
                    <div style="margin: 10px 0; padding: 10px; background: <?php echo $status['success'] ? '#d4edda' : '#f8d7da'; ?>; border-radius: 4px;">
                        <strong><?php echo htmlspecialchars($status['service']); ?>:</strong> 
                        <span style="color: <?php echo $status['success'] ? '#155724' : '#721c24'; ?>;">
                            <?php echo $status['success'] ? '✅ Connected' : '❌ Failed'; ?>
                        </span>
                        <?php if (!$status['success'] && isset($status['error'])): ?>
                            <br><small><?php echo htmlspecialchars($status['error']); ?></small>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <!-- Message Display -->
            <?php if (!empty($message)): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Information Box -->
            <div class="info-box">
                <h4>📋 How to Use This Test</h4>
                <ol>
                    <li>Enter your email address below</li>
                    <li>Select the type of test email you want to send</li>
                    <li>Click "Send Test Email"</li>
                    <li>Check your email inbox (and spam folder)</li>
                    <li>Check the logs for detailed information</li>
                </ol>
                <p><strong>Note:</strong> If emails are not arriving, check your SendGrid account dashboard for delivery logs and any potential issues.</p>
            </div>
            
            <!-- Test Form -->
            <form method="POST">
                <div class="form-group">
                    <label for="to_email">Email Address:</label>
                    <input type="email" id="to_email" name="to_email" 
                           value="<?php echo htmlspecialchars($_POST['to_email'] ?? ''); ?>" 
                           placeholder="Enter your email address" required>
                </div>
                
                <div class="form-group">
                    <label for="test_type">Test Email Type:</label>
                    <select id="test_type" name="test_type">
                        <option value="basic" <?php echo ($_POST['test_type'] ?? '') === 'basic' ? 'selected' : ''; ?>>
                            Basic Test Email
                        </option>
                        <option value="order" <?php echo ($_POST['test_type'] ?? '') === 'order' ? 'selected' : ''; ?>>
                            Order Notification Test
                        </option>
                        <option value="error" <?php echo ($_POST['test_type'] ?? '') === 'error' ? 'selected' : ''; ?>>
                            Error Notification Test
                        </option>
                        <option value="connection" <?php echo ($_POST['test_type'] ?? '') === 'connection' ? 'selected' : ''; ?>>
                            Connection Alert Test
                        </option>
                    </select>
                </div>
                
                <div class="test-types">
                    <div class="test-type-option">
                        <strong>Basic:</strong> Simple test email with configuration details
                    </div>
                    <div class="test-type-option">
                        <strong>Order:</strong> Sample order notification email
                    </div>
                    <div class="test-type-option">
                        <strong>Error:</strong> Sample error notification email
                    </div>
                    <div class="test-type-option">
                        <strong>Connection:</strong> Sample connection alert email
                    </div>
                </div>
                
                <button type="submit" class="btn" <?php echo (!$connectionTest['success'] || $quotaExceeded) ? 'disabled' : ''; ?>>
                    Send Test Email
                </button>
                
                <?php if (!$connectionTest['success']): ?>
                    <p style="color: #721c24; margin-top: 10px;">
                        <small>Email testing is disabled because SendGrid connection failed. Please check your credentials.</small>
                    </p>
                <?php elseif ($quotaExceeded): ?>
                    <p style="color: #721c24; margin-top: 10px;">
                        <small>Email testing is disabled because your SendGrid quota has been exceeded. Please check your SendGrid account.</small>
                    </p>
                <?php endif; ?>
            </form>
            
            <!-- Additional Information -->
            <div class="info-box" style="margin-top: 30px;">
                <h4>🔍 Troubleshooting</h4>
                <ul>
                    <li><strong>Email not received:</strong> Check spam folder, verify SendGrid API key</li>
                    <li><strong>Connection failed:</strong> Verify API key in config/credentials.php</li>
                    <li><strong>Invalid from address:</strong> Ensure from_email is verified in SendGrid</li>
                    <li><strong>Rate limiting:</strong> SendGrid may have rate limits on your account</li>
                </ul>
                <p><strong>Check logs:</strong> View logs/app-<?php echo date('Y-m-d'); ?>.log for detailed error information</p>
            </div>
            
            <a href="<?php echo UrlHelper::url('status.php'); ?>" class="back-link">← Back to Status Dashboard</a>
        </div>
    </div>
</body>
</html>