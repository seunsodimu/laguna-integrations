<?php
/**
 * Find Joe Wiesnet Specifically
 * 
 * This script searches specifically for Joe Wiesnet using the company association
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Services\NetSuiteService;
use Laguna\Integration\Utils\Logger;
use Laguna\Integration\Middleware\AuthMiddleware;

// Start output buffering
ob_start();

// Set JSON content type
header('Content-Type: application/json');

// Log file
$logFile = __DIR__ . '/../logs/debug-find-joe.log';

function debugLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

try {
    debugLog("=== FIND JOE WIESNET START ===");
    
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
    
    // Load configuration
    $config = require __DIR__ . '/../config/config.php';
    date_default_timezone_set($config['app']['timezone']);
    
    // Initialize services
    $netSuiteService = new NetSuiteService();
    $logger = Logger::getInstance();
    
    debugLog("Services initialized");
    
    // Search criteria
    $searchEmail = 'joe@buffalowoodturningproducts.com';
    $searchFirstName = 'Joe';
    $searchLastName = 'Wiesnet';
    
    debugLog("Searching for Joe Wiesnet with email: $searchEmail");
    
    // Search for customers with Buffalo company and matching name/email
    $queries = [
        'buffalo_and_email' => "SELECT id, firstName, lastName, email, companyName, phone, isperson FROM customer WHERE UPPER(companyName) LIKE '%BUFFALO%' AND email = '$searchEmail'",
        'buffalo_and_name' => "SELECT id, firstName, lastName, email, companyName, phone, isperson FROM customer WHERE UPPER(companyName) LIKE '%BUFFALO%' AND firstName = '$searchFirstName' AND lastName = '$searchLastName'",
        'buffalo_and_name_partial' => "SELECT id, firstName, lastName, email, companyName, phone, isperson FROM customer WHERE UPPER(companyName) LIKE '%BUFFALO%' AND (firstName = '$searchFirstName' OR UPPER(firstName) LIKE '%JOE%')",
        'all_buffalo_customers' => "SELECT id, firstName, lastName, email, companyName, phone, isperson FROM customer WHERE UPPER(companyName) LIKE '%BUFFALO%' ORDER BY datecreated DESC"
    ];
    
    $results = [];
    
    foreach ($queries as $queryName => $query) {
        debugLog("Executing query: $queryName");
        debugLog("SQL: $query");
        
        try {
            $result = $netSuiteService->executeSuiteQLQuery($query);
            $results[$queryName] = $result;
            
            if (isset($result['items']) && count($result['items']) > 0) {
                debugLog("Query $queryName found " . count($result['items']) . " results");
                
                // Log first few results for analysis
                foreach (array_slice($result['items'], 0, 3) as $i => $customer) {
                    debugLog("Result " . ($i + 1) . ": ID=" . ($customer['id'] ?? 'N/A') . 
                           ", Name=" . ($customer['firstName'] ?? '') . ' ' . ($customer['lastName'] ?? '') . 
                           ", Email=" . ($customer['email'] ?? 'N/A') . 
                           ", Company=" . ($customer['companyName'] ?? 'N/A'));
                }
            } else {
                debugLog("Query $queryName found no results");
            }
        } catch (Exception $e) {
            debugLog("Query $queryName failed: " . $e->getMessage());
            $results[$queryName] = ['error' => $e->getMessage()];
        }
    }
    
    // Analyze results to find exact match
    $exactMatch = null;
    $possibleMatches = [];
    
    foreach ($results as $queryName => $result) {
        if (isset($result['items'])) {
            foreach ($result['items'] as $customer) {
                // Check for exact email match
                if (isset($customer['email']) && strtolower($customer['email']) === strtolower($searchEmail)) {
                    $exactMatch = $customer;
                    $exactMatch['found_by'] = $queryName;
                    break 2;
                }
                
                // Check for name match
                if (isset($customer['firstName']) && isset($customer['lastName']) &&
                    strtolower($customer['firstName']) === strtolower($searchFirstName) &&
                    strtolower($customer['lastName']) === strtolower($searchLastName)) {
                    $possibleMatches[] = [
                        'customer' => $customer,
                        'found_by' => $queryName,
                        'match_type' => 'name_match'
                    ];
                }
            }
        }
    }
    
    debugLog("Analysis complete. Exact match: " . ($exactMatch ? 'YES' : 'NO'));
    debugLog("Possible matches: " . count($possibleMatches));
    
    $response = [
        'success' => true,
        'search_criteria' => [
            'email' => $searchEmail,
            'firstName' => $searchFirstName,
            'lastName' => $searchLastName
        ],
        'exact_match' => $exactMatch,
        'possible_matches' => $possibleMatches,
        'all_results' => $results,
        'recommendation' => null
    ];
    
    // Add recommendation
    if ($exactMatch) {
        $response['recommendation'] = "✅ EXACT MATCH FOUND! Use customer ID: " . $exactMatch['id'];
        debugLog("Recommendation: Use customer ID " . $exactMatch['id']);
    } elseif (count($possibleMatches) > 0) {
        $response['recommendation'] = "⚠️ Found " . count($possibleMatches) . " possible matches by name. Review manually.";
        debugLog("Recommendation: Review " . count($possibleMatches) . " possible matches");
    } else {
        $response['recommendation'] = "❌ No matches found. Safe to create new customer.";
        debugLog("Recommendation: Create new customer");
    }
    
    ob_clean();
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    debugLog("Exception: " . $e->getMessage());
    
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => true
    ]);
}

debugLog("=== FIND JOE WIESNET END ===");
?>