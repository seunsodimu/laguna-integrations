<?php
/**
 * Test Discount Fix with Real NetSuite Order Creation
 * 
 * This test will create a real NetSuite order using the fixed discount logic
 * to verify that discounts now appear correctly in NetSuite
 */

require_once __DIR__ . '/vendor/autoload.php';

use Laguna\Integration\Services\NetSuiteService;
use Laguna\Integration\Utils\Logger;

echo "🧪 Testing Discount Fix with Real NetSuite Order Creation\n";
echo "=========================================================\n\n";

// Load the test order data
$testOrderData = json_decode(file_get_contents(__DIR__ . '/testOrder.json'), true);
if (!$testOrderData || !isset($testOrderData[0])) {
    echo "❌ Failed to load test order data\n";
    exit(1);
}

$orderData = $testOrderData[0];

// Modify the order ID to avoid conflicts
$orderData['OrderID'] = 'TEST_DISCOUNT_' . time();
$orderData['InvoiceNumber'] = 99999;

echo "1. Order Data Analysis...\n";
echo str_repeat('-', 40) . "\n";
echo "📋 Test Order: " . $orderData['OrderID'] . "\n";
echo "   3DCart OrderAmount: $" . number_format($orderData['OrderAmount'], 2) . "\n";
echo "   3DCart OrderDiscount: $" . number_format($orderData['OrderDiscount'], 2) . "\n";
echo "   Expected NetSuite Total: $" . number_format($orderData['OrderAmount'], 2) . "\n";

// Calculate items subtotal for verification
$itemsSubtotal = 0;
foreach ($orderData['OrderItemList'] as $item) {
    $itemTotal = ($item['ItemUnitPrice'] + $item['ItemOptionPrice']) * $item['ItemQuantity'];
    $itemsSubtotal += $itemTotal;
}
echo "   Items Subtotal: $" . number_format($itemsSubtotal, 2) . "\n";
echo "   Discount Amount: -$" . number_format($orderData['OrderDiscount'], 2) . "\n";
echo "   Calculation: $" . number_format($itemsSubtotal, 2) . " - $" . number_format($orderData['OrderDiscount'], 2) . " = $" . number_format($itemsSubtotal - $orderData['OrderDiscount'], 2) . "\n";

echo "\n2. Testing NetSuite Service with Fixed Logic...\n";
echo str_repeat('-', 40) . "\n";

