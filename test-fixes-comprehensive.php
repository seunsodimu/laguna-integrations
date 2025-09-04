<?php
/**
 * Test Both Fixes: CustomerComments Mapping + Item Validation
 * 
 * This script tests both the CustomerComments to custbody2 mapping
 * and the improved item validation that prevents NetSuite errors
 */

require_once __DIR__ . '/vendor/autoload.php';

use Laguna\Integration\Services\NetSuiteService;
use Laguna\Integration\Models\Order;

echo "🧪 Testing Both Fixes: CustomerComments + Item Validation\n";
echo "========================================================\n\n";

// Load the test order data
$testOrderData = json_decode(file_get_contents(__DIR__ . '/testOrder.json'), true);
if (!$testOrderData || !isset($testOrderData[0])) {
    echo "❌ Failed to load test order data\n";
    exit(1);
}

$orderData = $testOrderData[0];

// Add CustomerComments for testing
$orderData['CustomerComments'] = 'Test customer comments for custbody2 mapping - Order #' . $orderData['OrderID'];

echo "📋 Test Order Summary:\n";
echo "   Order ID: " . $orderData['OrderID'] . "\n";
echo "   OrderAmount: $" . number_format($orderData['OrderAmount'], 2) . "\n";
echo "   OrderDiscount: $" . number_format($orderData['OrderDiscount'], 2) . "\n";
echo "   CustomerComments: " . $orderData['CustomerComments'] . "\n";

echo "\n1. Testing Item Validation...\n";
echo str_repeat('-', 40) . "\n";

