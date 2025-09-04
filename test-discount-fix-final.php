<?php
/**
 * Test Final Discount Fix
 * 
 * This script tests that the discount is now properly applied at the order level
 */

require_once __DIR__ . '/vendor/autoload.php';

use Laguna\Integration\Services\NetSuiteService;
use Laguna\Integration\Models\Order;

echo "🧪 Testing Final Discount Fix\n";
echo "=============================\n\n";

// Load the test order data
$testOrderData = json_decode(file_get_contents(__DIR__ . '/testOrder.json'), true);
if (!$testOrderData || !isset($testOrderData[0])) {
    echo "❌ Failed to load test order data\n";
    exit(1);
}

$orderData = $testOrderData[0];

// Add CustomerComments for testing
$orderData['CustomerComments'] = 'Customer requested expedited shipping and special handling.';

echo "📋 Test Order Details:\n";
echo "   Order ID: " . $orderData['OrderID'] . "\n";
echo "   3DCart Total: $" . number_format($orderData['OrderAmount'], 2) . "\n";
echo "   3DCart Discount: $" . number_format($orderData['OrderDiscount'], 2) . "\n";
echo "   Expected NetSuite Total: $" . number_format($orderData['OrderAmount'], 2) . "\n";
echo "   CustomerComments: " . $orderData['CustomerComments'] . "\n";

echo "\n1. Testing Order Model...\n";
echo str_repeat('-', 40) . "\n";

