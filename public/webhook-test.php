<?php
/**
 * System Diagnostic Test (Requires Authentication)
 */

// Load authentication first
require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Middleware\AuthMiddleware;

// Require authentication
$auth = new AuthMiddleware();
$currentUser = $auth->requireAuth();
if (!$currentUser) {
    exit; // Middleware handles redirect
}

// Simple test file to check if basic PHP works
echo "PHP is working!";
echo "<br>Current directory: " . __DIR__;
echo "<br>File exists check: " . (file_exists(__DIR__ . '/../vendor/autoload.php') ? 'YES' : 'NO');

// Try to load config
try {
    $config = require __DIR__ . '/../config/config.php';
    echo "<br>Config loaded successfully";
    echo "<br>Webhook enabled: " . ($config['webhook']['enabled'] ? 'true' : 'false');
} catch (Exception $e) {
    echo "<br>Config error: " . $e->getMessage();
}

// Autoloader already loaded above
echo "<br>Autoloader loaded successfully";
?>