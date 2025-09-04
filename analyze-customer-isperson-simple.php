<?php

require_once __DIR__ . '/vendor/autoload.php';

use Laguna\Integration\Services\ThreeDCartService;
use Laguna\Integration\Utils\Logger;

/**
 * Simple analysis of customer isPerson values for non-dropship orders
 */

echo "<h1>Customer isPerson Analysis for Non-Dropship Orders</h1>\n";

try {
    $threeDCartService = new ThreeDCartService();
    $logger = Logger::getInstance();
    
    echo "<h2>Fetching Recent Orders from 3DCart</h2>\n";
    
    // Get recent orders
    $orders = $threeDCartService->getOrders(['limit' => 50]);
    
    $dropshipOrders = [];
    $regularOrders = [];
    $paymentMethods = [];
    
    foreach ($orders as $order) {
        $paymentMethod = $order['BillingPaymentMethod'] ?? 'Unknown';
        
        // Count payment methods
        if (!isset($paymentMethods[$paymentMethod])) {
            $paymentMethods[$paymentMethod] = 0;
        }
        $paymentMethods[$paymentMethod]++;
        
        if ($paymentMethod === 'Dropship to Customer') {
            $dropshipOrders[] = $order;
        } else {
            $regularOrders[] = $order;
        }
    }
    
    echo "<h3>Order Distribution:</h3>\n";
    echo "<ul>\n";
    echo "<li><strong>Total Orders:</strong> " . count($orders) . "</li>\n";
    echo "<li><strong>Dropship Orders:</strong> " . count($dropshipOrders) . "</li>\n";
    echo "<li><strong>Regular Orders:</strong> " . count($regularOrders) . "</li>\n";
    echo "</ul>\n";
    
    echo "<h3>Payment Methods Found:</h3>\n";
    echo "<ul>\n";
    foreach ($paymentMethods as $method => $count) {
        $type = ($method === 'Dropship to Customer') ? 'DROPSHIP' : 'REGULAR';
        echo "<li><strong>{$method}:</strong> {$count} orders ({$type})</li>\n";
    }
    echo "</ul>\n";
    
    if (!empty($regularOrders)) {
        echo "<h2>Regular (Non-Dropship) Order Analysis</h2>\n";
        
        foreach (array_slice($regularOrders, 0, 5) as $index => $order) {
            echo "<h3>Regular Order #" . ($index + 1) . " - ID: {$order['OrderID']}</h3>\n";
            
            $paymentMethod = $order['BillingPaymentMethod'] ?? 'Unknown';
            $billingEmail = $order['BillingEmail'] ?? '';
            $billingPhone = $order['BillingPhoneNumber'] ?? '';
            $billingCompany = $order['BillingCompany'] ?? '';
            
            echo "<ul>\n";
            echo "<li><strong>Payment Method:</strong> {$paymentMethod}</li>\n";
            echo "<li><strong>Billing Email:</strong> {$billingEmail}</li>\n";
            echo "<li><strong>Billing Phone:</strong> {$billingPhone}</li>\n";
            echo "<li><strong>Billing Company:</strong> " . ($billingCompany ?: 'N/A') . "</li>\n";
            echo "</ul>\n";
            
            // Extract customer email from QuestionList
            $customerEmail = '';
            if (isset($order['QuestionList']) && is_array($order['QuestionList'])) {
                foreach ($order['QuestionList'] as $question) {
                    if (isset($question['QuestionAnswer']) && filter_var($question['QuestionAnswer'], FILTER_VALIDATE_EMAIL)) {
                        $customerEmail = $question['QuestionAnswer'];
                        break;
                    }
                }
            }
            
            echo "<h4>Customer Search Process (Based on Code Analysis):</h4>\n";
            echo "<ol>\n";
            echo "<li><strong>Extract Customer Email from QuestionList:</strong> " . ($customerEmail ?: 'None found') . "</li>\n";
            
            if ($customerEmail) {
                echo "<li><strong>Store Customer Search Query:</strong><br>\n";
                echo "<code>SELECT id, firstName, lastName, email, companyName, phone, isperson<br>\n";
                echo "FROM customer<br>\n";
                echo "WHERE email = '{$customerEmail}' AND <strong>isperson = 'F'</strong></code></li>\n";
            } else {
                echo "<li><strong>Store Customer Search:</strong> Skipped (no valid email in QuestionList)</li>\n";
            }
            
            echo "<li><strong>Parent Company Search Query:</strong><br>\n";
            echo "<code>SELECT id, firstName, lastName, email, companyName, phone, isperson<br>\n";
            echo "FROM customer<br>\n";
            echo "WHERE (email = '{$billingEmail}' OR phone = '{$billingPhone}') AND <strong>isperson = 'F'</strong></code></li>\n";
            
            echo "<li><strong>Customer Creation (if not found):</strong><br>\n";
            echo "Create new customer with <strong>isPerson = false</strong> (company customer)</li>\n";
            echo "</ol>\n";
            
            echo "<hr>\n";
        }
    }
    
    if (!empty($dropshipOrders)) {
        echo "<h2>Dropship Order Analysis (for comparison)</h2>\n";
        
        foreach (array_slice($dropshipOrders, 0, 2) as $index => $order) {
            echo "<h3>Dropship Order #" . ($index + 1) . " - ID: {$order['OrderID']}</h3>\n";
            
            $paymentMethod = $order['BillingPaymentMethod'] ?? 'Unknown';
            $billingEmail = $order['BillingEmail'] ?? '';
            $billingPhone = $order['BillingPhoneNumber'] ?? '';
            
            echo "<ul>\n";
            echo "<li><strong>Payment Method:</strong> {$paymentMethod}</li>\n";
            echo "<li><strong>Billing Email:</strong> {$billingEmail}</li>\n";
            echo "<li><strong>Billing Phone:</strong> {$billingPhone}</li>\n";
            echo "</ul>\n";
            
            echo "<h4>Customer Search Process (Based on Code Analysis):</h4>\n";
            echo "<ol>\n";
            echo "<li><strong>Parent Company Search Query:</strong><br>\n";
            echo "<code>SELECT id, firstName, lastName, email, companyName, phone, isperson<br>\n";
            echo "FROM customer<br>\n";
            echo "WHERE (email = '{$billingEmail}' OR phone = '{$billingPhone}') AND <strong>isperson = 'F'</strong></code></li>\n";
            
            echo "<li><strong>Customer Creation:</strong><br>\n";
            echo "Create new customer with <strong>isPerson = true</strong> (person customer)</li>\n";
            echo "</ol>\n";
            
            echo "<hr>\n";
        }
    }
    
    echo "<h2>Code Analysis Summary</h2>\n";
    
    echo "<h3>Customer Search Queries (from NetSuiteService.php):</h3>\n";
    echo "<table border='1' cellpadding='10' cellspacing='0' style='border-collapse: collapse;'>\n";
    echo "<tr style='background-color: #f0f0f0;'>\n";
    echo "<th>Search Type</th><th>Query</th><th>isPerson Filter</th><th>Line #</th>\n";
    echo "</tr>\n";
    echo "<tr>\n";
    echo "<td><strong>Store Customer</strong></td>\n";
    echo "<td><code>SELECT * FROM customer WHERE email = '{QuestionList->email}' AND isperson = 'F'</code></td>\n";
    echo "<td><strong>'F' (Company)</strong></td>\n";
    echo "<td>739</td>\n";
    echo "</tr>\n";
    echo "<tr>\n";
    echo "<td><strong>Parent Company</strong></td>\n";
    echo "<td><code>SELECT * FROM customer WHERE (email = '{BillingEmail}' OR phone = '{BillingPhone}') AND isperson = 'F'</code></td>\n";
    echo "<td><strong>'F' (Company)</strong></td>\n";
    echo "<td>701-702</td>\n";
    echo "</tr>\n";
    echo "</table>\n";
    
    echo "<h3>Customer Creation Logic (from NetSuiteService.php):</h3>\n";
    echo "<table border='1' cellpadding='10' cellspacing='0' style='border-collapse: collapse;'>\n";
    echo "<tr style='background-color: #f0f0f0;'>\n";
    echo "<th>Order Type</th><th>isPerson Value</th><th>Customer Type</th><th>Line #</th>\n";
    echo "</tr>\n";
    echo "<tr>\n";
    echo "<td><strong>Regular Orders</strong></td>\n";
    echo "<td><strong>false</strong></td>\n";
    echo "<td>Company Customer</td>\n";
    echo "<td>828</td>\n";
    echo "</tr>\n";
    echo "<tr>\n";
    echo "<td><strong>Dropship Orders</strong></td>\n";
    echo "<td><strong>true</strong></td>\n";
    echo "<td>Person Customer</td>\n";
    echo "<td>782</td>\n";
    echo "</tr>\n";
    echo "</table>\n";
    
    echo "<h2>✅ Key Findings</h2>\n";
    echo "<ul>\n";
    echo "<li>✅ <strong>All customer searches use isPerson = 'F'</strong> - The system only searches for company customers</li>\n";
    echo "<li>✅ <strong>Regular orders create company customers</strong> - isPerson = false</li>\n";
    echo "<li>✅ <strong>Dropship orders create person customers</strong> - isPerson = true</li>\n";
    echo "<li>✅ <strong>No person customer searches</strong> - The system never searches for isPerson = 'T'</li>\n";
    echo "<li>✅ <strong>Consistent logic</strong> - All non-dropship orders are treated as company orders</li>\n";
    echo "</ul>\n";
    
    echo "<h2>🔍 Answer to Your Question</h2>\n";
    echo "<div style='background-color: #e8f5e8; padding: 15px; border-left: 5px solid #4CAF50;'>\n";
    echo "<p><strong>For orders that aren't dropship, the isPerson value of customers searched for is:</strong></p>\n";
    echo "<h3 style='color: #2E7D32;'>isPerson = 'F' (False - Company Customers)</h3>\n";
    echo "<p>The system searches for existing company customers using:</p>\n";
    echo "<ul>\n";
    echo "<li><strong>Store Customer Search:</strong> <code>isperson = 'F'</code></li>\n";
    echo "<li><strong>Parent Company Search:</strong> <code>isperson = 'F'</code></li>\n";
    echo "</ul>\n";
    echo "<p>When creating new customers for regular orders, they are created with <strong>isPerson = false</strong> (company customers).</p>\n";
    echo "</div>\n";
    
} catch (Exception $e) {
    echo "<p><strong>❌ Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>\n";
}

?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h1, h2, h3 { color: #333; }
ul, ol { margin: 10px 0; }
li { margin: 5px 0; }
pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; }
code { background: #f0f0f0; padding: 2px 4px; border-radius: 3px; font-family: monospace; }
hr { margin: 20px 0; border: 1px solid #ddd; }
table { width: 100%; margin: 10px 0; }
th, td { text-align: left; padding: 8px; border: 1px solid #ddd; }
th { background-color: #f0f0f0; font-weight: bold; }
</style>