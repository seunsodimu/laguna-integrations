<?php
/**
 * Check Discount Item Configuration
 * 
 * This script checks if the configured discount item ID is valid in NetSuite
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
    
    $response = [
        'success' => true,
        'steps' => []
    ];
    
    $response['steps'][] = 'Checking configured discount item ID...';
    
    // Get configured item IDs
    $discountItemId = $config['netsuite']['discount_item_id'];
    $taxItemId = $config['netsuite']['tax_item_id'];
    $shippingItemId = $config['netsuite']['shipping_item_id'];
    
    $response['configured_items'] = [
        'discount_item_id' => $discountItemId,
        'tax_item_id' => $taxItemId,
        'shipping_item_id' => $shippingItemId
    ];
    
    $response['steps'][] = "Configured discount item ID: {$discountItemId}";
    
    // Check if discount item exists and is valid
    try {
        $discountItemValidation = $netSuiteService->validateItem($discountItemId);
        $response['discount_item_validation'] = $discountItemValidation;
        
        if ($discountItemValidation['exists']) {
            if ($discountItemValidation['usable']) {
                $response['steps'][] = "✅ Discount item {$discountItemId} exists and is usable";
                $response['discount_item_status'] = 'VALID';
            } else {
                $response['steps'][] = "⚠️ Discount item {$discountItemId} exists but is not usable (inactive or not a sale item)";
                $response['discount_item_status'] = 'EXISTS_BUT_UNUSABLE';
            }
        } else {
            $response['steps'][] = "❌ Discount item {$discountItemId} does not exist in NetSuite";
            $response['discount_item_status'] = 'DOES_NOT_EXIST';
        }
        
    } catch (Exception $e) {
        $response['steps'][] = "❌ Error checking discount item: " . $e->getMessage();
        $response['discount_item_status'] = 'ERROR';
        $response['discount_item_error'] = $e->getMessage();
    }
    
    // Also check tax and shipping items
    $response['steps'][] = 'Checking other configured item IDs...';
    
    try {
        $taxItemValidation = $netSuiteService->validateItem($taxItemId);
        $response['tax_item_validation'] = $taxItemValidation;
        $response['steps'][] = $taxItemValidation['exists'] ? 
            "✅ Tax item {$taxItemId} exists" : 
            "❌ Tax item {$taxItemId} does not exist";
            
    } catch (Exception $e) {
        $response['steps'][] = "❌ Error checking tax item: " . $e->getMessage();
    }
    
    try {
        $shippingItemValidation = $netSuiteService->validateItem($shippingItemId);
        $response['shipping_item_validation'] = $shippingItemValidation;
        $response['steps'][] = $shippingItemValidation['exists'] ? 
            "✅ Shipping item {$shippingItemId} exists" : 
            "❌ Shipping item {$shippingItemId} does not exist";
            
    } catch (Exception $e) {
        $response['steps'][] = "❌ Error checking shipping item: " . $e->getMessage();
    }
    
    // Search for valid discount-type items
    $response['steps'][] = 'Searching for valid discount items in NetSuite...';
    
    try {
        // Search for items that might be used for discounts
        $suiteQLQuery = "SELECT id, itemid, displayname, itemtype, isinactive, issaleitem FROM item WHERE itemtype IN ('Discount', 'OtherCharge', 'Service') AND isinactive = 'F' AND issaleitem = 'T' ORDER BY itemid LIMIT 10";
        
        $searchResult = $netSuiteService->executeSuiteQLQuery($suiteQLQuery);
        
        if (!empty($searchResult['items'])) {
            $response['available_discount_items'] = $searchResult['items'];
            $response['steps'][] = "Found " . count($searchResult['items']) . " potential discount items";
            
            foreach ($searchResult['items'] as $item) {
                $response['steps'][] = "  - ID: {$item['id']}, ItemID: {$item['itemid']}, Name: {$item['displayname']}, Type: {$item['itemtype']}";
            }
        } else {
            $response['steps'][] = "No suitable discount items found";
        }
        
    } catch (Exception $e) {
        $response['steps'][] = "❌ Error searching for discount items: " . $e->getMessage();
    }
    
    // Provide recommendations
    $response['steps'][] = 'Recommendations:';
    
    if ($response['discount_item_status'] === 'DOES_NOT_EXIST') {
        $response['steps'][] = "❌ The configured discount item ID {$discountItemId} is invalid";
        $response['steps'][] = "🔧 You need to either:";
        $response['steps'][] = "   1. Create a discount item in NetSuite with ID {$discountItemId}";
        $response['steps'][] = "   2. Update the config to use a valid discount item ID";
        $response['steps'][] = "   3. Disable discount line items in the config";
        
        if (!empty($response['available_discount_items'])) {
            $firstValidItem = $response['available_discount_items'][0];
            $response['steps'][] = "💡 Suggestion: Use item ID {$firstValidItem['id']} ({$firstValidItem['itemid']}) instead";
            $response['suggested_discount_item_id'] = $firstValidItem['id'];
        }
    } elseif ($response['discount_item_status'] === 'VALID') {
        $response['steps'][] = "✅ Discount item configuration is correct";
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