<?php
require_once __DIR__ . '/vendor/autoload.php';

use Laguna\Integration\Services\NetSuiteService;
use Laguna\Integration\Utils\Logger;

echo "Testing Customer Search in NetSuite\n";
echo "===================================\n\n";

try {
    $netSuiteService = new NetSuiteService();
    $logger = Logger::getInstance();
    
    // Search for customers with the company name that's causing the conflict
    $companyName = "1144809: OakTree Supply";
    echo "Searching for customers with company name: '$companyName'\n\n";
    
    // Use SuiteQL to search for customers by company name
    $query = "SELECT id, firstName, lastName, email, companyName, phone, isperson FROM customer WHERE companyName = '" . addslashes($companyName) . "'";
    
    echo "Executing query: $query\n\n";
    
    $results = $netSuiteService->executeSuiteQLQuery($query);
    
    if (!empty($results)) {
        echo "✅ Found " . count($results) . " customer(s) with this company name:\n";
        foreach ($results as $customer) {
            echo "  - ID: " . $customer['id'] . "\n";
            echo "    Name: " . $customer['firstName'] . " " . $customer['lastName'] . "\n";
            echo "    Email: " . $customer['email'] . "\n";
            echo "    Company: " . $customer['companyName'] . "\n";
            echo "    Phone: " . $customer['phone'] . "\n";
            echo "    Is Person: " . ($customer['isperson'] ? 'Yes' : 'No') . "\n\n";
        }
    } else {
        echo "❌ No customers found with company name '$companyName'\n\n";
    }
    
    // Also search for customers with email david@williams.com
    echo "Searching for customers with email: 'david@williams.com'\n\n";
    
    $emailQuery = "SELECT id, firstName, lastName, email, companyName, phone, isperson FROM customer WHERE email = 'david@williams.com'";
    $emailResults = $netSuiteService->executeSuiteQLQuery($emailQuery);
    
    if (!empty($emailResults)) {
        echo "✅ Found " . count($emailResults) . " customer(s) with this email:\n";
        foreach ($emailResults as $customer) {
            echo "  - ID: " . $customer['id'] . "\n";
            echo "    Name: " . $customer['firstName'] . " " . $customer['lastName'] . "\n";
            echo "    Email: " . $customer['email'] . "\n";
            echo "    Company: " . $customer['companyName'] . "\n";
            echo "    Phone: " . $customer['phone'] . "\n";
            echo "    Is Person: " . ($customer['isperson'] ? 'Yes' : 'No') . "\n\n";
        }
    } else {
        echo "❌ No customers found with email 'david@williams.com'\n\n";
    }
    
    // Search for any customers with "OakTree Supply" in company name
    echo "Searching for customers with 'OakTree Supply' in company name:\n\n";
    
    $companyQuery = "SELECT id, firstName, lastName, email, companyName, phone, isperson FROM customer WHERE UPPER(companyName) LIKE '%OAKTREE SUPPLY%'";
    $companyResults = $netSuiteService->executeSuiteQLQuery($companyQuery);
    
    if (!empty($companyResults)) {
        echo "✅ Found " . count($companyResults) . " customer(s) with 'OakTree Supply' in company name:\n";
        foreach ($companyResults as $customer) {
            echo "  - ID: " . $customer['id'] . "\n";
            echo "    Name: " . $customer['firstName'] . " " . $customer['lastName'] . "\n";
            echo "    Email: " . $customer['email'] . "\n";
            echo "    Company: " . $customer['companyName'] . "\n";
            echo "    Phone: " . $customer['phone'] . "\n";
            echo "    Is Person: " . ($customer['isperson'] ? 'Yes' : 'No') . "\n\n";
        }
    } else {
        echo "❌ No customers found with 'OakTree Supply' in company name\n\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error occurred:\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
?>