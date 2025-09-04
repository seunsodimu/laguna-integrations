<?php
require_once __DIR__ . '/vendor/autoload.php';

use Laguna\Integration\Services\NetSuiteService;

echo "Searching for Customer with Phone Number '260-637-0054'\n";
echo "======================================================\n\n";

try {
    $netSuiteService = new NetSuiteService();
    
    // Search for customers with the exact phone number
    $query = "SELECT id, firstName, lastName, email, companyName, phone FROM customer WHERE phone = '260-637-0054'";
    $results = $netSuiteService->executeSuiteQLQuery($query);
    
    echo "Query: $query\n\n";
    echo "Results:\n";
    
    if (!empty($results['items'])) {
        echo "Found " . count($results['items']) . " customer(s) with phone '260-637-0054':\n\n";
        foreach ($results['items'] as $customer) {
            echo "Customer ID: " . $customer['id'] . "\n";
            echo "Name: " . ($customer['firstName'] ?? '') . " " . ($customer['lastName'] ?? '') . "\n";
            echo "Email: " . ($customer['email'] ?? 'N/A') . "\n";
            echo "Company: " . ($customer['companyName'] ?? 'N/A') . "\n";
            echo "Phone: " . ($customer['phone'] ?? 'N/A') . "\n";
            echo "---\n";
        }
    } else {
        echo "No customers found with phone '260-637-0054'\n\n";
    }
    
    // Also search for similar phone numbers (in case of formatting differences)
    echo "Searching for similar phone numbers:\n";
    $query2 = "SELECT id, firstName, lastName, email, companyName, phone FROM customer WHERE phone LIKE '%260%637%0054%' OR phone LIKE '%2606370054%'";
    $results2 = $netSuiteService->executeSuiteQLQuery($query2);
    
    if (!empty($results2['items'])) {
        echo "Found " . count($results2['items']) . " customer(s) with similar phone numbers:\n\n";
        foreach ($results2['items'] as $customer) {
            echo "Customer ID: " . $customer['id'] . "\n";
            echo "Name: " . ($customer['firstName'] ?? '') . " " . ($customer['lastName'] ?? '') . "\n";
            echo "Email: " . ($customer['email'] ?? 'N/A') . "\n";
            echo "Company: " . ($customer['companyName'] ?? 'N/A') . "\n";
            echo "Phone: " . ($customer['phone'] ?? 'N/A') . "\n";
            echo "---\n";
        }
    } else {
        echo "No customers found with similar phone numbers\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error occurred:\n";
    echo "Error: " . $e->getMessage() . "\n";
}
?>