<?php
/**
 * Test NetSuite Items
 * 
 * This script tests NetSuite item lookup and finds valid item IDs
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
    
    $response = [
        'success' => true,
        'steps' => []
    ];
    
    $response['steps'][] = 'Testing NetSuite item lookup and finding valid default item ID...';
    
    // Step 1: Initialize NetSuite service
    $response['steps'][] = 'Step 1: Initializing NetSuite service...';
    
    try {
        $netSuiteService = new NetSuiteService();
        $response['steps'][] = "✅ NetSuite service initialized successfully";
    } catch (Exception $e) {
        $response['steps'][] = "❌ Failed to initialize NetSuite service: " . $e->getMessage();
        throw $e;
    }
    
    // Step 2: Test current default item ID
    $response['steps'][] = 'Step 2: Testing current default item ID...';
    
    $config = require __DIR__ . '/../config/config.php';
    $currentDefaultId = $config['netsuite']['default_item_id'];
    
    $response['current_default_id'] = $currentDefaultId;
    $response['steps'][] = "Current default item ID: {$currentDefaultId}";
    
    // Try to fetch the current default item
    try {
        $reflection = new ReflectionClass($netSuiteService);
        $makeRequestMethod = $reflection->getMethod('makeRequest');
        $makeRequestMethod->setAccessible(true);
        
        // Test if current default item exists
        $itemResponse = $makeRequestMethod->invoke($netSuiteService, 'GET', "/item/{$currentDefaultId}");
        $itemData = json_decode($itemResponse->getBody()->getContents(), true);
        
        if (isset($itemData['id'])) {
            $response['steps'][] = "✅ Current default item ID {$currentDefaultId} exists in NetSuite";
            $response['current_item_valid'] = true;
            $response['current_item_data'] = [
                'id' => $itemData['id'],
                'itemId' => $itemData['itemId'] ?? 'N/A',
                'displayName' => $itemData['displayName'] ?? 'N/A',
                'type' => $itemData['type'] ?? 'N/A'
            ];
        } else {
            $response['steps'][] = "❌ Current default item ID {$currentDefaultId} does not exist in NetSuite";
            $response['current_item_valid'] = false;
        }
        
    } catch (Exception $e) {
        $response['steps'][] = "❌ Current default item ID {$currentDefaultId} is invalid: " . $e->getMessage();
        $response['current_item_valid'] = false;
        $response['current_item_error'] = $e->getMessage();
    }
    
    // Step 3: Find valid items in NetSuite
    $response['steps'][] = 'Step 3: Searching for valid items in NetSuite...';
    
    $itemTypes = ['inventoryItem', 'noninventoryItem', 'serviceItem', 'item'];
    $validItems = [];
    
    foreach ($itemTypes as $itemType) {
        try {
            $response['steps'][] = "Searching {$itemType} items...";
            
            $itemResponse = $makeRequestMethod->invoke($netSuiteService, 'GET', "/{$itemType}", null, [
                'limit' => 10,
                'offset' => 0
            ]);
            
            $itemsData = json_decode($itemResponse->getBody()->getContents(), true);
            
            if (isset($itemsData['items']) && count($itemsData['items']) > 0) {
                $response['steps'][] = "✅ Found " . count($itemsData['items']) . " {$itemType} items";
                
                foreach ($itemsData['items'] as $item) {
                    $validItems[] = [
                        'id' => $item['id'],
                        'itemId' => $item['itemId'] ?? 'N/A',
                        'displayName' => $item['displayName'] ?? 'N/A',
                        'type' => $itemType,
                        'isInactive' => $item['isInactive'] ?? false
                    ];
                }
            } else {
                $response['steps'][] = "⚠️ No {$itemType} items found";
            }
            
        } catch (Exception $e) {
            $response['steps'][] = "❌ Error searching {$itemType}: " . $e->getMessage();
        }
    }
    
    $response['valid_items'] = $validItems;
    $response['valid_items_count'] = count($validItems);
    
    // Step 4: Find a good default item
    $response['steps'][] = 'Step 4: Finding a suitable default item...';
    
    $recommendedItem = null;
    
    // Look for active items first
    foreach ($validItems as $item) {
        if (!$item['isInactive']) {
            $recommendedItem = $item;
            break;
        }
    }
    
    // If no active items, use the first available
    if (!$recommendedItem && count($validItems) > 0) {
        $recommendedItem = $validItems[0];
    }
    
    if ($recommendedItem) {
        $response['recommended_item'] = $recommendedItem;
        $response['steps'][] = "✅ Recommended default item: ID {$recommendedItem['id']} ({$recommendedItem['itemId']}) - {$recommendedItem['displayName']}";
        
        // Check if it's different from current
        if ($recommendedItem['id'] != $currentDefaultId) {
            $response['needs_config_update'] = true;
            $response['steps'][] = "⚠️ Current default item ID needs to be updated from {$currentDefaultId} to {$recommendedItem['id']}";
        } else {
            $response['needs_config_update'] = false;
            $response['steps'][] = "✅ Current default item ID is already correct";
        }
    } else {
        $response['steps'][] = "❌ No valid items found in NetSuite - this is a serious issue";
        $response['recommended_item'] = null;
        $response['needs_config_update'] = true;
    }
    
    // Step 5: Test item creation (if enabled)
    $response['steps'][] = 'Step 5: Testing item creation capability...';
    
    if ($config['netsuite']['create_missing_items']) {
        $response['steps'][] = "✅ Item creation is enabled in config";
        
        // Test creating a sample item (but don't actually create it)
        $sampleItem = [
            'itemId' => 'TEST-ITEM-' . time(),
            'displayName' => 'Test Item for Default',
            'description' => 'Test item created for default item ID testing',
            'basePrice' => 1.00,
            'includeChildren' => false,
            'isInactive' => false,
            'subsidiary' => [['id' => $config['netsuite']['default_subsidiary_id']]],
        ];
        
        $response['sample_item_payload'] = $sampleItem;
        $response['steps'][] = "Sample item payload prepared (not created)";
        
    } else {
        $response['steps'][] = "⚠️ Item creation is disabled in config";
    }
    
    // Summary
    $response['steps'][] = '📋 NETSUITE ITEMS TEST SUMMARY:';
    $response['steps'][] = "• Current Default Item ID: {$currentDefaultId}";
    $response['steps'][] = "• Current Item Valid: " . ($response['current_item_valid'] ? 'Yes' : 'No');
    $response['steps'][] = "• Valid Items Found: " . count($validItems);
    $response['steps'][] = "• Recommended Item ID: " . ($recommendedItem ? $recommendedItem['id'] : 'None');
    $response['steps'][] = "• Config Update Needed: " . ($response['needs_config_update'] ? 'Yes' : 'No');
    
    // Overall status
    if ($response['current_item_valid']) {
        $response['overall_status'] = 'SUCCESS - Current default item ID is valid';
        $response['steps'][] = '🎉 SUCCESS: Current default item ID is working correctly!';
    } elseif ($recommendedItem) {
        $response['overall_status'] = 'NEEDS_UPDATE - Valid item found, config needs update';
        $response['steps'][] = '⚠️ NEEDS UPDATE: Valid item found but config needs to be updated';
    } else {
        $response['overall_status'] = 'CRITICAL - No valid items found';
        $response['steps'][] = '❌ CRITICAL: No valid items found in NetSuite - check NetSuite setup';
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