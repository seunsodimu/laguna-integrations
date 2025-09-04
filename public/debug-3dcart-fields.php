<?php
/**
 * Debug 3DCart Order Fields
 * 
 * This script examines all fields in the 3DCart order to understand the discount structure
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Services\ThreeDCartService;
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
    $threeDCartService = new ThreeDCartService();
    
    // Get order 1140673
    $orderId = '1140673';
    
    $response = [
        'success' => true,
        'order_id' => $orderId
    ];
    
    try {
        $threeDCartOrder = $threeDCartService->getOrder($orderId);
        
        // Extract all top-level fields that might be related to totals/discounts
        $financialFields = [];
        $allFields = [];
        
        foreach ($threeDCartOrder as $key => $value) {
            $allFields[$key] = $value;
            
            // Look for fields that might contain financial data
            if (is_numeric($value) || 
                stripos($key, 'amount') !== false || 
                stripos($key, 'total') !== false || 
                stripos($key, 'discount') !== false || 
                stripos($key, 'tax') !== false || 
                stripos($key, 'shipping') !== false || 
                stripos($key, 'cost') !== false || 
                stripos($key, 'price') !== false) {
                $financialFields[$key] = $value;
            }
        }
        
        $response['financial_fields'] = $financialFields;
        $response['all_fields'] = $allFields;
        
        // Calculate item totals
        $itemBreakdown = [];
        $itemTotal = 0;
        
        if (isset($threeDCartOrder['OrderItemList']) && is_array($threeDCartOrder['OrderItemList'])) {
            foreach ($threeDCartOrder['OrderItemList'] as $index => $item) {
                $quantity = (float)($item['ItemQuantity'] ?? 0);
                $unitPrice = (float)($item['ItemUnitPrice'] ?? 0);
                $optionPrice = (float)($item['ItemOptionPrice'] ?? 0);
                $itemSubtotal = $quantity * ($unitPrice + $optionPrice);
                $itemTotal += $itemSubtotal;
                
                $itemBreakdown[] = [
                    'index' => $index,
                    'item_id' => $item['ItemID'] ?? 'N/A',
                    'description' => $item['ItemDescription'] ?? 'N/A',
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'option_price' => $optionPrice,
                    'effective_price' => $unitPrice + $optionPrice,
                    'subtotal' => $itemSubtotal
                ];
            }
        }
        
        $response['item_breakdown'] = $itemBreakdown;
        $response['calculated_item_total'] = $itemTotal;
        
        // Try to figure out the discount
        $orderAmount = (float)($threeDCartOrder['OrderAmount'] ?? 0);
        $possibleDiscount = $itemTotal - $orderAmount;
        
        $response['discount_analysis'] = [
            'calculated_item_total' => $itemTotal,
            'order_amount' => $orderAmount,
            'possible_discount' => $possibleDiscount,
            'discount_percentage' => $itemTotal > 0 ? ($possibleDiscount / $itemTotal) * 100 : 0
        ];
        
        // Look for any fields that might contain the actual final total
        $response['total_analysis'] = [
            'OrderAmount' => $threeDCartOrder['OrderAmount'] ?? 'N/A',
            'OrderTotal' => $threeDCartOrder['OrderTotal'] ?? 'N/A',
            'SalesTax' => $threeDCartOrder['SalesTax'] ?? 'N/A',
            'ShippingCost' => $threeDCartOrder['ShippingCost'] ?? 'N/A',
            'calculated_final_total' => $orderAmount + (float)($threeDCartOrder['SalesTax'] ?? 0) + (float)($threeDCartOrder['ShippingCost'] ?? 0)
        ];
        
    } catch (Exception $e) {
        $response['error'] = $e->getMessage();
        $response['success'] = false;
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