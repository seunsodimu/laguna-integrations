<?php
require_once __DIR__ . '/vendor/autoload.php';

use Laguna\Integration\Services\NetSuiteService;

echo "Debug: Existing Customers with Company Name '1144809: OakTree Supply'\n";
echo "================================================================\n\n";

try {
    $netSuiteService = new NetSuiteService();
    
    // Search for customers with the exact company name
    $query = "SELECT id, firstName, lastName, email, companyName FROM customer WHERE companyName = '1144809: OakTree Supply'";
    $results = $netSuiteService->executeSuiteQLQuery($query);
    
    echo "Found " . count($results) . " customers:\n\n";
    
    // Debug the structure of results
    echo "Raw results structure:\n";
    var_dump($results);
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>