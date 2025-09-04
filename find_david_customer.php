<?php
require_once __DIR__ . '/vendor/autoload.php';

use Laguna\Integration\Services\NetSuiteService;

echo "Comprehensive Search for David Williams Customer\n";
echo "===============================================\n\n";

try {
    $netSuiteService = new NetSuiteService();
    
    // Search 1: Exact email match
    echo "1. Searching for exact email 'david@williams.com':\n";
    $query1 = "SELECT id, firstName, lastName, email, companyName, isinactive FROM customer WHERE email = 'david@williams.com'";
    $results1 = $netSuiteService->executeSuiteQLQuery($query1);
    echo "Results: " . count($results1['items'] ?? []) . " customers\n";
    if (!empty($results1['items'])) {
        print_r($results1['items']);
    }
    echo "\n";
    
    // Search 2: Case insensitive email
    echo "2. Searching for case-insensitive email:\n";
    $query2 = "SELECT id, firstName, lastName, email, companyName, isinactive FROM customer WHERE UPPER(email) = 'DAVID@WILLIAMS.COM'";
    $results2 = $netSuiteService->executeSuiteQLQuery($query2);
    echo "Results: " . count($results2['items'] ?? []) . " customers\n";
    if (!empty($results2['items'])) {
        print_r($results2['items']);
    }
    echo "\n";
    
    // Search 3: Email contains 'david' and 'williams'
    echo "3. Searching for emails containing 'david' and 'williams':\n";
    $query3 = "SELECT id, firstName, lastName, email, companyName, isinactive FROM customer WHERE UPPER(email) LIKE '%DAVID%' AND UPPER(email) LIKE '%WILLIAMS%'";
    $results3 = $netSuiteService->executeSuiteQLQuery($query3);
    echo "Results: " . count($results3['items'] ?? []) . " customers\n";
    if (!empty($results3['items'])) {
        print_r($results3['items']);
    }
    echo "\n";
    
    // Search 4: Name-based search
    echo "4. Searching by name 'David Williams':\n";
    $query4 = "SELECT id, firstName, lastName, email, companyName, isinactive FROM customer WHERE UPPER(firstName) = 'DAVID' AND UPPER(lastName) = 'WILLIAMS'";
    $results4 = $netSuiteService->executeSuiteQLQuery($query4);
    echo "Results: " . count($results4['items'] ?? []) . " customers\n";
    if (!empty($results4['items'])) {
        print_r($results4['items']);
    }
    echo "\n";
    
    // Search 5: Include inactive customers
    echo "5. Searching for all customers with 'williams' in email (including inactive):\n";
    $query5 = "SELECT id, firstName, lastName, email, companyName, isinactive FROM customer WHERE UPPER(email) LIKE '%WILLIAMS%'";
    $results5 = $netSuiteService->executeSuiteQLQuery($query5);
    echo "Results: " . count($results5['items'] ?? []) . " customers\n";
    if (!empty($results5['items'])) {
        foreach ($results5['items'] as $customer) {
            echo "  - ID: {$customer['id']}, Name: {$customer['firstName']} {$customer['lastName']}, Email: {$customer['email']}, Inactive: " . ($customer['isinactive'] ? 'Yes' : 'No') . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error occurred:\n";
    echo "Error: " . $e->getMessage() . "\n";
}
?>