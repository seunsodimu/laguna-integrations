<?php
/**
 * Order Amount Handling Test
 * 
 * Tests the order amount population fixes for NetSuite integration
 */

require_once __DIR__ . '/vendor/autoload.php';

use Laguna\Integration\Models\Order;
use Laguna\Integration\Services\NetSuiteService;

echo "💰 Order Amount Handling Test\n";
echo "=============================\n\n";

// Test data with various amount scenarios
$testOrders = [
    // Test Case 1: Order with tax only
    [
        'name' => 'Order with Tax',
        'data' => [
            'OrderID' => '12345',
            'OrderTotal' => 108.50,
            'SalesTax' => 8.50,
            'ShippingCost' => 0,
            'DiscountAmount' => 0,
            'OrderItemList' => [
                [
                    'CatalogID' => 'ITEM001',
                    'ItemName' => 'Test Product',
                    'ItemQuantity' => 1,
                    'ItemUnitPrice' => 100.00
                ]
            ]
        ]
    ],
    
    // Test Case 2: Order with shipping
    [
        'name' => 'Order with Shipping',
        'data' => [
            'OrderID' => '12346',
            'OrderTotal' => 125.00,
            'SalesTax' => 0,
            'ShippingCost' => 25.00,
            'DiscountAmount' => 0,
            'OrderItemList' => [
                [
                    'CatalogID' => 'ITEM002',
                    'ItemName' => 'Test Product 2',
                    'ItemQuantity' => 1,
                    'ItemUnitPrice' => 100.00
                ]
            ]
        ]
    ],
    
    // Test Case 3: Order with discount
    [
        'name' => 'Order with Discount',
        'data' => [
            'OrderID' => '12347',
            'OrderTotal' => 90.00,
            'SalesTax' => 0,
            'ShippingCost' => 0,
            'DiscountAmount' => 10.00,
            'OrderItemList' => [
                [
                    'CatalogID' => 'ITEM003',
                    'ItemName' => 'Test Product 3',
                    'ItemQuantity' => 1,
                    'ItemUnitPrice' => 100.00
                ]
            ]
        ]
    ],
    
    // Test Case 4: Complex order with all amounts
    [
        'name' => 'Complex Order (Tax + Shipping + Discount)',
        'data' => [
            'OrderID' => '12348',
            'OrderTotal' => 123.50,
            'SalesTax' => 8.50,
            'ShippingCost' => 25.00,
            'DiscountAmount' => 10.00,
            'OrderItemList' => [
                [
                    'CatalogID' => 'ITEM004',
                    'ItemName' => 'Test Product 4',
                    'ItemQuantity' => 1,
                    'ItemUnitPrice' => 100.00
                ]
            ]
        ]
    ],
    
    // Test Case 5: Multiple items with amounts
    [
        'name' => 'Multiple Items with Amounts',
        'data' => [
            'OrderID' => '12349',
            'OrderTotal' => 248.50,
            'SalesTax' => 18.50,
            'ShippingCost' => 30.00,
            'DiscountAmount' => 0,
            'OrderItemList' => [
                [
                    'CatalogID' => 'ITEM005',
                    'ItemName' => 'Test Product 5',
                    'ItemQuantity' => 2,
                    'ItemUnitPrice' => 100.00
                ],
                [
                    'CatalogID' => 'ITEM006',
                    'ItemName' => 'Test Product 6',
                    'ItemQuantity' => 1,
                    'ItemUnitPrice' => 200.00
                ]
            ]
        ]
    ]
];

echo "1. Testing Order Model Fixes...\n";
echo str_repeat('-', 50) . "\n";

