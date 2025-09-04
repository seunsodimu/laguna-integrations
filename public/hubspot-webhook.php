<?php
/**
 * HubSpot Webhook Endpoint
 * 
 * Receives webhook notifications from HubSpot for contact property changes
 * and processes lead synchronization to NetSuite.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Services\HubSpotService;
use Laguna\Integration\Utils\Logger;

// Set timezone
date_default_timezone_set('America/New_York');

// Initialize services
$logger = Logger::getInstance();
$hubspotService = new HubSpotService();
$config = require __DIR__ . '/../config/config.php';

// Log incoming request
$logger->info('HubSpot webhook endpoint accessed', [
    'method' => $_SERVER['REQUEST_METHOD'],
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
]);

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $logger->warning('Invalid request method for HubSpot webhook', [
        'method' => $_SERVER['REQUEST_METHOD']
    ]);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Get raw POST data
    $rawPayload = file_get_contents('php://input');
    
    if (empty($rawPayload)) {
        throw new \Exception('Empty payload received');
    }
    
    // Log raw payload for debugging
    $logger->info('HubSpot webhook raw payload received', [
        'payload_length' => strlen($rawPayload),
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'Unknown'
    ]);
    
    // Decode JSON payload
    $payload = json_decode($rawPayload, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new \Exception('Invalid JSON payload: ' . json_last_error_msg());
    }
    
    // Verify webhook signature if configured
    $signature = $_SERVER['HTTP_X_HUBSPOT_SIGNATURE'] ?? $_SERVER['HTTP_X_HUBSPOT_SIGNATURE_V2'] ?? null;
    if ($signature && !empty($config['hubspot']['webhook_secret'])) {
        if (!$hubspotService->verifyWebhookSignature($rawPayload, $signature)) {
            http_response_code(401);
            $logger->warning('HubSpot webhook signature verification failed');
            echo json_encode(['error' => 'Signature verification failed']);
            exit;
        }
    }
    
    // Log processed payload
    $logger->info('HubSpot webhook payload processed', [
        'subscription_type' => $payload['subscriptionType'] ?? 'Unknown',
        'object_id' => $payload['objectId'] ?? 'Unknown',
        'property_name' => $payload['propertyName'] ?? 'Unknown',
        'property_value' => $payload['propertyValue'] ?? 'Unknown'
    ]);
    
    // Process the webhook
    $result = $hubspotService->processWebhook($payload);
    
    if ($result['success']) {
        http_response_code(200);
        $logger->info('HubSpot webhook processed successfully', [
            'object_id' => $payload['objectId'] ?? 'Unknown',
            'message' => $result['message'] ?? 'Success'
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => $result['message'] ?? 'Webhook processed successfully'
        ]);
    } else {
        http_response_code(400);
        $logger->error('HubSpot webhook processing failed', [
            'object_id' => $payload['objectId'] ?? 'Unknown',
            'error' => $result['error']
        ]);
        
        echo json_encode([
            'success' => false,
            'error' => $result['error']
        ]);
    }
    
} catch (\Exception $e) {
    http_response_code(500);
    $error = 'HubSpot webhook processing error: ' . $e->getMessage();
    
    $logger->error($error, [
        'exception' => get_class($e),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}