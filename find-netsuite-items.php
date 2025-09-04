<?php
/**
 * Find Existing NetSuite Items
 * 
 * This script searches for existing items in NetSuite that can be used for tax, shipping, and discounts
 */

require_once __DIR__ . '/vendor/autoload.php';

use Laguna\Integration\Services\NetSuiteService;

echo "🔍 Finding Existing NetSuite Items\n";
echo "==================================\n\n";

try {
    $netSuiteService = new NetSuiteService();
    
    echo "🔗 Testing NetSuite connection...\n";
    $connectionTest = $netSuiteService->testConnection();
    if (!$connectionTest['success']) {
        echo "❌ NetSuite connection failed: " . $connectionTest['message'] . "\n";
        exit(1);
    }
    echo "✅ NetSuite connection successful\n\n";
    
    echo "🔍 Searching for existing items...\n";
    echo str_repeat('-', 50) . "\n";
    
    // Search for items using SuiteQL
    $queries = [
        'Service Items' => "SELECT id, itemid, displayname, itemtype, isinactive, issaleitem FROM item WHERE itemtype = 'Service' AND isinactive = 'F' AND issaleitem = 'T' ORDER BY id LIMIT 20",
        'Other Charge Items' => "SELECT id, itemid, displayname, itemtype, isinactive, issaleitem FROM item WHERE itemtype = 'OthCharge' AND isinactive = 'F' AND issaleitem = 'T' ORDER BY id LIMIT 20",
        'All Usable Items' => "SELECT id, itemid, displayname, itemtype, isinactive, issaleitem FROM item WHERE isinactive = 'F' AND issaleitem = 'T' ORDER BY id LIMIT 50"
    ];
    
    $allItems = [];
    
    foreach ($queries as $queryName => $query) {
        echo "\n📋 $queryName:\n";
        
        try {
            $result = $netSuiteService->executeSuiteQLQuery($query);
            
            if (!empty($result['items'])) {
                echo "   Found " . count($result['items']) . " items:\n";
                
                foreach ($result['items'] as $item) {
                    $id = $item['id'];
                    $itemId = $item['itemid'];
                    $displayName = $item['displayname'];
                    $itemType = $item['itemtype'];
                    
                    echo "   • ID: $id | Name: $itemId | Display: $displayName | Type: $itemType\n";
                    
                    // Store for analysis
                    $allItems[$id] = [
                        'id' => $id,
                        'itemid' => $itemId,
                        'displayname' => $displayName,
                        'itemtype' => $itemType,
                        'isinactive' => $item['isinactive'],
                        'issaleitem' => $item['issaleitem']
                    ];
                }
            } else {
                echo "   No items found\n";
            }
            
        } catch (Exception $e) {
            echo "   ❌ Error: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n🎯 Item Recommendations:\n";
    echo str_repeat('-', 50) . "\n";
    
    if (empty($allItems)) {
        echo "❌ No usable items found in NetSuite!\n";
        echo "You need to create items for tax, shipping, and discounts.\n\n";
        
        echo "📝 How to create items in NetSuite:\n";
        echo "1. Go to Lists > Accounting > Items > New\n";
        echo "2. Choose 'Service' or 'Other Charge'\n";
        echo "3. Fill in the required fields:\n";
        echo "   - Item Name/Number: e.g., 'DISCOUNT', 'SHIPPING', 'TAX'\n";
        echo "   - Display Name: e.g., 'Discount', 'Shipping', 'Sales Tax'\n";
        echo "   - Sale Item: ✅ Check this box\n";
        echo "   - Income Account: Choose appropriate account\n";
        echo "   - Tax Code: Usually 'Non-Taxable'\n";
        echo "4. Save and note the Internal ID\n";
        echo "5. Update config.php with the correct IDs\n";
        
    } else {
        echo "✅ Found " . count($allItems) . " usable items in NetSuite\n\n";
        
        // Suggest items based on names
        $suggestions = [
            'discount' => [],
            'shipping' => [],
            'tax' => [],
            'freight' => [],
            'other' => []
        ];
        
        foreach ($allItems as $item) {
            $name = strtolower($item['itemid'] . ' ' . $item['displayname']);
            
            if (strpos($name, 'discount') !== false || strpos($name, 'promo') !== false) {
                $suggestions['discount'][] = $item;
            } elseif (strpos($name, 'shipping') !== false || strpos($name, 'freight') !== false) {
                $suggestions['shipping'][] = $item;
            } elseif (strpos($name, 'tax') !== false) {
                $suggestions['tax'][] = $item;
            } else {
                $suggestions['other'][] = $item;
            }
        }
        
        echo "💡 Suggested Items for Configuration:\n\n";
        
        // Discount items
        if (!empty($suggestions['discount'])) {
            echo "🏷️  For Discount (discount_item_id):\n";
            foreach ($suggestions['discount'] as $item) {
                echo "   • ID: {$item['id']} - {$item['itemid']} ({$item['displayname']})\n";
            }
        } else {
            echo "🏷️  For Discount: No discount-related items found\n";
            echo "   Suggested: Use any Service/Other Charge item or create new\n";
            if (!empty($suggestions['other'])) {
                echo "   Could use: ID " . $suggestions['other'][0]['id'] . " - " . $suggestions['other'][0]['itemid'] . "\n";
            }
        }
        
        echo "\n";
        
        // Shipping items
        if (!empty($suggestions['shipping'])) {
            echo "🚚 For Shipping (shipping_item_id):\n";
            foreach ($suggestions['shipping'] as $item) {
                echo "   • ID: {$item['id']} - {$item['itemid']} ({$item['displayname']})\n";
            }
        } else {
            echo "🚚 For Shipping: No shipping-related items found\n";
            echo "   Suggested: Use any Service/Other Charge item or create new\n";
            if (!empty($suggestions['other'])) {
                $shippingItem = count($suggestions['other']) > 1 ? $suggestions['other'][1] : $suggestions['other'][0];
                echo "   Could use: ID " . $shippingItem['id'] . " - " . $shippingItem['itemid'] . "\n";
            }
        }
        
        echo "\n";
        
        // Tax items
        if (!empty($suggestions['tax'])) {
            echo "💰 For Tax (tax_item_id):\n";
            foreach ($suggestions['tax'] as $item) {
                echo "   • ID: {$item['id']} - {$item['itemid']} ({$item['displayname']})\n";
            }
        } else {
            echo "💰 For Tax: No tax-related items found\n";
            echo "   Suggested: Use any Service/Other Charge item or create new\n";
            if (!empty($suggestions['other'])) {
                $taxItem = count($suggestions['other']) > 2 ? $suggestions['other'][2] : $suggestions['other'][0];
                echo "   Could use: ID " . $taxItem['id'] . " - " . $taxItem['itemid'] . "\n";
            }
        }
        
        echo "\n📝 Suggested config.php updates:\n";
        echo str_repeat('-', 30) . "\n";
        
        // Generate config suggestions
        $discountId = !empty($suggestions['discount']) ? $suggestions['discount'][0]['id'] : 
                     (!empty($suggestions['other']) ? $suggestions['other'][0]['id'] : 'CREATE_NEW');
        
        $shippingId = !empty($suggestions['shipping']) ? $suggestions['shipping'][0]['id'] : 
                     (!empty($suggestions['other']) && count($suggestions['other']) > 1 ? $suggestions['other'][1]['id'] : 
                     (!empty($suggestions['other']) ? $suggestions['other'][0]['id'] : 'CREATE_NEW'));
        
        $taxId = !empty($suggestions['tax']) ? $suggestions['tax'][0]['id'] : 
                (!empty($suggestions['other']) && count($suggestions['other']) > 2 ? $suggestions['other'][2]['id'] : 
                (!empty($suggestions['other']) ? $suggestions['other'][0]['id'] : 'CREATE_NEW'));
        
        echo "'netsuite' => [\n";
        echo "    // ... other settings ...\n";
        echo "    'tax_item_id' => $taxId,\n";
        echo "    'shipping_item_id' => $shippingId,\n";
        echo "    'discount_item_id' => $discountId,\n";
        echo "    // ... other settings ...\n";
        echo "],\n";
    }
    
    echo "\n⚠️  Important Notes:\n";
    echo "• All items must have 'Sale Item' = Yes\n";
    echo "• All items must be Active (not inactive)\n";
    echo "• Items should have appropriate GL accounts\n";
    echo "• Test with a small order after updating config\n";
    
    echo "\n🔧 Quick Fix for Current Error:\n";
    echo "1. Update config.php with valid item IDs from above\n";
    echo "2. Or temporarily disable discount line items:\n";
    echo "   'include_discount_as_line_item' => false,\n";
    echo "3. Test order creation again\n";
    
} catch (Exception $e) {
    echo "❌ Error during search: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n🎯 Item search completed!\n";
?>