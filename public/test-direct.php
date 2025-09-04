<?php
/**
 * Direct Access Test (Requires Authentication)
 * Test if we can access files directly in the public directory
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Middleware\AuthMiddleware;

// Require authentication
$auth = new AuthMiddleware();
$currentUser = $auth->requireAuth();
if (!$currentUser) {
    exit; // Middleware handles redirect
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Direct Access Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .success { color: green; font-weight: bold; }
        .info { background: #f0f0f0; padding: 10px; margin: 10px 0; }
    </style>
</head>
<body>
    <h1 class="success">✅ Direct Access Working!</h1>
    
    <div class="info">
        <strong>Current URL:</strong> <?php echo $_SERVER['REQUEST_URI']; ?><br>
        <strong>Script Path:</strong> <?php echo $_SERVER['SCRIPT_NAME']; ?><br>
        <strong>Document Root:</strong> <?php echo $_SERVER['DOCUMENT_ROOT']; ?><br>
        <strong>Current Directory:</strong> <?php echo __DIR__; ?><br>
        <strong>Time:</strong> <?php echo date('Y-m-d H:i:s'); ?>
    </div>
    
    <h2>Test Links</h2>
    <ul>
        <li><a href="index.php">Dashboard (index.php)</a></li>
        <li><a href="status.php">Status Page (status.php)</a></li>
        <li><a href="upload.php">Upload Page (upload.php)</a></li>
        <li><a href="webhook.php">Webhook Endpoint (webhook.php)</a></li>
        <li><a href="../debug.php">Debug Script (../debug.php)</a></li>
        <li><a href="../test.php">Installation Test (../test.php)</a></li>
    </ul>
    
    <h2>Next Steps</h2>
    <p>If you can see this page, direct access to the public directory is working.</p>
    <p>Now try accessing the main dashboard at: <a href="index.php">index.php</a></p>
</body>
</html>