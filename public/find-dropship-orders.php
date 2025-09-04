<?php
/**
 * Find Dropship Orders
 * 
 * This script searches for orders with "Dropship to Customer" payment method
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
    
    $response = [
        'success' => true,
        'steps' => []
    ];
    
    $response['steps'][] = 'Searching for orders with "Dropship to Customer" payment method...';
    
    // Step 1: Initialize 3DCart service
    $response['steps'][] = 'Step 1: Initializing 3DCart service...';
    
    try {
        $threeDCartService = new ThreeDCartService();
        $response['steps'][] = "✅ 3DCart service initialized successfully";
    } catch (Exception $e) {
        $response['steps'][] = "❌ Failed to initialize 3DCart service: " . $e->getMessage();
        throw $e;
    }
    
    // Step 2: Get recent orders to search for dropship orders
    $response['steps'][] = 'Step 2: Fetching recent orders to search for dropship orders...';
    
    try {
        // Get orders from the last 30 days
        $startDate = date('Y-m-d', strtotime('-30 days'));
        $endDate = date('Y-m-d');
        
        $response['steps'][] = "Searching orders from {$startDate} to {$endDate}...";
        
        // Try different statuses to find dropship orders
        $allOrders = [];
        $statuses = [1, 2, 3, 4, 5]; // Different order statuses
        
        foreach ($statuses as $status) {
            try {
                $orders = $threeDCartService->getOrdersByDateRange($startDate, $endDate, $status);
                if (!empty($orders)) {
                    $allOrders = array_merge($allOrders, $orders);
                    $response['steps'][] = "Found " . count($orders) . " orders with status {$status}";
                }
            } catch (Exception $e) {
                $response['steps'][] = "⚠️ Error fetching orders with status {$status}: " . $e->getMessage();
            }
        }
        
        $response['total_orders_found'] = count($allOrders);
        $response['steps'][] = "Total orders found: " . count($allOrders);
        
        if (empty($allOrders)) {
            throw new Exception("No orders found in the last 30 days");
        }
        
    } catch (Exception $e) {
        $response['steps'][] = "❌ Failed to fetch orders: " . $e->getMessage();
        throw $e;
    }
    
    // Step 3: Search for dropship orders
    $response['steps'][] = 'Step 3: Analyzing orders for dropship payment method...';
    
    $dropshipOrders = [];
    $paymentMethods = [];
    
    foreach ($allOrders as $order) {
        $paymentMethod = $order['BillingPaymentMethod'] ?? 'Unknown';
        
        // Count payment methods
        if (!isset($paymentMethods[$paymentMethod])) {
            $paymentMethods[$paymentMethod] = 0;
        }
        $paymentMethods[$paymentMethod]++;
        
        // Check for dropship orders
        if ($paymentMethod === 'Dropship to Customer') {
            $dropshipOrders[] = [
                'OrderID' => $order['OrderID'],
                'OrderDate' => $order['OrderDate'] ?? 'N/A',
                'BillingPaymentMethod' => $paymentMethod,
                'CustomerID' => $order['CustomerID'] ?? 'N/A',
                'OrderAmount' => $order['OrderAmount'] ?? 'N/A',
                'ShipmentList' => $order['ShipmentList'] ?? [],
                'has_shipment_address' => !empty($order['ShipmentList'][0]['ShipmentAddress'] ?? '')
            ];
        }
    }
    
    $response['payment_methods_found'] = $paymentMethods;
    $response['dropship_orders'] = $dropshipOrders;
    $response['dropship_count'] = count($dropshipOrders);
    
    $response['steps'][] = "Found " . count($dropshipOrders) . " dropship orders";
    
    // Step 4: Analyze payment methods
    $response['steps'][] = 'Step 4: Analyzing payment methods distribution...';
    
    arsort($paymentMethods);
    foreach ($paymentMethods as $method => $count) {
        $response['steps'][] = "• {$method}: {$count} orders";
    }
    
    // Step 5: Show dropship order details
    if (!empty($dropshipOrders)) {
        $response['steps'][] = 'Step 5: Analyzing dropship order details...';
        
        foreach ($dropshipOrders as $index => $order) {
            $response['steps'][] = "Dropship Order #" . ($index + 1) . ": " . $order['OrderID'];
            
            if (!empty($order['ShipmentList'])) {
                $shipment = $order['ShipmentList'][0];
                $addressParts = [];
                
                if (!empty($shipment['ShipmentAddress'])) {
                    $addressParts[] = $shipment['ShipmentAddress'];
                }
                if (!empty($shipment['ShipmentAddress2'])) {
                    $addressParts[] = $shipment['ShipmentAddress2'];
                }
                if (!empty($shipment['ShipmentCity'])) {
                    $addressParts[] = $shipment['ShipmentCity'];
                }
                if (!empty($shipment['ShipmentState'])) {
                    $addressParts[] = $shipment['ShipmentState'];
                }
                if (!empty($shipment['ShipmentZipCode'])) {
                    $addressParts[] = $shipment['ShipmentZipCode'];
                }
                
                $expectedAddressee = implode(', ', $addressParts);
                $response['steps'][] = "  Expected addressee: " . ($expectedAddressee ?: 'N/A');
            }
        }
    } else {
        $response['steps'][] = 'Step 5: No dropship orders found in recent data';
    }
    
    // Summary
    $response['steps'][] = '📋 DROPSHIP ORDER SEARCH SUMMARY:';
    $response['steps'][] = "• Total Orders Searched: " . count($allOrders);
    $response['steps'][] = "• Dropship Orders Found: " . count($dropshipOrders);
    $response['steps'][] = "• Payment Methods Found: " . count($paymentMethods);
    $response['steps'][] = "• Most Common Payment Method: " . (array_keys($paymentMethods)[0] ?? 'N/A');
    
    // Overall status
    if (count($dropshipOrders) > 0) {
        $response['overall_status'] = 'SUCCESS - Found dropship orders';
        $response['steps'][] = '🎉 SUCCESS: Found dropship orders to test with!';
        $response['recommended_test_order'] = $dropshipOrders[0]['OrderID'] ?? null;
    } else {
        $response['overall_status'] = 'NO_DROPSHIP_ORDERS - Use test functionality';
        $response['steps'][] = '⚠️ NO DROPSHIP ORDERS: Use the test functionality to verify implementation';
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