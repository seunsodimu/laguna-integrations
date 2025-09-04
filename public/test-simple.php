<?php
/**
 * Simple Test Script
 */

header('Content-Type: application/json');

echo json_encode([
    'success' => true,
    'message' => 'Test script is working!',
    'timestamp' => date('Y-m-d H:i:s'),
    'server_info' => [
        'php_version' => PHP_VERSION,
        'request_method' => $_SERVER['REQUEST_METHOD'],
        'post_data' => $_POST
    ]
]);
?>