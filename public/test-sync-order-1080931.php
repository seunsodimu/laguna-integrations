<?php
/**
 * Test Sync Order 1080931
 * 
 * This script tests syncing the specific order 1080931 with enhanced debugging
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Controllers\OrderController;
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
    
    $response = [
        'success' => true,
        'steps' => []
    ];
    
    $orderId = '1080931';
    $response['steps'][] = "Testing sync for 3DCart order {$orderId}...";
    
    // Step 1: Initialize OrderController
    $response['steps'][] = 'Step 1: Initializing OrderController...';
    
    try {
        $orderController = new OrderController();
        $response['steps'][] = "✅ OrderController initialized successfully";
    } catch (Exception $e) {
        $response['steps'][] = "❌ Failed to initialize OrderController: " . $e->getMessage();
        throw $e;
    }
    
    // Step 2: Check current configuration
    $response['steps'][] = 'Step 2: Checking current configuration...';
    
    $config = require __DIR__ . '/../config/config.php';
    $defaultItemId = $config['netsuite']['default_item_id'];
    $createMissingItems = $config['netsuite']['create_missing_items'];
    
    $response['current_config'] = [
        'default_item_id' => $defaultItemId,
        'create_missing_items' => $createMissingItems
    ];
    
    $response['steps'][] = "Current default_item_id: {$defaultItemId}";
    $response['steps'][] = "Create missing items: " . ($createMissingItems ? 'enabled' : 'disabled');
    
    if ($defaultItemId == 1) {
        $response['steps'][] = "⚠️ WARNING: default_item_id is set to 1, which likely doesn't exist in NetSuite";
    }
    
    // Step 3: Attempt to sync the order
    $response['steps'][] = 'Step 3: Attempting to sync order...';
    
    try {
        $syncResult = $orderController->syncOrder($orderId);
        
        if ($syncResult['success']) {
            $response['steps'][] = "✅ Order sync successful!";
            $response['sync_result'] = $syncResult;
        } else {
            $response['steps'][] = "❌ Order sync failed: " . ($syncResult['error'] ?? 'Unknown error');
            $response['sync_result'] = $syncResult;
        }
        
    } catch (Exception $e) {
        $response['steps'][] = "❌ Order sync threw exception: " . $e->getMessage();
        $response['sync_error'] = $e->getMessage();
        $response['sync_result'] = [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
    
    // Step 4: Analyze the error if sync failed
    if (!($response['sync_result']['success'] ?? false)) {
        $response['steps'][] = 'Step 4: Analyzing sync failure...';
        
        $errorMessage = $response['sync_result']['error'] ?? $response['sync_error'] ?? 'Unknown error';
        
        if (strpos($errorMessage, 'Invalid Field Value 1') !== false) {
            $response['steps'][] = "🎯 IDENTIFIED ISSUE: Invalid item ID '1' in NetSuite";
            $response['issue_identified'] = 'invalid_default_item_id';
            $response['recommended_action'] = 'Update default_item_id in config to a valid NetSuite item ID';
            
            // Check if it's specifically item.items[2]
            if (strpos($errorMessage, 'item.items[2]') !== false) {
                $response['steps'][] = "📍 Error is at item index [2] (third item in the order)";
                $response['steps'][] = "🔍 From logs: Item 'ALAREVO18 3\" Riser Blocks' is using default_item_id = 1";
            }
            
        } elseif (strpos($errorMessage, 'Invalid Field Value') !== false) {
            $response['steps'][] = "🎯 IDENTIFIED ISSUE: Invalid item ID in NetSuite";
            $response['issue_identified'] = 'invalid_item_id';
            $response['recommended_action'] = 'Check which item ID is invalid and update configuration';
            
        } else {
            $response['steps'][] = "❓ Unknown error type - check logs for details";
            $response['issue_identified'] = 'unknown';
            $response['recommended_action'] = 'Check application logs for detailed error information';
        }
    }
    
    // Step 5: Provide recommendations
    $response['steps'][] = 'Step 5: Providing recommendations...';
    
    if ($response['issue_identified'] ?? false) {
        $response['steps'][] = '💡 RECOMMENDATIONS:';
        
        if ($response['issue_identified'] === 'invalid_default_item_id') {
            $response['steps'][] = '1. Run "🔧 Test NetSuite Items" to find a valid default item ID';
            $response['steps'][] = '2. Update config/config.php with the recommended item ID';
            $response['steps'][] = '3. Retry the order sync';
            $response['steps'][] = '4. Consider enabling item creation or manually create missing items';
        }
        
        $response['recommendations'] = [
            'immediate_fix' => 'Update default_item_id in config/config.php',
            'test_to_run' => 'NetSuite Items Test',
            'config_file' => 'config/config.php',
            'config_setting' => 'netsuite.default_item_id'
        ];
    }
    
    // Summary
    $response['steps'][] = '📋 ORDER SYNC TEST SUMMARY:';
    $response['steps'][] = "• Order ID: {$orderId}";
    $response['steps'][] = "• Sync Status: " . (($response['sync_result']['success'] ?? false) ? 'SUCCESS' : 'FAILED');
    $response['steps'][] = "• Issue Identified: " . ($response['issue_identified'] ?? 'None');
    $response['steps'][] = "• Current default_item_id: {$defaultItemId}";
    
    // Overall status
    if ($response['sync_result']['success'] ?? false) {
        $response['overall_status'] = 'SUCCESS - Order synced successfully';
        $response['steps'][] = '🎉 SUCCESS: Order sync completed successfully!';
    } elseif ($response['issue_identified'] === 'invalid_default_item_id') {
        $response['overall_status'] = 'FAILED - Invalid default item ID (fixable)';
        $response['steps'][] = '🔧 FIXABLE: Update default_item_id configuration and retry';
    } else {
        $response['overall_status'] = 'FAILED - Unknown issue';
        $response['steps'][] = '❌ FAILED: Check logs and configuration for issues';
    }
    
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