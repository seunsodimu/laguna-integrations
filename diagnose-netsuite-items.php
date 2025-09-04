<?php
/**
 * NetSuite Items Diagnostic Tool
 * 
 * This script checks if the configured NetSuite item IDs are valid
 */

require_once __DIR__ . '/vendor/autoload.php';

use Laguna\Integration\Services\NetSuiteService;

echo "🔍 NetSuite Items Diagnostic Tool\n";
echo "=================================\n\n";

$config = require __DIR__ . '/config/config.php';

echo "📋 Configured Item IDs:\n";
echo "   Tax Item ID: " . ($config['netsuite']['tax_item_id'] ?? 'NOT SET') . "\n";
echo "   Shipping Item ID: " . ($config['netsuite']['shipping_item_id'] ?? 'NOT SET') . "\n";
echo "   Discount Item ID: " . ($config['netsuite']['discount_item_id'] ?? 'NOT SET') . "\n";
echo "   Default Item ID: " . ($config['netsuite']['default_item_id'] ?? 'NOT SET') . "\n";

try {
    $netSuiteService = new NetSuiteService();
    
    echo "\n🔗 Testing NetSuite connection...\n";
    $connectionTest = $netSuiteService->testConnection();
    if (!$connectionTest['success']) {
        echo "❌ NetSuite connection failed: " . $connectionTest['message'] . "\n";
        exit(1);
    }
    echo "✅ NetSuite connection successful\n";
    
    // Test each configured item ID
    $itemIds = [
        'Tax Item' => $config['netsuite']['tax_item_id'] ?? null,
        'Shipping Item' => $config['netsuite']['shipping_item_id'] ?? null,
        'Discount Item' => $config['netsuite']['discount_item_id'] ?? null,
        'Default Item' => $config['netsuite']['default_item_id'] ?? null,
    ];
    
    echo "\n🧪 Testing Item IDs in NetSuite...\n";
    echo str_repeat('-', 50) . "\n";
    
    foreach ($itemIds as $itemName => $itemId) {
        if ($itemId === null) {
            echo "⚠️  $itemName: NOT CONFIGURED\n";
            continue;
        }
        
        echo "🔍 Testing $itemName (ID: $itemId)...\n";
        
        try {
            // Use the new validateItem method
            $itemValidation = $netSuiteService->validateItem($itemId);
            
            if ($itemValidation['exists']) {
                echo "   ✅ Item exists\n";
                echo "   - Name: " . $itemValidation['itemid'] . "\n";
                echo "   - Display Name: " . $itemValidation['displayname'] . "\n";
                echo "   - Type: " . $itemValidation['itemtype'] . "\n";
                echo "   - Is Inactive: " . ($itemValidation['isinactive'] ? 'Yes' : 'No') . "\n";
                echo "   - Can be Used on Sales Orders: " . ($itemValidation['issaleitem'] ? 'Yes' : 'No') . "\n";
                echo "   - Usable for Line Items: " . ($itemValidation['usable'] ? '✅ Yes' : '❌ No') . "\n";
                
                // Check if item is suitable for line items
                if ($itemValidation['isinactive']) {
                    echo "   ⚠️  WARNING: Item is inactive!\n";
                }
                
                if (!$itemValidation['issaleitem']) {
                    echo "   ⚠️  WARNING: Item cannot be used on sales orders!\n";
                }
                
                if (!$itemValidation['usable']) {
                    echo "   ❌ CRITICAL: This item cannot be used in sales orders!\n";
                }
                
            } else {
                echo "   ❌ Item not found\n";
                echo "   - Error: " . $itemValidation['error'] . "\n";
                echo "   💡 Item ID $itemId does not exist in NetSuite\n";
            }
            
        } catch (Exception $e) {
            echo "   ❌ Error checking item: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }
    
    echo "🔧 Recommendations:\n";
    echo str_repeat('-', 50) . "\n";
    
    // Check for common issues
    $issues = [];
    $recommendations = [];
    
    // Check if discount item exists and is valid
    if (isset($config['netsuite']['discount_item_id'])) {
        $recommendations[] = "For Discount Item (ID: " . $config['netsuite']['discount_item_id'] . "):";
        $recommendations[] = "  • Should be a 'Service' or 'Other Charge' item type";
        $recommendations[] = "  • Must be marked as 'Sale Item' = Yes";
        $recommendations[] = "  • Must be Active (not inactive)";
        $recommendations[] = "  • Should have an appropriate GL account for discounts";
    }
    
    $recommendations[] = "\nTo create missing items in NetSuite:";
    $recommendations[] = "1. Go to Lists > Accounting > Items > New";
    $recommendations[] = "2. Choose 'Service' or 'Other Charge' for discount/shipping/tax items";
    $recommendations[] = "3. Set 'Sale Item' = Yes";
    $recommendations[] = "4. Choose appropriate income/expense accounts";
    $recommendations[] = "5. Make sure the item is Active";
    $recommendations[] = "6. Note the Internal ID and update config.php";
    
    foreach ($recommendations as $rec) {
        echo "$rec\n";
    }
    
    echo "\n📝 Sample NetSuite Item Configuration:\n";
    echo str_repeat('-', 50) . "\n";
    echo "Discount Item:\n";
    echo "  • Type: Service or Other Charge\n";
    echo "  • Name: 'Discount' or 'Promotional Discount'\n";
    echo "  • Sale Item: Yes\n";
    echo "  • Income Account: Discount/Promotional Expense Account\n";
    echo "  • Tax Code: Non-Taxable\n";
    echo "  • Active: Yes\n";
    
    echo "\nShipping Item:\n";
    echo "  • Type: Service or Other Charge\n";
    echo "  • Name: 'Shipping' or 'Freight'\n";
    echo "  • Sale Item: Yes\n";
    echo "  • Income Account: Shipping Revenue Account\n";
    echo "  • Tax Code: Usually Non-Taxable\n";
    echo "  • Active: Yes\n";
    
    echo "\nTax Item:\n";
    echo "  • Type: Service or Other Charge\n";
    echo "  • Name: 'Sales Tax' or 'Tax'\n";
    echo "  • Sale Item: Yes\n";
    echo "  • Income Account: Sales Tax Payable Account\n";
    echo "  • Tax Code: Non-Taxable (tax items themselves aren't taxed)\n";
    echo "  • Active: Yes\n";
    
} catch (Exception $e) {
    echo "❌ Error during diagnostic: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n🎯 Diagnostic completed!\n";
echo "\nNext steps:\n";
echo "1. Review the item test results above\n";
echo "2. Create or fix any missing/invalid items in NetSuite\n";
echo "3. Update config.php with correct item IDs\n";
echo "4. Test order creation again\n";
?>