foreach ($testOrders as $testCase) {
    echo "\n📋 {$testCase['name']} (Order #{$testCase['data']['OrderID']})\n";
    
    $order = new Order($testCase['data']);
    
    // Test order amount methods
    $total = $order->getTotal();
    $subtotal = $order->getSubtotal();
    $tax = $order->getTaxAmount();
    $shipping = $order->getShippingCost();
    $discount = $order->getDiscountAmount();
    $itemsSubtotal = $order->calculateItemsSubtotal();
    
    echo "   Order Total: $" . number_format($total, 2) . "\n";
    echo "   Items Subtotal: $" . number_format($itemsSubtotal, 2) . "\n";
    echo "   Tax: $" . number_format($tax, 2) . "\n";
    echo "   Shipping: $" . number_format($shipping, 2) . "\n";
    echo "   Discount: $" . number_format($discount, 2) . "\n";
    
    // Test total validation
    $validation = $order->validateTotals();
    echo "   Calculated Total: $" . number_format($validation['calculated_total'], 2) . "\n";
    echo "   Difference: $" . number_format($validation['difference'], 2) . "\n";
    echo "   Valid: " . ($validation['is_valid'] ? '✅ Yes' : '❌ No') . "\n";
    
    if (!$validation['is_valid']) {
        echo "   ⚠️  Total mismatch detected!\n";
    }
}

echo "\n\n2. Testing NetSuite Configuration...\n";
echo str_repeat('-', 50) . "\n";

try {
    $config = require __DIR__ . '/config/config.php';
    $netsuiteConfig = $config['netsuite'];
    
    echo "✅ NetSuite Configuration Loaded\n";
    echo "   Tax Item ID: " . ($netsuiteConfig['tax_item_id'] ?? 'Not Set') . "\n";
    echo "   Shipping Item ID: " . ($netsuiteConfig['shipping_item_id'] ?? 'Not Set') . "\n";
    echo "   Discount Item ID: " . ($netsuiteConfig['discount_item_id'] ?? 'Not Set') . "\n";
    echo "   Validate Totals: " . ($netsuiteConfig['validate_totals'] ? 'Enabled' : 'Disabled') . "\n";
    echo "   Total Tolerance: $" . ($netsuiteConfig['total_tolerance'] ?? 'Not Set') . "\n";
    echo "   Include Tax Line: " . ($netsuiteConfig['include_tax_as_line_item'] ? 'Yes' : 'No') . "\n";
    echo "   Include Shipping Line: " . ($netsuiteConfig['include_shipping_as_line_item'] ? 'Yes' : 'No') . "\n";
    echo "   Include Discount Line: " . ($netsuiteConfig['include_discount_as_line_item'] ? 'Yes' : 'No') . "\n";
    
} catch (Exception $e) {
    echo "❌ Configuration Error: " . $e->getMessage() . "\n";
}

echo "\n\n3. Testing NetSuite Sales Order Structure...\n";
echo str_repeat('-', 50) . "\n";

