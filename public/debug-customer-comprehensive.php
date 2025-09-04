<?php
/**
 * Comprehensive Customer Search Debug
 * 
 * This script searches for customers using multiple criteria to find potential matches
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
$logFile = __DIR__ . '/../logs/debug-customer-comprehensive.log';

function debugLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

try {
    debugLog("=== COMPREHENSIVE CUSTOMER SEARCH START ===");
    
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
    
    // Customer details to search for
    $searchEmail = 'joe@buffalowoodturningproducts.com';
    $searchFirstName = 'Joe';
    $searchLastName = 'Wiesnet';
    $searchCompany = 'Buffalo Wood Turning Products'; // Guessing from email domain
    
    debugLog("Searching for customer: $searchFirstName $searchLastName, Email: $searchEmail");
    
    $searchResults = [];
    
    // 1. Search by exact email (case sensitive)
    debugLog("1. Testing exact email search (case sensitive)...");
    try {
        $query1 = "SELECT id, firstName, lastName, email, companyName, phone FROM customer WHERE email = '$searchEmail'";
        $result1 = $netSuiteService->executeSuiteQLQuery($query1);
        $searchResults['exact_email'] = $result1;
        debugLog("Exact email search result: " . json_encode($result1));
    } catch (Exception $e) {
        debugLog("Exact email search failed: " . $e->getMessage());
        $searchResults['exact_email'] = ['error' => $e->getMessage()];
    }
    
    // 2. Search by email (case insensitive)
    debugLog("2. Testing case-insensitive email search...");
    try {
        $query2 = "SELECT id, firstName, lastName, email, companyName, phone FROM customer WHERE UPPER(email) = UPPER('$searchEmail')";
        $result2 = $netSuiteService->executeSuiteQLQuery($query2);
        $searchResults['case_insensitive_email'] = $result2;
        debugLog("Case-insensitive email search result: " . json_encode($result2));
    } catch (Exception $e) {
        debugLog("Case-insensitive email search failed: " . $e->getMessage());
        $searchResults['case_insensitive_email'] = ['error' => $e->getMessage()];
    }
    
    // 3. Search by first and last name
    debugLog("3. Testing name search...");
    try {
        $query3 = "SELECT id, firstName, lastName, email, companyName, phone FROM customer WHERE firstName = '$searchFirstName' AND lastName = '$searchLastName'";
        $result3 = $netSuiteService->executeSuiteQLQuery($query3);
        $searchResults['name_search'] = $result3;
        debugLog("Name search result: " . json_encode($result3));
    } catch (Exception $e) {
        debugLog("Name search failed: " . $e->getMessage());
        $searchResults['name_search'] = ['error' => $e->getMessage()];
    }
    
    // 4. Search by partial email domain
    debugLog("4. Testing domain search...");
    try {
        $emailDomain = substr($searchEmail, strpos($searchEmail, '@'));
        $query4 = "SELECT id, firstName, lastName, email, companyName, phone FROM customer WHERE email LIKE '%$emailDomain'";
        $result4 = $netSuiteService->executeSuiteQLQuery($query4);
        $searchResults['domain_search'] = $result4;
        debugLog("Domain search result: " . json_encode($result4));
    } catch (Exception $e) {
        debugLog("Domain search failed: " . $e->getMessage());
        $searchResults['domain_search'] = ['error' => $e->getMessage()];
    }
    
    // 5. Search by company name (if it contains "buffalo")
    debugLog("5. Testing company name search...");
    try {
        $query5 = "SELECT id, firstName, lastName, email, companyName, phone FROM customer WHERE UPPER(companyName) LIKE '%BUFFALO%'";
        $result5 = $netSuiteService->executeSuiteQLQuery($query5);
        $searchResults['company_search'] = $result5;
        debugLog("Company search result: " . json_encode($result5));
    } catch (Exception $e) {
        debugLog("Company search failed: " . $e->getMessage());
        $searchResults['company_search'] = ['error' => $e->getMessage()];
    }
    
    // 6. Search for any customer with similar first name
    debugLog("6. Testing similar first name search...");
    try {
        $query6 = "SELECT id, firstName, lastName, email, companyName, phone FROM customer WHERE firstName = '$searchFirstName' LIMIT 10";
        $result6 = $netSuiteService->executeSuiteQLQuery($query6);
        $searchResults['firstname_search'] = $result6;
        debugLog("First name search result: " . json_encode($result6));
    } catch (Exception $e) {
        debugLog("First name search failed: " . $e->getMessage());
        $searchResults['firstname_search'] = ['error' => $e->getMessage()];
    }
    
    // 7. Get recent customers to see data format
    debugLog("7. Getting recent customers for data format reference...");
    try {
        $query7 = "SELECT id, firstName, lastName, email, companyName, phone, datecreated FROM customer ORDER BY datecreated DESC LIMIT 5";
        $result7 = $netSuiteService->executeSuiteQLQuery($query7);
        $searchResults['recent_customers'] = $result7;
        debugLog("Recent customers result: " . json_encode($result7));
    } catch (Exception $e) {
        debugLog("Recent customers search failed: " . $e->getMessage());
        $searchResults['recent_customers'] = ['error' => $e->getMessage()];
    }
    
    // Analyze results
    $foundCustomers = [];
    $totalMatches = 0;
    
    foreach ($searchResults as $searchType => $result) {
        if (isset($result['items']) && count($result['items']) > 0) {
            $foundCustomers[$searchType] = $result['items'];
            $totalMatches += count($result['items']);
        }
    }
    
    debugLog("Total potential matches found: $totalMatches");
    
    $response = [
        'success' => true,
        'search_email' => $searchEmail,
        'search_name' => "$searchFirstName $searchLastName",
        'total_matches' => $totalMatches,
        'found_customers' => $foundCustomers,
        'all_search_results' => $searchResults,
        'analysis' => []
    ];
    
    // Add analysis
    if ($totalMatches === 0) {
        $response['analysis'][] = "❌ No customers found with any search criteria";
        $response['analysis'][] = "✅ Safe to create new customer";
    } else {
        $response['analysis'][] = "⚠️ Found $totalMatches potential matches";
        $response['analysis'][] = "🔍 Review matches to see if any are duplicates";
        
        foreach ($foundCustomers as $searchType => $customers) {
            $response['analysis'][] = "📋 $searchType: " . count($customers) . " matches";
        }
    }
    
    debugLog("Response prepared with analysis");
    
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

debugLog("=== COMPREHENSIVE CUSTOMER SEARCH END ===");
?>