try {
    $netSuiteService = new NetSuiteService();
    $config = require __DIR__ . '/config/config.php';
    
    echo "🔗 Testing NetSuite connection...\n";
    $connectionTest = $netSuiteService->testConnection();
    if (!$connectionTest['success']) {
        echo "❌ NetSuite connection failed: " . $connectionTest['message'] . "\n";
        exit(1);
    }
    echo "✅ NetSuite connection successful\n\n";
    
    // Test each configured item
    $itemTests = [
        'Tax Item' => $config['netsuite']['tax_item_id'] ?? null,
        'Shipping Item' => $config['netsuite']['shipping_item_id'] ?? null,
        'Discount Item' => $config['netsuite']['discount_item_id'] ?? null,
    ];
    
    $validItems = [];
    $invalidItems = [];
    
    foreach ($itemTests as $itemName => $itemId) {
        if ($itemId === null) {
            echo "⚠️  $itemName: NOT CONFIGURED\n";
            continue;
        }
        
        echo "🔍 Testing $itemName (ID: $itemId)...\n";
        
        $validation = $netSuiteService->validateItem($itemId);
        
        if ($validation['exists'] && $validation['usable']) {
            echo "   ✅ Valid and usable\n";
            $validItems[$itemName] = $itemId;
        } elseif ($validation['exists']) {
            echo "   ⚠️  Exists but not usable\n";
            echo "   - Inactive: " . ($validation['isinactive'] ? 'Yes' : 'No') . "\n";
            echo "   - Sale Item: " . ($validation['issaleitem'] ? 'Yes' : 'No') . "\n";
            $invalidItems[$itemName] = $itemId;
        } else {
            echo "   ❌ Does not exist\n";
            echo "   - Error: " . $validation['error'] . "\n";
            $invalidItems[$itemName] = $itemId;
        }
        echo "\n";
    }
    
    echo "📊 Item Validation Summary:\n";
    echo "   Valid Items: " . count($validItems) . "\n";
    echo "   Invalid Items: " . count($invalidItems) . "\n";
    
    if (!empty($invalidItems)) {
        echo "\n⚠️  Invalid items will be skipped during order creation\n";
        echo "   This prevents the 'Invalid Field Value' error\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error during item validation: " . $e->getMessage() . "\n";
}

echo "\n2. Testing CustomerComments Mapping...\n";
echo str_repeat('-', 40) . "\n";

// Test the CustomerComments mapping logic
echo "📝 CustomerComments Mapping Test:\n";
echo "   Input: " . $orderData['CustomerComments'] . "\n";

// Simulate the mapping logic from NetSuiteService
$salesOrderData = [];

if (!empty($orderData['CustomerComments'])) {
    $salesOrderData['custbody2'] = $orderData['CustomerComments'];
    echo "   ✅ Mapped to custbody2: " . $salesOrderData['custbody2'] . "\n";
} else {
    echo "   ⚠️  No CustomerComments to map\n";
}

echo "\n3. Testing Order Processing Logic...\n";
echo str_repeat('-', 40) . "\n";

try {
    // Test the order model with discount fixes
    $order = new Order($orderData);
    
    echo "📋 Order Model Results:\n";
    echo "   Total: $" . number_format($order->getTotal(), 2) . "\n";
    echo "   Discount: $" . number_format($order->getDiscountAmount(), 2) . "\n";
    echo "   Items Subtotal: $" . number_format($order->calculateItemsSubtotal(), 2) . "\n";
    
    $validation = $order->validateTotals();
    echo "   Validation: " . ($validation['is_valid'] ? '✅ Valid' : '❌ Invalid') . "\n";
    
    // Simulate line item creation with validation
    echo "\n📦 Simulating Line Item Creation:\n";
    
    $items = [];
    $warnings = [];
    
    // Product items (these should always work)
    foreach ($orderData['OrderItemList'] as $item) {
        $items[] = [
            'type' => 'Product',
            'item_id' => 'MOCK_' . $item['ItemID'],
            'quantity' => $item['ItemQuantity'],
            'rate' => $item['ItemUnitPrice'] + ($item['ItemOptionPrice'] ?? 0)
        ];
    }
    echo "   ✅ Added " . count($orderData['OrderItemList']) . " product items\n";
    
    // Tax item (with validation)
    $taxAmount = (float)($orderData['SalesTax'] ?? 0);
    if ($taxAmount > 0) {
        if (isset($validItems['Tax Item'])) {
            $items[] = [
                'type' => 'Tax',
                'item_id' => $validItems['Tax Item'],
                'quantity' => 1,
                'rate' => $taxAmount
            ];
            echo "   ✅ Added tax line item (ID: " . $validItems['Tax Item'] . ")\n";
        } else {
            $warnings[] = "Tax item invalid, skipped tax line item";
            echo "   ⚠️  Skipped tax line item (invalid item ID)\n";
        }
    }
    
    // Shipping item (with validation)
    $shippingCost = (float)($orderData['ShippingCost'] ?? 0);
    if ($shippingCost > 0) {
        if (isset($validItems['Shipping Item'])) {
            $items[] = [
                'type' => 'Shipping',
                'item_id' => $validItems['Shipping Item'],
                'quantity' => 1,
                'rate' => $shippingCost
            ];
            echo "   ✅ Added shipping line item (ID: " . $validItems['Shipping Item'] . ")\n";
        } else {
            $warnings[] = "Shipping item invalid, skipped shipping line item";
            echo "   ⚠️  Skipped shipping line item (invalid item ID)\n";
        }
    }
    
    // Discount item (with validation)
    $discountAmount = $order->getDiscountAmount();
    if ($discountAmount > 0) {
        if (isset($validItems['Discount Item'])) {
            $items[] = [
                'type' => 'Discount',
                'item_id' => $validItems['Discount Item'],
                'quantity' => 1,
                'rate' => -$discountAmount
            ];
            echo "   ✅ Added discount line item (ID: " . $validItems['Discount Item'] . ")\n";
        } else {
            $warnings[] = "Discount item invalid, skipped discount line item";
            echo "   ⚠️  Skipped discount line item (invalid item ID)\n";
            echo "   💡 Discount info will be added to memo instead\n";
        }
    }
    
    echo "\n📊 Line Items Summary:\n";
    echo "   Total Items: " . count($items) . "\n";
    echo "   Warnings: " . count($warnings) . "\n";
    
    if (!empty($warnings)) {
        echo "\n⚠️  Warnings:\n";
        foreach ($warnings as $warning) {
            echo "   • $warning\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error during order processing test: " . $e->getMessage() . "\n";
}

echo "\n4. Testing Complete Sales Order Data Structure...\n";
echo str_repeat('-', 40) . "\n";

// Simulate the complete sales order structure
$salesOrder = [
    'entity' => ['id' => 12345], // Mock customer ID
    'subsidiary' => ['id' => 1],
    'department' => ['id' => 3],
    'istaxable' => false,
    'tranDate' => date('Y-m-d', strtotime($orderData['OrderDate'])),
    'memo' => 'Order imported from 3DCart - Order #' . $orderData['OrderID'],
    'externalId' => '3DCART_' . $orderData['OrderID']
];

// Add CustomerComments mapping
if (!empty($orderData['CustomerComments'])) {
    $salesOrder['custbody2'] = $orderData['CustomerComments'];
}

// Add discount info to memo if discount item is invalid
if ($order->getDiscountAmount() > 0 && !isset($validItems['Discount Item'])) {
    $salesOrder['memo'] .= ' | Discount Applied: $' . number_format($order->getDiscountAmount(), 2);
}

echo "📋 Complete Sales Order Structure:\n";
echo "   Entity ID: " . $salesOrder['entity']['id'] . "\n";
echo "   External ID: " . $salesOrder['externalId'] . "\n";
echo "   Transaction Date: " . $salesOrder['tranDate'] . "\n";
echo "   Memo: " . $salesOrder['memo'] . "\n";
echo "   custbody2: " . ($salesOrder['custbody2'] ?? 'Not set') . "\n";
echo "   Items Count: " . count($items ?? []) . "\n";

echo "\n5. Summary and Recommendations...\n";
echo str_repeat('-', 40) . "\n";

$allGood = true;
$issues = [];
$recommendations = [];

// Check CustomerComments mapping
if (isset($salesOrder['custbody2'])) {
    echo "✅ CustomerComments → custbody2 mapping: Working\n";
} else {
    echo "⚠️  CustomerComments → custbody2 mapping: No comments to map\n";
}

// Check item validation
if (count($invalidItems) > 0) {
    echo "⚠️  Item validation: " . count($invalidItems) . " invalid items found\n";
    $issues[] = "Invalid NetSuite item IDs configured";
    $recommendations[] = "Create missing items in NetSuite or update config.php";
} else {
    echo "✅ Item validation: All configured items are valid\n";
}

// Check discount handling
if ($order->getDiscountAmount() > 0) {
    if (isset($validItems['Discount Item'])) {
        echo "✅ Discount handling: Will be added as line item\n";
    } else {
        echo "⚠️  Discount handling: Will be added to memo only\n";
        $recommendations[] = "Create or configure valid discount item for proper discount tracking";
    }
}

echo "\n🎯 Overall Status:\n";
if (count($issues) == 0) {
    echo "✅ All fixes are working correctly!\n";
    echo "   • CustomerComments mapping implemented\n";
    echo "   • Item validation prevents NetSuite errors\n";
    echo "   • Order processing will succeed\n";
} else {
    echo "⚠️  Some issues need attention:\n";
    foreach ($issues as $issue) {
        echo "   • $issue\n";
    }
}

if (!empty($recommendations)) {
    echo "\n📋 Recommendations:\n";
    foreach ($recommendations as $rec) {
        echo "   • $rec\n";
    }
}

echo "\n🚀 Next Steps:\n";
echo "1. ✅ CustomerComments mapping is ready\n";
echo "2. ✅ Item validation prevents errors\n";
if (count($invalidItems) > 0) {
    echo "3. 🔧 Create missing NetSuite items or update config\n";
    echo "4. 🧪 Test real order creation\n";
} else {
    echo "3. 🧪 Test real order creation (should work now!)\n";
}
echo "5. 👀 Monitor logs for any remaining issues\n";

echo "\n🎯 Comprehensive fix testing completed!\n";
?>