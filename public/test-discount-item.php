<?php
/**
 * Test Discount Item Validation
 * 
 * This script tests if the discount item ID exists in NetSuite
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Services\NetSuiteService;
use Laguna\Integration\Utils\Logger;
use Laguna\Integration\Middleware\AuthMiddleware;

// Start output buffering
ob_start();

// Set JSON content type
header('Content-Type: application/json');

try {
    // Check authentication
    $auth = new AuthMiddleware();
    if (!$auth->isAuthenticated()) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'error' => 'Authentication required. Please log in.',
            'redirect' => 'login.php'
        ]);
        exit;
    }
    
    // Load configuration
    $config = require __DIR__ . '/../config/config.php';
    date_default_timezone_set($config['app']['timezone']);
    
    // Initialize NetSuite service
    $netSuiteService = new NetSuiteService();
    
    // Test discount item ID from config
    $discountItemId = $config['netsuite']['discount_item_id'];
    
    $response = [
        'success' => true,
        'discount_item_id' => $discountItemId,
        'current_config' => [
            'include_discount_as_line_item' => $config['netsuite']['include_discount_as_line_item'],
            'discount_item_id' => $discountItemId
        ]
    ];
    
    // Test if discount item exists
    try {
        $itemValidation = $netSuiteService->validateItem($discountItemId);
        $response['item_validation'] = $itemValidation;
        
        if ($itemValidation['exists'] && $itemValidation['usable']) {
            $response['recommendation'] = 'Enable discount as line item - item exists and is usable';
            $response['action_needed'] = 'Set include_discount_as_line_item to true';
        } else {
            $response['recommendation'] = 'Use order-level discount - item does not exist or is not usable';
            $response['action_needed'] = 'Modify sales order creation to use discountTotal field';
        }
        
    } catch (Exception $e) {
        $response['item_validation'] = [
            'error' => $e->getMessage(),
            'exists' => false,
            'usable' => false
        ];
        $response['recommendation'] = 'Use order-level discount - item validation failed';
        $response['action_needed'] = 'Modify sales order creation to use discountTotal field';
    }
    
    // Test current discount configuration effectiveness
    $response['current_status'] = [
        'discount_enabled' => $config['netsuite']['include_discount_as_line_item'],
        'will_apply_discount' => $config['netsuite']['include_discount_as_line_item'] && 
                                ($response['item_validation']['exists'] ?? false) && 
                                ($response['item_validation']['usable'] ?? false),
        'issue' => !$config['netsuite']['include_discount_as_line_item'] ? 
                   'Discount processing is disabled in configuration' : 
                   (!(($response['item_validation']['exists'] ?? false) && ($response['item_validation']['usable'] ?? false)) ? 
                    'Discount item does not exist or is not usable' : 
                    'Configuration appears correct')
    ];
    
    ob_clean();
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => true
    ]);
}
?>