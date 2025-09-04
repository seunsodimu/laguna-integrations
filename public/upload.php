<?php
/**
 * Manual Upload Interface
 * 
 * Provides a web interface for manually uploading orders via CSV or Excel files.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Controllers\OrderController;
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

$logger = Logger::getInstance();
$message = '';
$messageType = '';
$results = null;

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['order_file'])) {
    try {
        $controller = new OrderController();
        $results = $controller->handleFileUpload();
        
        $message = "Upload processed successfully! {$results['successful']} orders processed, {$results['failed']} failed.";
        $messageType = 'success';
        
        $logger->info('Manual upload completed', [
            'total' => $results['total'],
            'successful' => $results['successful'],
            'failed' => $results['failed']
        ]);
        
    } catch (\Exception $e) {
        $message = 'Upload failed: ' . $e->getMessage();
        $messageType = 'error';
        
        $logger->error('Manual upload failed', [
            'error' => $e->getMessage()
        ]);
    }
}

$config = require __DIR__ . '/../config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual Upload - 3DCart Integration</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .content {
            padding: 30px;
        }
        .upload-form {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 30px;
            margin: 20px 0;
            border: 2px dashed #dee2e6;
            text-align: center;
            transition: all 0.3s ease;
        }
        .upload-form:hover {
            border-color: #667eea;
            background: #f0f4ff;
        }
        .upload-form.dragover {
            border-color: #667eea;
            background: #e3f2fd;
        }
        .file-input {
            display: none;
        }
        .file-input-label {
            display: inline-block;
            padding: 15px 30px;
            background: #667eea;
            color: white;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.2s;
            font-size: 1.1em;
            font-weight: 500;
        }
        .file-input-label:hover {
            background: #5a6fd8;
        }
        .upload-info {
            margin-top: 20px;
            color: #666;
        }
        .message {
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .message.success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .message.error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin: 20px 0;
            border-radius: 0 4px 4px 0;
        }
        .warning-box {
            background: #fff3e0;
            border-left: 4px solid #ff9800;
            padding: 15px;
            margin: 20px 0;
            border-radius: 0 4px 4px 0;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 5px;
            border: none;
            cursor: pointer;
            font-size: 1em;
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
        .results-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .results-table th,
        .results-table td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        .results-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        .results-table tr:nth-child(even) {
            background: #f8f9fa;
        }
        .status-success {
            color: #28a745;
            font-weight: bold;
        }
        .status-error {
            color: #dc3545;
            font-weight: bold;
        }
        .file-requirements {
            background: #f8f9fa;
            border-radius: 5px;
            padding: 20px;
            margin: 20px 0;
        }
        .file-requirements h4 {
            margin-top: 0;
            color: #333;
        }
        .file-requirements ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        .sample-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            font-size: 0.9em;
        }
        .sample-table th,
        .sample-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .sample-table th {
            background: #e9ecef;
            font-weight: 600;
        }
        .progress-bar {
            width: 100%;
            height: 20px;
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #20c997);
            transition: width 0.3s ease;
        }
    </style>
    <script>
        function handleFileSelect(event) {
            const file = event.target.files[0];
            if (file) {
                document.getElementById('selected-file').textContent = file.name;
                document.getElementById('file-size').textContent = formatFileSize(file.size);
                document.getElementById('file-info').style.display = 'block';
                document.getElementById('upload-btn').disabled = false;
            }
        }
        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        function handleDragOver(event) {
            event.preventDefault();
            event.currentTarget.classList.add('dragover');
        }
        
        function handleDragLeave(event) {
            event.currentTarget.classList.remove('dragover');
        }
        
        function handleDrop(event) {
            event.preventDefault();
            event.currentTarget.classList.remove('dragover');
            
            const files = event.dataTransfer.files;
            if (files.length > 0) {
                document.getElementById('order_file').files = files;
                handleFileSelect({ target: { files: files } });
            }
        }
        
        function submitForm() {
            const form = document.getElementById('upload-form');
            const submitBtn = document.getElementById('upload-btn');
            
            submitBtn.disabled = true;
            submitBtn.textContent = 'Processing...';
            
            form.submit();
        }
    </script>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📤 Manual Order Upload</h1>
            <p>Upload orders from CSV or Excel files</p>
        </div>
        
        <div class="content">
            <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($results): ?>
            <div class="info-box">
                <h3>📊 Upload Results</h3>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $results['total'] > 0 ? ($results['successful'] / $results['total']) * 100 : 0; ?>%"></div>
                </div>
                <p>
                    <strong>Total Orders:</strong> <?php echo $results['total']; ?><br>
                    <strong>Successful:</strong> <span class="status-success"><?php echo $results['successful']; ?></span><br>
                    <strong>Failed:</strong> <span class="status-error"><?php echo $results['failed']; ?></span><br>
                    <strong>Success Rate:</strong> <?php echo $results['total'] > 0 ? round(($results['successful'] / $results['total']) * 100, 2) : 0; ?>%
                </p>
            </div>
            
            <?php if (!empty($results['processed_orders'])): ?>
            <h3>✅ Successfully Processed Orders</h3>
            <table class="results-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>NetSuite Order ID</th>
                        <th>Customer ID</th>
                        <th>Row Number</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results['processed_orders'] as $order): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($order['order_id']); ?></td>
                        <td><?php echo htmlspecialchars($order['netsuite_order_id']); ?></td>
                        <td><?php echo htmlspecialchars($order['customer_id']); ?></td>
                        <td><?php echo htmlspecialchars($order['row_number']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
            
            <?php if (!empty($results['errors'])): ?>
            <h3>❌ Failed Orders</h3>
            <table class="results-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Row Number</th>
                        <th>Error</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results['errors'] as $error): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($error['order_id']); ?></td>
                        <td><?php echo htmlspecialchars($error['row_number']); ?></td>
                        <td><?php echo htmlspecialchars($error['error']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
            <?php endif; ?>
            
            <form id="upload-form" method="POST" enctype="multipart/form-data">
                <div class="upload-form" 
                     ondragover="handleDragOver(event)" 
                     ondragleave="handleDragLeave(event)" 
                     ondrop="handleDrop(event)">
                    <div style="font-size: 3em; margin-bottom: 15px;">📁</div>
                    <h3>Select or Drop Your File</h3>
                    <p>Choose a CSV or Excel file containing order data</p>
                    
                    <input type="file" 
                           id="order_file" 
                           name="order_file" 
                           class="file-input" 
                           accept=".csv,.xlsx,.xls" 
                           onchange="handleFileSelect(event)" 
                           required>
                    
                    <label for="order_file" class="file-input-label">
                        📂 Choose File
                    </label>
                    
                    <div id="file-info" style="display: none; margin-top: 15px;">
                        <p><strong>Selected:</strong> <span id="selected-file"></span></p>
                        <p><strong>Size:</strong> <span id="file-size"></span></p>
                    </div>
                    
                    <div class="upload-info">
                        <p>Supported formats: CSV, Excel (.xlsx, .xls)</p>
                        <p>Maximum file size: <?php echo number_format($config['upload']['max_file_size'] / (1024 * 1024), 1); ?>MB</p>
                    </div>
                </div>
                
                <div style="text-align: center; margin: 20px 0;">
                    <button type="button" id="upload-btn" class="btn" onclick="submitForm()" disabled>
                        🚀 Process Orders
                    </button>
                    <a href="<?php echo UrlHelper::url('index.php'); ?>" class="btn btn-secondary">🏠 Back to Dashboard</a>
                </div>
            </form>
            
            <div class="file-requirements">
                <h4>📋 File Format Requirements</h4>
                <p>Your file should contain the following columns (column names are case-insensitive):</p>
                
                <h5>Required Columns:</h5>
                <ul>
                    <li><strong>Order ID</strong> - Unique identifier for the order</li>
                    <li><strong>First Name</strong> - Customer's first name</li>
                    <li><strong>Last Name</strong> - Customer's last name</li>
                    <li><strong>Email</strong> - Customer's email address</li>
                    <li><strong>Item Name</strong> - Product name</li>
                    <li><strong>Quantity</strong> - Number of items</li>
                    <li><strong>Price</strong> - Unit price of the item</li>
                </ul>
                
                <h5>Optional Columns:</h5>
                <ul>
                    <li><strong>Company</strong> - Customer's company name</li>
                    <li><strong>Phone</strong> - Customer's phone number</li>
                    <li><strong>Address</strong> - Street address</li>
                    <li><strong>City</strong> - City name</li>
                    <li><strong>State</strong> - State or province</li>
                    <li><strong>Zip</strong> - Postal code</li>
                    <li><strong>Country</strong> - Country (defaults to US)</li>
                    <li><strong>SKU</strong> - Product SKU/Catalog ID</li>
                    <li><strong>Order Date</strong> - Date of the order</li>
                    <li><strong>Order Total</strong> - Total order amount</li>
                </ul>
                
                <h5>Sample CSV Format:</h5>
                <table class="sample-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>First Name</th>
                            <th>Last Name</th>
                            <th>Email</th>
                            <th>Item Name</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>SKU</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>ORD001</td>
                            <td>John</td>
                            <td>Doe</td>
                            <td>john.doe@example.com</td>
                            <td>Sample Product</td>
                            <td>2</td>
                            <td>49.99</td>
                            <td>PROD001</td>
                        </tr>
                        <tr>
                            <td>ORD002</td>
                            <td>Jane</td>
                            <td>Smith</td>
                            <td>jane.smith@example.com</td>
                            <td>Another Product</td>
                            <td>1</td>
                            <td>29.99</td>
                            <td>PROD002</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="warning-box">
                <h4>⚠️ Important Notes</h4>
                <ul>
                    <li>Each row represents one order with one item</li>
                    <li>For orders with multiple items, create separate rows with the same Order ID</li>
                    <li>Email addresses must be valid and unique per customer</li>
                    <li>If a customer already exists in NetSuite, they will be matched by email</li>
                    <li>New customers will be created automatically if enabled in configuration</li>
                    <li>Orders are processed sequentially - large files may take time</li>
                </ul>
            </div>
            
            <div class="info-box">
                <h4>🔄 Processing Flow</h4>
                <ol>
                    <li><strong>File Upload:</strong> Your file is uploaded and validated</li>
                    <li><strong>Data Parsing:</strong> Order data is extracted and normalized</li>
                    <li><strong>Validation:</strong> Each order is validated for required fields</li>
                    <li><strong>Customer Processing:</strong> Customers are found or created in NetSuite</li>
                    <li><strong>Order Creation:</strong> Sales orders are created in NetSuite</li>
                    <li><strong>Notification:</strong> Email notification sent with results</li>
                </ol>
            </div>
        </div>
    </div>
</body>
</html>