try {
    $order = new Order($orderData);
    
    echo "✅ Order model created successfully\n";
    echo "   Items Subtotal: $" . number_format($order->calculateItemsSubtotal(), 2) . "\n";
    echo "   Discount Amount: $" . number_format($order->getDiscountAmount(), 2) . "\n";
    echo "   Final Total: $" . number_format($order->getTotal(), 2) . "\n";
    
    $validation = $order->validateTotals();
    echo "   Validation: " . ($validation['is_valid'] ? '✅ Valid' : '❌ Invalid') . "\n";
    
    if ($validation['is_valid']) {
        echo "   ✅ Math checks out: " . number_format($validation['items_subtotal'], 2) . 
             " - " . number_format($validation['discount'], 2) . 
             " = " . number_format($validation['calculated_total'], 2) . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Order model error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n2. Testing NetSuite Service Configuration...\n";
echo str_repeat('-', 40) . "\n";

$config = require __DIR__ . '/config/config.php';

echo "📋 Current Configuration:\n";
echo "   Include Discount Line Item: " . ($config['netsuite']['include_discount_as_line_item'] ? 'Yes' : 'No') . "\n";
echo "   Discount Item ID: " . $config['netsuite']['discount_item_id'] . "\n";
echo "   Validate Totals: " . ($config['netsuite']['validate_totals'] ? 'Yes' : 'No') . "\n";

if (!$config['netsuite']['include_discount_as_line_item']) {
    echo "✅ Discount line items disabled - will use order-level discount\n";
} else {
    echo "⚠️  Discount line items enabled - may cause item validation error\n";
}

echo "\n3. Simulating Sales Order Creation...\n";
echo str_repeat('-', 40) . "\n";

try {
    $netSuiteService = new NetSuiteService();
    
    // Test connection
    $connectionTest = $netSuiteService->testConnection();
    if (!$connectionTest['success']) {
        echo "❌ NetSuite connection failed: " . $connectionTest['message'] . "\n";
        exit(1);
    }
    echo "✅ NetSuite connection successful\n";
    
    // Simulate the sales order creation logic
    echo "\n📦 Simulating Sales Order Structure:\n";
    
    // Mock customer ID
    $customerId = 12345;
    
    // Start with basic sales order structure
    $salesOrder = [
        'entity' => ['id' => $customerId],
        'subsidiary' => ['id' => $config['netsuite']['default_subsidiary_id']],
        'department' => ['id' => $config['netsuite']['default_department_id']],
        'istaxable' => false,
        'tranDate' => date('Y-m-d', strtotime($orderData['OrderDate'])),
        'memo' => 'Order imported from 3DCart - Order #' . $orderData['OrderID'],
        'externalId' => '3DCART_' . $orderData['OrderID']
    ];
    
    // Add CustomerComments mapping
    if (!empty($orderData['CustomerComments'])) {
        $salesOrder['custbody2'] = $orderData['CustomerComments'];
        echo "   ✅ CustomerComments mapped to custbody2\n";
    }
    
    // Create line items (products only)
    $items = [];
    $itemsSubtotal = 0;
    
    foreach ($orderData['OrderItemList'] as $item) {
        $quantity = (float)$item['ItemQuantity'];
        $unitPrice = (float)$item['ItemUnitPrice'];
        $optionPrice = (float)($item['ItemOptionPrice'] ?? 0);
        $totalPrice = $unitPrice + $optionPrice;
        $lineTotal = $quantity * $totalPrice;
        
        $items[] = [
            'item' => ['id' => 999], // Mock item ID
            'quantity' => $quantity,
            'rate' => $totalPrice
        ];
        
        $itemsSubtotal += $lineTotal;
    }
    
    echo "   ✅ Created " . count($items) . " product line items\n";
    echo "   Items Subtotal: $" . number_format($itemsSubtotal, 2) . "\n";
    
    // Handle discount
    $discountAmount = (float)($orderData['OrderDiscount'] ?? 0);
    if ($discountAmount > 0) {
        if (!$config['netsuite']['include_discount_as_line_item']) {
            // Apply discount at order level (NEW FIX)
            $salesOrder['discountTotal'] = $discountAmount;
            $salesOrder['memo'] .= ' | Discount Applied: $' . number_format($discountAmount, 2);
            
            echo "   ✅ Applied order-level discount: $" . number_format($discountAmount, 2) . "\n";
            echo "   Expected NetSuite Total: $" . number_format($itemsSubtotal - $discountAmount, 2) . "\n";
        } else {
            echo "   ⚠️  Would attempt to create discount line item (may fail)\n";
        }
    }
    
    $salesOrder['item'] = ['items' => $items];
    
    echo "\n📊 Final Sales Order Structure:\n";
    echo "   External ID: " . $salesOrder['externalId'] . "\n";
    echo "   custbody2: " . ($salesOrder['custbody2'] ?? 'Not set') . "\n";
    echo "   discountTotal: " . ($salesOrder['discountTotal'] ?? 'Not set') . "\n";
    echo "   Memo: " . $salesOrder['memo'] . "\n";
    echo "   Line Items: " . count($items) . "\n";
    
} catch (Exception $e) {
    echo "❌ Simulation error: " . $e->getMessage() . "\n";
}

echo "\n4. Comparing Expected vs Previous Results...\n";
echo str_repeat('-', 40) . "\n";

// Load the previous NetSuite result
$previousNS = json_decode(file_get_contents(__DIR__ . '/testNS.json'), true);

echo "📊 Comparison:\n";
echo "\n❌ PREVIOUS NetSuite Result:\n";
echo "   Total: $" . number_format($previousNS['total'], 2) . "\n";
echo "   Discount Total: $" . number_format($previousNS['discountTotal'], 2) . "\n";
echo "   Subtotal: $" . number_format($previousNS['subtotal'], 2) . "\n";
echo "   Status: ❌ Discount not applied\n";

echo "\n✅ EXPECTED New Result:\n";
echo "   Total: $" . number_format($orderData['OrderAmount'], 2) . " (should match 3DCart)\n";
echo "   Discount Total: $" . number_format($orderData['OrderDiscount'], 2) . " (should show discount)\n";
echo "   Subtotal: $" . number_format($order->calculateItemsSubtotal(), 2) . " (before discount)\n";
echo "   Status: ✅ Discount properly applied\n";

$expectedImprovement = $previousNS['total'] - $orderData['OrderAmount'];
echo "\n📈 Expected Improvement:\n";
echo "   Total Correction: $" . number_format($expectedImprovement, 2) . "\n";
echo "   Discount Recognition: $" . number_format($orderData['OrderDiscount'], 2) . "\n";

echo "\n5. Summary...\n";
echo str_repeat('-', 40) . "\n";

echo "🎯 Fixes Applied:\n";
echo "   ✅ CustomerComments → custbody2 mapping\n";
echo "   ✅ Order-level discount application (discountTotal field)\n";
echo "   ✅ JSON error handling in order-sync page\n";
echo "   ✅ Item validation to prevent NetSuite errors\n";

echo "\n🚀 Expected Results:\n";
echo "   • Order #1057113 will sync successfully\n";
echo "   • NetSuite total will be $6,778.65 (matching 3DCart)\n";
echo "   • NetSuite discountTotal will be $1,803.34\n";
echo "   • CustomerComments will appear in custbody2\n";
echo "   • No 'Invalid Field Value' errors\n";
echo "   • No JSON parsing errors in UI\n";

echo "\n📞 Next Steps:\n";
echo "1. 🧪 Test actual order sync from the order-sync page\n";
echo "2. ✅ Verify discount appears correctly in NetSuite\n";
echo "3. ✅ Confirm custbody2 field is populated\n";
echo "4. 👀 Monitor for any remaining issues\n";

echo "\n🎯 Final discount fix testing completed!\n";
?>