try {
    $netSuiteService = new NetSuiteService();
    
    // Test connection first
    echo "🔗 Testing NetSuite connection...\n";
    $connectionTest = $netSuiteService->testConnection();
    if (!$connectionTest['success']) {
        echo "❌ NetSuite connection failed: " . $connectionTest['message'] . "\n";
        exit(1);
    }
    echo "✅ NetSuite connection successful\n";
    
    echo "\n📝 Creating test sales order with discount...\n";
    
    // Create a simplified test order to avoid customer creation issues
    $testOrderSimplified = [
        'OrderID' => $orderData['OrderID'],
        'OrderAmount' => $orderData['OrderAmount'],
        'OrderDiscount' => $orderData['OrderDiscount'],
        'OrderDiscountPromotion' => $orderData['OrderDiscountPromotion'],
        'SalesTax' => $orderData['SalesTax'],
        'BillingFirstName' => $orderData['BillingFirstName'],
        'BillingLastName' => $orderData['BillingLastName'],
        'BillingCompany' => $orderData['BillingCompany'],
        'BillingEmail' => $orderData['BillingEmail'],
        'BillingAddress' => $orderData['BillingAddress'],
        'BillingCity' => $orderData['BillingCity'],
        'BillingState' => $orderData['BillingState'],
        'BillingZipCode' => $orderData['BillingZipCode'],
        'BillingCountry' => $orderData['BillingCountry'],
        'BillingPhoneNumber' => $orderData['BillingPhoneNumber'],
        'OrderDate' => $orderData['OrderDate'],
        'PromotionList' => $orderData['PromotionList'],
        'OrderItemList' => array_slice($orderData['OrderItemList'], 0, 2), // Just first 2 items for testing
        'ShipmentList' => $orderData['ShipmentList']
    ];
    
    // Recalculate totals for simplified order
    $simplifiedItemsTotal = 0;
    foreach ($testOrderSimplified['OrderItemList'] as $item) {
        $itemTotal = ($item['ItemUnitPrice'] + $item['ItemOptionPrice']) * $item['ItemQuantity'];
        $simplifiedItemsTotal += $itemTotal;
    }
    
    // Adjust discount proportionally
    $discountRatio = $testOrderSimplified['OrderDiscount'] / $itemsSubtotal;
    $adjustedDiscount = $simplifiedItemsTotal * $discountRatio;
    $testOrderSimplified['OrderDiscount'] = $adjustedDiscount;
    $testOrderSimplified['OrderAmount'] = $simplifiedItemsTotal - $adjustedDiscount;
    
    echo "   Simplified test order:\n";
    echo "   - Items: " . count($testOrderSimplified['OrderItemList']) . "\n";
    echo "   - Items Total: $" . number_format($simplifiedItemsTotal, 2) . "\n";
    echo "   - Discount: $" . number_format($adjustedDiscount, 2) . "\n";
    echo "   - Final Total: $" . number_format($testOrderSimplified['OrderAmount'], 2) . "\n";
    
    // Try to create the order
    echo "\n🚀 Attempting to create NetSuite sales order...\n";
    
    $startTime = microtime(true);
    $result = $netSuiteService->createSalesOrder($testOrderSimplified);
    $duration = (microtime(true) - $startTime) * 1000;
    
    if ($result && isset($result['id'])) {
        echo "✅ SUCCESS: NetSuite sales order created!\n";
        echo "   NetSuite Order ID: " . $result['id'] . "\n";
        echo "   Creation Time: " . number_format($duration, 2) . "ms\n";
        
        // Retrieve the created order to verify discount
        echo "\n🔍 Retrieving created order to verify discount...\n";
        
        try {
            $createdOrder = $netSuiteService->getSalesOrder($result['id']);
            
            if ($createdOrder) {
                echo "✅ Order retrieved successfully\n";
                echo "   NetSuite Total: $" . number_format($createdOrder['total'] ?? 0, 2) . "\n";
                echo "   Expected Total: $" . number_format($testOrderSimplified['OrderAmount'], 2) . "\n";
                echo "   Discount Total: $" . number_format($createdOrder['discountTotal'] ?? 0, 2) . "\n";
                
                $totalMatch = abs(($createdOrder['total'] ?? 0) - $testOrderSimplified['OrderAmount']) <= 0.01;
                $discountMatch = abs(($createdOrder['discountTotal'] ?? 0) - $adjustedDiscount) <= 0.01;
                
                echo "\n📊 Verification Results:\n";
                echo "   Total Match: " . ($totalMatch ? "✅ Perfect" : "❌ Mismatch") . "\n";
                echo "   Discount Applied: " . ($discountMatch ? "✅ Correct" : "❌ Missing") . "\n";
                
                if ($totalMatch && $discountMatch) {
                    echo "\n🎉 DISCOUNT FIX VERIFIED: Working perfectly!\n";
                    echo "   ✅ Discount appears in NetSuite\n";
                    echo "   ✅ Total matches exactly\n";
                    echo "   ✅ Integration is working correctly\n";
                } else {
                    echo "\n⚠️  Issues detected:\n";
                    if (!$totalMatch) {
                        echo "   • Total mismatch: Expected $" . number_format($testOrderSimplified['OrderAmount'], 2) . 
                             ", Got $" . number_format($createdOrder['total'] ?? 0, 2) . "\n";
                    }
                    if (!$discountMatch) {
                        echo "   • Discount mismatch: Expected $" . number_format($adjustedDiscount, 2) . 
                             ", Got $" . number_format($createdOrder['discountTotal'] ?? 0, 2) . "\n";
                    }
                }
                
                // Show line items if available
                if (isset($createdOrder['item']) || isset($createdOrder['items'])) {
                    echo "\n📋 NetSuite Line Items:\n";
                    // This would require additional API call to get line items
                    echo "   (Line item details require separate API call)\n";
                }
                
            } else {
                echo "⚠️  Could not retrieve created order for verification\n";
            }
            
        } catch (Exception $e) {
            echo "⚠️  Error retrieving created order: " . $e->getMessage() . "\n";
        }
        
    } else {
        echo "❌ Failed to create NetSuite sales order\n";
        if (is_array($result)) {
            echo "   Response: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error during testing: " . $e->getMessage() . "\n";
    echo "   Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n3. Log Analysis...\n";
echo str_repeat('-', 40) . "\n";

// Check recent logs for discount processing
$logger = Logger::getInstance();
$logFile = __DIR__ . '/logs/app-' . date('Y-m-d') . '.log';

if (file_exists($logFile)) {
    echo "📋 Checking today's logs for discount processing...\n";
    
    $logContent = file_get_contents($logFile);
    $lines = explode("\n", $logContent);
    $recentLines = array_slice($lines, -50); // Last 50 lines
    
    $discountLogs = array_filter($recentLines, function($line) {
        return stripos($line, 'discount') !== false || 
               stripos($line, 'promotion') !== false ||
               stripos($line, 'OrderDiscount') !== false;
    });
    
    if (!empty($discountLogs)) {
        echo "✅ Found discount-related log entries:\n";
        foreach ($discountLogs as $log) {
            echo "   " . trim($log) . "\n";
        }
    } else {
        echo "ℹ️  No discount-specific log entries found in recent logs\n";
    }
} else {
    echo "ℹ️  No log file found for today\n";
}

echo "\n4. Summary...\n";
echo str_repeat('-', 40) . "\n";

echo "🎯 Discount Fix Test Summary:\n";
echo "• Fixed field mapping from DiscountAmount → OrderDiscount\n";
echo "• Fixed total field mapping from OrderTotal → OrderAmount\n";
echo "• Enhanced Order model to include ItemOptionPrice\n";
echo "• Added discount line items to NetSuite orders\n";
echo "• Implemented comprehensive validation\n\n";

echo "📋 Next Steps:\n";
echo "1. Monitor production orders for discount accuracy\n";
echo "2. Verify discount line items appear in NetSuite UI\n";
echo "3. Test with various discount types and amounts\n";
echo "4. Update documentation with new field mappings\n\n";

echo "🚀 Discount fix testing completed!\n";
?>