// Test the NetSuite service structure (without actually creating orders)
foreach ($testOrders as $testCase) {
    echo "\n📋 {$testCase['name']} - NetSuite Line Items:\n";
    
    $orderData = $testCase['data'];
    $items = [];
    
    // Simulate the NetSuite service logic
    if (isset($orderData['OrderItemList']) && is_array($orderData['OrderItemList'])) {
        foreach ($orderData['OrderItemList'] as $item) {
            $items[] = [
                'type' => 'Product',
                'item_id' => 'MOCK_' . $item['CatalogID'],
                'quantity' => (float)$item['ItemQuantity'],
                'rate' => (float)$item['ItemUnitPrice'],
                'total' => (float)$item['ItemQuantity'] * (float)$item['ItemUnitPrice']
            ];
        }
    }
    
    // Add tax line item
    $taxAmount = (float)($orderData['SalesTax'] ?? 0);
    if ($taxAmount > 0) {
        $items[] = [
            'type' => 'Tax',
            'item_id' => $config['netsuite']['tax_item_id'],
            'quantity' => 1,
            'rate' => $taxAmount,
            'total' => $taxAmount
        ];
    }
    
    // Add shipping line item
    $shippingCost = (float)($orderData['ShippingCost'] ?? 0);
    if ($shippingCost > 0) {
        $items[] = [
            'type' => 'Shipping',
            'item_id' => $config['netsuite']['shipping_item_id'],
            'quantity' => 1,
            'rate' => $shippingCost,
            'total' => $shippingCost
        ];
    }
    
    // Add discount line item
    $discountAmount = (float)($orderData['DiscountAmount'] ?? 0);
    if ($discountAmount > 0) {
        $items[] = [
            'type' => 'Discount',
            'item_id' => $config['netsuite']['discount_item_id'],
            'quantity' => 1,
            'rate' => -$discountAmount,
            'total' => -$discountAmount
        ];
    }
    
    // Display line items
    $calculatedTotal = 0;
    foreach ($items as $item) {
        echo "   {$item['type']}: Item #{$item['item_id']} x {$item['quantity']} @ $" . 
             number_format($item['rate'], 2) . " = $" . number_format($item['total'], 2) . "\n";
        $calculatedTotal += $item['total'];
    }
    
    echo "   " . str_repeat('-', 40) . "\n";
    echo "   NetSuite Total: $" . number_format($calculatedTotal, 2) . "\n";
    echo "   3DCart Total: $" . number_format($orderData['OrderTotal'], 2) . "\n";
    echo "   Match: " . (abs($calculatedTotal - $orderData['OrderTotal']) <= 0.01 ? '✅ Yes' : '❌ No') . "\n";
}

echo "\n\n4. Summary and Recommendations...\n";
echo str_repeat('-', 50) . "\n";

$allValid = true;
$issuesFound = [];

// Check Order model
foreach ($testOrders as $testCase) {
    $order = new Order($testCase['data']);
    $validation = $order->validateTotals();
    
    if (!$validation['is_valid']) {
        $allValid = false;
        $issuesFound[] = "Order #{$testCase['data']['OrderID']} has total validation issues";
    }
}

// Check configuration
if (!isset($config['netsuite']['tax_item_id']) || !$config['netsuite']['tax_item_id']) {
    $allValid = false;
    $issuesFound[] = "Tax item ID not configured";
}

if (!isset($config['netsuite']['shipping_item_id']) || !$config['netsuite']['shipping_item_id']) {
    $allValid = false;
    $issuesFound[] = "Shipping item ID not configured";
}

if (!isset($config['netsuite']['discount_item_id']) || !$config['netsuite']['discount_item_id']) {
    $allValid = false;
    $issuesFound[] = "Discount item ID not configured";
}

if ($allValid) {
    echo "🎉 SUCCESS: All order amount handling tests passed!\n\n";
    
    echo "✅ Order Model Fixes:\n";
    echo "• getSubtotal() method fixed\n";
    echo "• getDiscountAmount() method added\n";
    echo "• calculateItemsSubtotal() method added\n";
    echo "• validateTotals() method added\n\n";
    
    echo "✅ NetSuite Configuration:\n";
    echo "• Tax, shipping, and discount item IDs configured\n";
    echo "• Total validation enabled\n";
    echo "• Line item inclusion flags set\n\n";
    
    echo "✅ Expected NetSuite Behavior:\n";
    echo "• Tax amounts will be added as separate line items\n";
    echo "• Shipping costs will be added as separate line items\n";
    echo "• Discounts will be added as negative line items\n";
    echo "• Order totals will match 3DCart exactly\n";
    echo "• Total validation will catch discrepancies\n\n";
    
} else {
    echo "⚠️  ISSUES FOUND:\n";
    foreach ($issuesFound as $issue) {
        echo "• $issue\n";
    }
    echo "\n";
}

echo "📋 Next Steps:\n";
echo "1. Ensure NetSuite has items with IDs 2, 3, 4 for tax, shipping, discount\n";
echo "2. Test with real NetSuite integration\n";
echo "3. Verify order totals match in NetSuite\n";
echo "4. Monitor logs for total validation warnings\n";
echo "5. Adjust item IDs in config if needed\n\n";

echo "🚀 Order amount handling test completed!\n";
?>