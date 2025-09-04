<?php
/**
 * Webhook Test Utility
 * 
 * This utility helps test the webhook endpoint with sample data.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Controllers\WebhookController;
use Laguna\Integration\Utils\Logger;
use Laguna\Integration\Middleware\AuthMiddleware;

// Require authentication
$auth = new AuthMiddleware();
$currentUser = $auth->requireAuth();
if (!$currentUser) {
    exit; // Middleware handles redirect
}

// Set timezone
date_default_timezone_set('America/New_York');

// Initialize logger
$logger = Logger::getInstance();

// Handle different request methods
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    // Handle test webhook POST request
    try {
        $action = $_POST['action'] ?? 'test';
        
        if ($action === 'test') {
            // Use real 3DCart order data for testing
            $testOrderData = [
                'OrderID' => '1108410',
                'CustomerID' => '341',
                'OrderDate' => '2025-08-03 10:30:00',
                'OrderStatusID' => 1,
                'OrderTotal' => 11921.5,
                'BillingFirstName' => 'Logan',
                'BillingLastName' => 'Williams',
                'BillingEmail' => 'lwilliams@oaktreesupplies.com',
                'BillingAddress' => '14110 Plank Street',
                'BillingCity' => 'Fort Wayne',
                'BillingState' => 'IN',
                'BillingZipCode' => '46818',
                'BillingCountry' => 'US',
                'OrderItemList' => [
                    [
                        'CatalogID' => 'XP2001',
                        'ItemName' => 'XP|20 20 Flexible Roller Conveyor Table',
                        'Quantity' => 1,
                        'ItemPrice' => 329
                    ]
                ],
                '_test' => true
            ];
            
            // Simulate webhook processing
            $controller = new WebhookController();
            $result = $controller->processOrderFromWebhookData($testOrderData);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Test webhook processed',
                'result' => $result,
                'test_data' => $testOrderData,
                'timestamp' => date('c')
            ]);
        }
    } catch (\Exception $e) {
        $logger->error('Test webhook failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'timestamp' => date('c')
        ]);
    }
} else {
    // Handle GET request - show test interface
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Webhook Test Utility - 3DCart Integration</title>
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
                background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
                color: white;
                padding: 30px;
                text-align: center;
            }
            .content {
                padding: 30px;
            }
            .test-section {
                background: #f8f9fa;
                border: 1px solid #e9ecef;
                border-radius: 5px;
                padding: 20px;
                margin: 20px 0;
            }
            .btn {
                display: inline-block;
                padding: 12px 24px;
                background: #28a745;
                color: white;
                text-decoration: none;
                border-radius: 5px;
                border: none;
                cursor: pointer;
                font-size: 16px;
                margin: 5px;
            }
            .btn:hover {
                background: #218838;
            }
            .btn-secondary {
                background: #6c757d;
            }
            .btn-secondary:hover {
                background: #5a6268;
            }
            .result-box {
                background: #e8f5e8;
                border: 1px solid #4caf50;
                border-radius: 5px;
                padding: 15px;
                margin: 20px 0;
                display: none;
            }
            .error-box {
                background: #ffebee;
                border: 1px solid #f44336;
                border-radius: 5px;
                padding: 15px;
                margin: 20px 0;
                display: none;
            }
            pre {
                background: #f8f9fa;
                border: 1px solid #e9ecef;
                border-radius: 5px;
                padding: 15px;
                overflow-x: auto;
                white-space: pre-wrap;
            }
            .loading {
                display: none;
                text-align: center;
                padding: 20px;
            }
            .spinner {
                border: 4px solid #f3f3f3;
                border-top: 4px solid #28a745;
                border-radius: 50%;
                width: 40px;
                height: 40px;
                animation: spin 1s linear infinite;
                margin: 0 auto;
            }
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🧪 Webhook Test Utility</h1>
                <p>Test your webhook endpoint with sample data</p>
            </div>
            
            <div class="content">
                <div class="test-section">
                    <h3>🚀 Quick Test</h3>
                    <p>Click the button below to test the webhook with sample order data:</p>
                    <button class="btn" onclick="runTest()">Run Test Webhook</button>
                    <a href="webhook.php" class="btn btn-secondary">View Webhook Info</a>
                    <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
                </div>
                
                <div class="loading" id="loading">
                    <div class="spinner"></div>
                    <p>Processing test webhook...</p>
                </div>
                
                <div class="result-box" id="result-box">
                    <h4>✅ Test Results</h4>
                    <pre id="result-content"></pre>
                </div>
                
                <div class="error-box" id="error-box">
                    <h4>❌ Test Error</h4>
                    <pre id="error-content"></pre>
                </div>
                
                <div class="test-section">
                    <h3>📋 Sample Test Data</h3>
                    <p>The test will use the following real 3DCart order data:</p>
                    <pre><code>{
  "OrderID": "1108410",
  "CustomerID": "341",
  "OrderDate": "2025-08-03 10:30:00",
  "OrderStatusID": 1,
  "OrderTotal": 11921.5,
  "BillingFirstName": "Logan",
  "BillingLastName": "Williams",
  "BillingEmail": "lwilliams@oaktreesupplies.com",
  "BillingAddress": "14110 Plank Street",
  "BillingCity": "Fort Wayne",
  "BillingState": "IN",
  "BillingZipCode": "46818",
  "BillingCountry": "US",
  "OrderItemList": [
    {
      "CatalogID": "XP2001",
      "ItemName": "XP|20 20 Flexible Roller Conveyor Table",
      "Quantity": 1,
      "ItemPrice": 329
    }
  ],
  "_test": true
}</code></pre>
                </div>
                
                <div class="test-section">
                    <h3>🔍 What This Test Does</h3>
                    <ul>
                        <li>Creates sample order data with all required fields</li>
                        <li>Validates the order data structure</li>
                        <li>Tests customer creation/lookup logic</li>
                        <li>Simulates the complete webhook processing flow</li>
                        <li>Returns detailed results and any errors</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <script>
            function runTest() {
                // Show loading
                document.getElementById('loading').style.display = 'block';
                document.getElementById('result-box').style.display = 'none';
                document.getElementById('error-box').style.display = 'none';
                
                // Make AJAX request
                fetch('test-webhook.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=test'
                })
                .then(response => response.json())
                .then(data => {
                    document.getElementById('loading').style.display = 'none';
                    
                    if (data.success) {
                        document.getElementById('result-content').textContent = JSON.stringify(data, null, 2);
                        document.getElementById('result-box').style.display = 'block';
                    } else {
                        document.getElementById('error-content').textContent = JSON.stringify(data, null, 2);
                        document.getElementById('error-box').style.display = 'block';
                    }
                })
                .catch(error => {
                    document.getElementById('loading').style.display = 'none';
                    document.getElementById('error-content').textContent = 'Network error: ' + error.message;
                    document.getElementById('error-box').style.display = 'block';
                });
            }
        </script>
    </body>
    </html>
    <?php
}
?>