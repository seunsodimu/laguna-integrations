<?php
require_once __DIR__ . '/vendor/autoload.php';

use Laguna\Integration\Services\NetSuiteService;

echo "Debug: Customers with Email 'david@williams.com'\n";
echo "===============================================\n\n";

try {
    $netSuiteService = new NetSuiteService();
    
    // Search for customers with the email
    $query = "SELECT id, firstName, lastName, email, companyName FROM customer WHERE email = 'david@williams.com'";
    $results = $netSuiteService->executeSuiteQLQuery($query);
    
    echo "Query: $query\n\n";
    echo "Results:\n";
    var_dump($results);
    
    // Also try case-insensitive search
    echo "\n\nCase-insensitive search:\n";
    $query2 = "SELECT id, firstName, lastName, email, companyName FROM customer WHERE UPPER(email) = UPPER('david@williams.com')";
    $results2 = $netSuiteService->executeSuiteQLQuery($query2);
    
    echo "Query: $query2\n\n";
    echo "Results:\n";
    var_dump($results2);
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>