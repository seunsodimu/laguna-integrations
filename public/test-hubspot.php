<?php
/**
 * HubSpot Integration Test Page
 * 
 * Test HubSpot API connection and webhook processing functionality.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Services\HubSpotService;
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

$hubspotService = new HubSpotService();
$testResults = [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['test_connection'])) {
        $testResults['connection'] = $hubspotService->testConnection();
    }
    
    if (isset($_POST['test_contact']) && !empty($_POST['contact_id'])) {
        $contactId = trim($_POST['contact_id']);
        $testResults['contact'] = $hubspotService->getContact($contactId);
    }
    
    if (isset($_POST['test_webhook']) && !empty($_POST['webhook_payload'])) {
        $payload = json_decode($_POST['webhook_payload'], true);
        if ($payload) {
            $testResults['webhook'] = $hubspotService->processWebhook($payload);
        } else {
            $testResults['webhook'] = [
                'success' => false,
                'error' => 'Invalid JSON payload'
            ];
        }
    }
    
    if (isset($_POST['test_update']) && !empty($_POST['update_contact_id']) && !empty($_POST['update_netsuite_id'])) {
        $contactId = trim($_POST['update_contact_id']);
        $netsuiteId = trim($_POST['update_netsuite_id']);
        $testResults['update'] = $hubspotService->updateContactNetSuiteId($contactId, $netsuiteId);
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HubSpot Integration Test - Laguna Integrations</title>
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
            background: linear-gradient(135deg, #ff7a59 0%, #ff6b4a 100%);
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
        .test-section {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            background: #fafafa;
        }
        .test-section h3 {
            margin: 0 0 15px 0;
            color: #333;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }
        .form-group textarea {
            height: 120px;
            font-family: monospace;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #ff7a59;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin: 5px 5px 5px 0;
            font-size: 14px;
            border: none;
            cursor: pointer;
        }
        .btn:hover {
            background: #ff6b4a;
        }
        .result-box {
            margin-top: 15px;
            padding: 15px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
            white-space: pre-wrap;
        }
        .result-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .result-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .sample-payload {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            padding: 10px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="user-info">
                Welcome, <?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?>
                <a href="<?php echo UrlHelper::url('index.php'); ?>">🏠 Dashboard</a>
                <a href="<?php echo UrlHelper::url('hubspot-status.php'); ?>">📊 Status</a>
                <a href="<?php echo UrlHelper::url('logout.php'); ?>">🚪 Logout</a>
            </div>
            <h1>🧪 HubSpot Integration Test</h1>
            <p>Test HubSpot API connection and webhook processing</p>
        </div>
        
        <div class="content">
            <!-- Connection Test -->
            <div class="test-section">
                <h3>🔗 Connection Test</h3>
                <p>Test the connection to HubSpot API to verify credentials and connectivity.</p>
                
                <form method="post">
                    <button type="submit" name="test_connection" class="btn">Test Connection</button>
                </form>
                
                <?php if (isset($testResults['connection'])): ?>
                <div class="result-box <?php echo $testResults['connection']['success'] ? 'result-success' : 'result-error'; ?>">
<?php echo json_encode($testResults['connection'], JSON_PRETTY_PRINT); ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Contact Retrieval Test -->
            <div class="test-section">
                <h3>👤 Contact Retrieval Test</h3>
                <p>Test retrieving a specific contact from HubSpot by ID.</p>
                
                <form method="post">
                    <div class="form-group">
                        <label for="contact_id">Contact ID:</label>
                        <input type="text" id="contact_id" name="contact_id" placeholder="e.g., 151203590532" value="<?php echo htmlspecialchars($_POST['contact_id'] ?? ''); ?>">
                    </div>
                    <button type="submit" name="test_contact" class="btn">Get Contact</button>
                </form>
                
                <?php if (isset($testResults['contact'])): ?>
                <div class="result-box <?php echo $testResults['contact']['success'] ? 'result-success' : 'result-error'; ?>">
<?php echo json_encode($testResults['contact'], JSON_PRETTY_PRINT); ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Webhook Processing Test -->
            <div class="test-section">
                <h3>🔄 Webhook Processing Test</h3>
                <p>Test webhook payload processing with a sample HubSpot webhook payload.</p>
                
                <form method="post">
                    <div class="form-group">
                        <label for="webhook_payload">Webhook Payload (JSON):</label>
                        <textarea id="webhook_payload" name="webhook_payload" placeholder="Paste HubSpot webhook payload here..."><?php echo htmlspecialchars($_POST['webhook_payload'] ?? ''); ?></textarea>
                    </div>
                    <button type="submit" name="test_webhook" class="btn">Process Webhook</button>
                    <button type="button" class="btn" onclick="loadSamplePayload()">Load Sample</button>
                </form>
                
                <div class="sample-payload">
                    <strong>Sample Webhook Payload:</strong>
                    <pre>{
  "appId": 18835995,
  "eventId": 100,
  "subscriptionId": 123,
  "portalId": 5403187,
  "occurredAt": 1756420771387,
  "subscriptionType": "contact.propertyChange",
  "attemptNumber": 0,
  "objectId": 151203590532,
  "changeSource": "CRM",
  "propertyName": "hubspot_owner_id",
  "propertyValue": 39877790
}</pre>
                </div>
                
                <?php if (isset($testResults['webhook'])): ?>
                <div class="result-box <?php echo $testResults['webhook']['success'] ? 'result-success' : 'result-error'; ?>">
<?php echo json_encode($testResults['webhook'], JSON_PRETTY_PRINT); ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- HubSpot Update Test -->
            <div class="test-section">
                <h3>🔄 HubSpot Contact Update Test</h3>
                <p>Test updating a HubSpot contact with a NetSuite customer ID.</p>
                
                <form method="post">
                    <div class="form-group">
                        <label for="update_contact_id">HubSpot Contact ID:</label>
                        <input type="text" id="update_contact_id" name="update_contact_id" placeholder="e.g., 151203590532" value="<?php echo htmlspecialchars($_POST['update_contact_id'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="update_netsuite_id">NetSuite Customer ID:</label>
                        <input type="text" id="update_netsuite_id" name="update_netsuite_id" placeholder="e.g., 472524" value="<?php echo htmlspecialchars($_POST['update_netsuite_id'] ?? ''); ?>">
                    </div>
                    <button type="submit" name="test_update" class="btn">Update Contact</button>
                </form>
                
                <?php if (isset($testResults['update'])): ?>
                <div class="result-box <?php echo $testResults['update']['success'] ? 'result-success' : 'result-error'; ?>">
<?php echo json_encode($testResults['update'], JSON_PRETTY_PRINT); ?>
                </div>
                <?php endif; ?>
            </div>
            
            <div style="background: #e3f2fd; border-left: 4px solid #2196f3; padding: 15px; margin: 20px 0; border-radius: 0 4px 4px 0;">
                <h4>📋 Testing Notes</h4>
                <ul>
                    <li><strong>Connection Test:</strong> Verifies HubSpot API credentials and connectivity</li>
                    <li><strong>Contact Test:</strong> Requires a valid HubSpot contact ID from your account</li>
                    <li><strong>Webhook Test:</strong> Simulates webhook processing without actual HubSpot trigger</li>
                    <li><strong>Update Test:</strong> Tests updating HubSpot contact with NetSuite customer ID</li>
                    <li><strong>Lead Creation:</strong> Webhook test will attempt to create leads in NetSuite if conditions are met</li>
                    <li><strong>Automatic Updates:</strong> When leads are created via webhook, HubSpot is automatically updated with the NetSuite ID</li>
                </ul>
            </div>
        </div>
    </div>
    
    <script>
        function loadSamplePayload() {
            document.getElementById('webhook_payload').value = `{
  "appId": 18835995,
  "eventId": 100,
  "subscriptionId": 123,
  "portalId": 5403187,
  "occurredAt": 1756420771387,
  "subscriptionType": "contact.propertyChange",
  "attemptNumber": 0,
  "objectId": 151203590532,
  "changeSource": "CRM",
  "propertyName": "hubspot_owner_id",
  "propertyValue": 39877790
}`;
        }
    </script>
</body>
</html>