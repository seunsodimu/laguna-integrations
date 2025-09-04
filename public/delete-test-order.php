<?php
/**
 * Delete Test Order from NetSuite
 * 
 * This script deletes the existing NetSuite order so we can test the corrected logic
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Services\NetSuiteService;
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
    
    // Initialize services
    $netSuiteService = new NetSuiteService();
    
    // Order details
    $orderId = '1140673';
    $netSuiteOrderId = '147662'; // From the test result
    
    $response = [
        'success' => true,
        'order_id' => $orderId,
        'netsuite_order_id' => $netSuiteOrderId,
        'steps' => []
    ];
    
    $response['steps'][] = "Attempting to delete NetSuite order {$netSuiteOrderId} for 3DCart order {$orderId}";
    
    try {
        // First, let's verify the order exists and get its details
        $existingOrder = $netSuiteService->getSalesOrderById($netSuiteOrderId);
        
        if ($existingOrder) {
            $response['steps'][] = "✅ Found existing order in NetSuite";
            $response['existing_order_details'] = [
                'id' => $existingOrder['id'] ?? 'N/A',
                'external_id' => $existingOrder['externalId'] ?? 'N/A',
                'subtotal' => $existingOrder['subtotal'] ?? 'N/A',
                'total' => $existingOrder['total'] ?? 'N/A',
                'status' => $existingOrder['status'] ?? 'N/A'
            ];
            
            // Try to delete the order
            $response['steps'][] = "Attempting to delete the order...";
            
            try {
                $deleteResult = $netSuiteService->deleteSalesOrder($netSuiteOrderId);
                
                if ($deleteResult) {
                    $response['steps'][] = "✅ Successfully deleted NetSuite order {$netSuiteOrderId}";
                    $response['delete_success'] = true;
                    
                    // Verify deletion
                    sleep(1);
                    $verifyOrder = $netSuiteService->getSalesOrderById($netSuiteOrderId);
                    
                    if (!$verifyOrder) {
                        $response['steps'][] = "✅ Verified: Order no longer exists in NetSuite";
                        $response['verification'] = 'Order successfully deleted';
                    } else {
                        $response['steps'][] = "⚠️ Order still exists after deletion attempt";
                        $response['verification'] = 'Order may still exist';
                    }
                    
                } else {
                    $response['steps'][] = "❌ Failed to delete order - delete operation returned false";
                    $response['delete_success'] = false;
                }
                
            } catch (Exception $deleteException) {
                $response['steps'][] = "❌ Exception during deletion: " . $deleteException->getMessage();
                $response['delete_success'] = false;
                $response['delete_error'] = $deleteException->getMessage();
            }
            
        } else {
            $response['steps'][] = "❌ Order not found in NetSuite (may already be deleted)";
            $response['delete_success'] = true; // Consider it successful if already gone
            $response['verification'] = 'Order does not exist';
        }
        
    } catch (Exception $e) {
        $response['steps'][] = "❌ Error checking/deleting order: " . $e->getMessage();
        $response['delete_success'] = false;
        $response['error'] = $e->getMessage();
    }
    
    // Instructions for next steps
    if ($response['delete_success']) {
        $response['steps'][] = "🎯 Next step: Run the 'Test Corrected Pricing' again to create a new order with the fixed logic";
        $response['next_action'] = 'Run corrected pricing test again';
    } else {
        $response['steps'][] = "⚠️ You may need to manually delete the order in NetSuite or check the error above";
        $response['next_action'] = 'Check error and try again';
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