<?php

namespace Laguna\Integration\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Laguna\Integration\Utils\Logger;
use Laguna\Integration\Utils\NetSuiteEnvironmentManager;

/**
 * NetSuite REST API Service
 * 
 * Handles all interactions with the NetSuite SuiteTalk REST API using OAuth 1.0 authentication.
 * Supports both HMAC-SHA256 (default, recommended) and HMAC-SHA1 (legacy) signature methods.
 * Documentation: https://docs.oracle.com/en/cloud/saas/netsuite/ns-online-help/section_1529089601.html
 */
class NetSuiteService {
    private $client;
    private $credentials;
    private $config;
    private $logger;
    private $baseUrl;
    private $environmentManager;
    
    public function __construct() {
        $credentials = require __DIR__ . '/../../config/credentials.php';
        $this->config = require __DIR__ . '/../../config/config.php';
        $this->credentials = $credentials['netsuite'];
        $this->logger = Logger::getInstance();
        $this->environmentManager = NetSuiteEnvironmentManager::getInstance();
        
        // Log environment information on initialization
        $envInfo = $this->environmentManager->getEnvironmentInfo();
        $this->logger->info('NetSuite Service initialized', [
            'environment' => $envInfo['environment'],
            'account_id' => $envInfo['account_id'],
            'base_url' => $envInfo['base_url'],
            'is_production' => $envInfo['is_production']
        ]);
        
        $this->baseUrl = rtrim($this->credentials['base_url'], '/') . '/services/rest/record/' . $this->credentials['rest_api_version'];
        
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 60,
            'verify' => false, // Disable SSL verification for development - CHANGE IN PRODUCTION
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]
        ]);
    }
    
    /**
     * Generate OAuth 1.0 signature for NetSuite API
     * Supports both HMAC-SHA256 (default) and HMAC-SHA1 (legacy) signature methods
     */
    private function generateOAuthHeader($method, $url, $params = []) {
        // Get signature method from config, default to HMAC-SHA256
        $signatureMethod = $this->credentials['signature_method'] ?? 'HMAC-SHA256';
        
        // Validate signature method
        if (!in_array($signatureMethod, ['HMAC-SHA256', 'HMAC-SHA1'])) {
            $signatureMethod = 'HMAC-SHA256';
            $this->logger->warning('Invalid signature method in config, defaulting to HMAC-SHA256', [
                'provided_method' => $this->credentials['signature_method'] ?? 'none'
            ]);
        }
        
        $oauthParams = [
            'oauth_consumer_key' => $this->credentials['consumer_key'],
            'oauth_token' => $this->credentials['token_id'],
            'oauth_signature_method' => $signatureMethod,
            'oauth_timestamp' => time(),
            'oauth_nonce' => bin2hex(random_bytes(16)),
            'oauth_version' => '1.0'
        ];
        
        // Merge OAuth params with request params (excluding realm from signature)
        $allParams = array_merge($oauthParams, $params);
        ksort($allParams);
        
        // Create parameter string
        $paramString = http_build_query($allParams, '', '&', PHP_QUERY_RFC3986);
        
        // Create signature base string
        $baseString = strtoupper($method) . '&' . rawurlencode($url) . '&' . rawurlencode($paramString);
        
        // Create signing key
        $signingKey = rawurlencode($this->credentials['consumer_secret']) . '&' . rawurlencode($this->credentials['token_secret']);
        
        // Generate signature based on method
        if ($signatureMethod === 'HMAC-SHA256') {
            $signature = base64_encode(hash_hmac('sha256', $baseString, $signingKey, true));
        } else {
            $signature = base64_encode(hash_hmac('sha1', $baseString, $signingKey, true));
        }
        
        $oauthParams['oauth_signature'] = $signature;
        
        // Build authorization header with realm
        $authHeader = 'OAuth realm="' . $this->credentials['account_id'] . '"';
        foreach ($oauthParams as $key => $value) {
            $authHeader .= ', ' . $key . '="' . rawurlencode($value) . '"';
        }
        
        return $authHeader;
    }
    
    /**
     * Make authenticated request to NetSuite
     */
    private function makeRequest($method, $endpoint, $data = null, $params = []) {
        // Handle SuiteQL endpoints differently
        if (strpos($endpoint, '/query/v1/suiteql') === 0) {
            // SuiteQL uses a different base path
            $suiteQLBaseUrl = rtrim($this->credentials['base_url'], '/') . '/services/rest';
            $fullUrl = $suiteQLBaseUrl . $endpoint;
        } else {
            // Regular REST API endpoints
            $fullUrl = $this->baseUrl . $endpoint;
        }
        
        $authHeader = $this->generateOAuthHeader($method, $fullUrl, $params);
        
        $options = [
            'headers' => [
                'Authorization' => $authHeader,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]
        ];
        
        // Add Prefer header for SuiteQL queries
        if (strpos($endpoint, '/query/v1/suiteql') === 0) {
            $options['headers']['Prefer'] = 'transient';
        }
        
        if ($data) {
            $options['json'] = $data;
        }
        
        if ($params) {
            $options['query'] = $params;
        }
        
        // Use the full URL instead of relying on base_uri
        return $this->client->request($method, $fullUrl, $options);
    }
    
    /**
     * Get the current signature method being used
     */
    public function getSignatureMethod() {
        return $this->credentials['signature_method'] ?? 'HMAC-SHA256';
    }
    
    /**
     * Get current NetSuite environment information
     */
    public function getEnvironmentInfo() {
        return $this->environmentManager->getEnvironmentInfo();
    }
    
    /**
     * Check if currently running in production environment
     */
    public function isProduction() {
        return $this->environmentManager->isProduction();
    }
    
    /**
     * Check if currently running in sandbox environment
     */
    public function isSandbox() {
        return $this->environmentManager->isSandbox();
    }
    
    /**
     * Get environment validation status
     */
    public function validateEnvironment() {
        return $this->environmentManager->validateEnvironment();
    }
    
    /**
     * Test connection to NetSuite API
     */
    public function testConnection() {
        try {
            $startTime = microtime(true);
            $response = $this->makeRequest('GET', '/customer', null, ['limit' => 1]);
            $duration = (microtime(true) - $startTime) * 1000;
            
            $this->logger->logApiCall('NetSuite', '/customer', 'GET', $response->getStatusCode(), $duration);
            
            return [
                'success' => true,
                'status_code' => $response->getStatusCode(),
                'response_time' => round($duration, 2) . 'ms'
            ];
        } catch (RequestException $e) {
            $this->logger->error('NetSuite connection test failed', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            
            $responseBody = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : 'No response body';
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'status_code' => $e->getResponse() ? $e->getResponse()->getStatusCode() : null,
                'details' => [
                    'error' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'response_body' => $responseBody
                ]
            ];
        }
    }
    
    /**
     * Get full customer record by ID including addresses
     */
    public function getCustomerById($customerId) {
        try {
            $this->logger->info('Retrieving full customer record by ID', ['customer_id' => $customerId]);
            
            $response = $this->makeRequest('GET', "/customer/{$customerId}");
            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            
            if ($statusCode === 200) {
                $customer = json_decode($responseBody, true);
                
                $this->logger->info('Retrieved full customer record', [
                    'customer_id' => $customerId,
                    'has_default_address' => !empty($customer['defaultAddress']),
                    'has_addressbook' => !empty($customer['addressbook']),
                    'addressbook_items' => count($customer['addressbook']['items'] ?? [])
                ]);
                
                return $customer;
            } else {
                $this->logger->warning('Failed to retrieve customer by ID', [
                    'customer_id' => $customerId,
                    'status_code' => $statusCode,
                    'response' => $responseBody
                ]);
                return null;
            }
        } catch (Exception $e) {
            $this->logger->error('Error retrieving customer by ID', [
                'customer_id' => $customerId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Search for customer by email using enhanced SuiteQL with multiple search strategies
     */
    public function findCustomerByEmail($email, $includeAddresses = false) {
        try {
            $startTime = microtime(true);
            
            // Strategy 1: Direct email match using SuiteQL (case-insensitive)
            $this->logger->info('Searching for customer by email (case-insensitive)', ['email' => $email]);
            
            $suiteQLQuery = "SELECT id, firstName, lastName, email, companyName, phone, isperson FROM customer WHERE LOWER(email) = LOWER('" . $email . "')";
            $result = $this->executeSuiteQLQuery($suiteQLQuery);
            
            if (isset($result['items']) && count($result['items']) > 0) {
                $customer = $result['items'][0];
                $this->logger->info('Found customer by direct email match', [
                    'email' => $email,
                    'customer_id' => $customer['id'],
                    'customer_name' => ($customer['firstName'] ?? '') . ' ' . ($customer['lastName'] ?? ''),
                    'company' => $customer['companyName'] ?? null
                ]);
                
                // If addresses are requested, get the full customer record
                if ($includeAddresses) {
                    $fullCustomer = $this->getCustomerById($customer['id']);
                    return $fullCustomer ?: $customer; // Fallback to basic customer if full record fails
                }
                
                return $customer;
            }
            
            // Strategy 2: Alternative case-insensitive email match (fallback)
            $this->logger->info('Searching for customer by email (alternative case-insensitive)', ['email' => $email]);
            
            $suiteQLQuery2 = "SELECT id, firstName, lastName, email, companyName, phone, isperson FROM customer WHERE LOWER(email) = '" . strtolower($email) . "'";
            $result2 = $this->executeSuiteQLQuery($suiteQLQuery2);
            
            if (isset($result2['items']) && count($result2['items']) > 0) {
                $customer = $result2['items'][0];
                $this->logger->info('Found customer by case-insensitive email match', [
                    'email' => $email,
                    'customer_id' => $customer['id'],
                    'customer_name' => ($customer['firstName'] ?? '') . ' ' . ($customer['lastName'] ?? ''),
                    'company' => $customer['companyName'] ?? null
                ]);
                
                // If addresses are requested, get the full customer record
                if ($includeAddresses) {
                    $fullCustomer = $this->getCustomerById($customer['id']);
                    return $fullCustomer ?: $customer; // Fallback to basic customer if full record fails
                }
                
                return $customer;
            }
            
            // Strategy 3: Search for customers associated with company that might have this email domain
            $emailDomain = substr($email, strpos($email, '@') + 1);
            $this->logger->info('Searching for customers by company domain', ['domain' => $emailDomain]);
            
            // Extract potential company name from domain (e.g., buffalowoodturningproducts.com -> Buffalo Wood Turning Products)
            $domainParts = explode('.', $emailDomain);
            if (count($domainParts) > 0) {
                $companyKeyword = $domainParts[0]; // e.g., "buffalowoodturningproducts"
                
                // Search for customers with company names containing this keyword (case-insensitive)
                $suiteQLQuery3 = "SELECT id, firstName, lastName, email, companyName, phone, isperson FROM customer WHERE LOWER(companyName) LIKE '%" . strtolower($companyKeyword) . "%' AND LOWER(email) = LOWER('" . $email . "')";
                $result3 = $this->executeSuiteQLQuery($suiteQLQuery3);
                
                if (isset($result3['items']) && count($result3['items']) > 0) {
                    $customer = $result3['items'][0];
                    $this->logger->info('Found customer by company association', [
                        'email' => $email,
                        'customer_id' => $customer['id'],
                        'customer_name' => ($customer['firstName'] ?? '') . ' ' . ($customer['lastName'] ?? ''),
                        'company' => $customer['companyName'] ?? null,
                        'search_keyword' => $companyKeyword
                    ]);
                    
                    // If addresses are requested, get the full customer record
                    if ($includeAddresses) {
                        $fullCustomer = $this->getCustomerById($customer['id']);
                        return $fullCustomer ?: $customer; // Fallback to basic customer if full record fails
                    }
                    
                    return $customer;
                }
            }
            
            $duration = (microtime(true) - $startTime) * 1000;
            $this->logger->info('Customer not found by any email search strategy', [
                'email' => $email,
                'search_duration_ms' => $duration
            ]);
            return null;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to search for customer by email in NetSuite', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            throw new \Exception("Failed to search for customer by email: " . $e->getMessage());
        }
    }

    /**
     * Search for customer by phone number
     */
    public function findCustomerByPhone($phone) {
        try {
            $startTime = microtime(true);
            
            // Use IS operator for exact phone match
            $query = 'phone IS "' . $phone . '"';
            
            $response = $this->makeRequest('GET', '/customer', null, [
                'q' => $query
            ]);
            
            $duration = (microtime(true) - $startTime) * 1000;
            $this->logger->logApiCall('NetSuite', '/customer', 'GET', $response->getStatusCode(), $duration);
            
            $data = json_decode($response->getBody()->getContents(), true);
            
            if (isset($data['items']) && count($data['items']) > 0) {
                $customer = $data['items'][0];
                $this->logger->info('Found existing customer by phone in NetSuite', [
                    'phone' => $phone,
                    'customer_id' => $customer['id'],
                    'total_matches' => count($data['items'])
                ]);
                return $customer;
            }
            
            $this->logger->info('Customer not found by phone in NetSuite', ['phone' => $phone]);
            return null;
            
        } catch (RequestException $e) {
            $this->logger->error('Failed to search for customer by phone in NetSuite', [
                'phone' => $phone,
                'error' => $e->getMessage(),
                'response_body' => $e->getResponse() ? $e->getResponse()->getBody()->getContents() : 'No response'
            ]);
            throw new \Exception("Failed to search for customer by phone: " . $e->getMessage());
        }
    }

    /**
     * Ensure customer is a person (isPerson = true) for sales order assignment
     * If customer is a company (isPerson = false), search for or create a person customer
     */
    public function ensurePersonCustomer($customer, $orderData = []) {
        try {
            // Check if customer is already a person
            $isPerson = $customer['isperson'] ?? false;
            
            // Handle different representations of isPerson
            if ($isPerson === true || $isPerson === 'T' || $isPerson === 't' || $isPerson === 1 || $isPerson === '1') {
                $this->logger->info('Customer is already a person - using as-is', [
                    'customer_id' => $customer['id'],
                    'isPerson' => $isPerson,
                    'customer_name' => ($customer['firstName'] ?? '') . ' ' . ($customer['lastName'] ?? '')
                ]);
                return $customer;
            }
            
            // Customer is a company - need to find or create a person customer
            $this->logger->info('Customer is a company - searching for person customer', [
                'company_customer_id' => $customer['id'],
                'company_name' => $customer['companyName'] ?? 'N/A',
                'isPerson' => $isPerson
            ]);
            
            // Extract names from order data
            $firstName = $orderData['BillingFirstName'] ?? $orderData['firstName'] ?? '';
            $lastName = $orderData['BillingLastName'] ?? $orderData['lastName'] ?? '';
            
            if (empty($firstName) && empty($lastName)) {
                $this->logger->warning('No first/last name available for person customer search', [
                    'company_customer_id' => $customer['id'],
                    'order_data_keys' => array_keys($orderData)
                ]);
                // Fallback: use the company customer as-is
                return $customer;
            }
            
            // Search for person customer using entityid = "firstName lastName" and parent = company customer ID
            $entityId = trim($firstName . ' ' . $lastName);
            $companyId = $customer['id'];
            
            $this->logger->info('Searching for person customer by entityid and parent', [
                'entityid' => $entityId,
                'parent_id' => $companyId
            ]);
            
            $suiteQLQuery = "SELECT id, firstName, lastName, email, companyName, phone, isperson FROM customer WHERE LOWER(entityid) = LOWER('" . $entityId . "') AND parent = " . $companyId;
            $result = $this->executeSuiteQLQuery($suiteQLQuery);
            
            if (isset($result['items']) && count($result['items']) > 0) {
                $personCustomer = $result['items'][0];
                $this->logger->info('Found existing person customer', [
                    'person_customer_id' => $personCustomer['id'],
                    'entityid' => $entityId,
                    'parent_id' => $companyId,
                    'isPerson' => $personCustomer['isperson'] ?? 'N/A'
                ]);
                return $personCustomer;
            }
            
            // Person customer not found - create new one
            $this->logger->info('Person customer not found - creating new person customer', [
                'firstName' => $firstName,
                'lastName' => $lastName,
                'parent_id' => $companyId,
                'note' => 'Will use ShipmentList names for person customer creation'
            ]);
            
            // Extract customer details according to requirements:
            // - email: from QuestionList (QuestionID=1) 
            // - firstName/lastName: from ShipmentList
            $customerEmail = $customer['email'] ?? '';
            $shipmentFirstName = '';
            $shipmentLastName = '';
            
            // Extract first name and last name from ShipmentList
            if (!empty($orderData['ShipmentList']) && is_array($orderData['ShipmentList'])) {
                $firstShipment = $orderData['ShipmentList'][0];
                $shipmentFirstName = $firstShipment['ShipmentFirstName'] ?? '';
                $shipmentLastName = $firstShipment['ShipmentLastName'] ?? '';
            }
            
            $personCustomerData = [
                'firstName' => $shipmentFirstName,
                'lastName' => $shipmentLastName,
                'email' => $customerEmail,
                'phone' => $customer['phone'] ?? ($orderData['BillingPhoneNumber'] ?? ''),
                'isPerson' => true
            ];
            
            $this->logger->info('Extracted person customer details from ShipmentList', [
                'shipment_firstName' => $shipmentFirstName,
                'shipment_lastName' => $shipmentLastName,
                'customer_email' => $customerEmail,
                'billing_firstName' => $firstName,
                'billing_lastName' => $lastName,
                'note' => 'Using ShipmentList names instead of billing names for person customer'
            ]);
            
            // Add billing address fields for address creation if available in orderData
            if (!empty($orderData)) {
                $addressFields = [
                    'BillingFirstName', 'BillingLastName', 'BillingCompany', 'BillingAddress', 
                    'BillingAddress2', 'BillingCity', 'BillingState', 'BillingZipCode', 
                    'BillingCountry', 'BillingPhoneNumber', 'ShipmentList'
                ];
                
                foreach ($addressFields as $field) {
                    if (isset($orderData[$field])) {
                        $personCustomerData[$field] = $orderData[$field];
                    }
                }
                
                // Add second email for custentity2nd_email_address field
                $personCustomerData['second_email'] = $personCustomerData['email'];
                
                $this->logger->info('Added address fields to person customer data', [
                    'person_customer_email' => $personCustomerData['email'],
                    'has_billing_address' => !empty($orderData['BillingAddress']),
                    'has_shipment_list' => !empty($orderData['ShipmentList']),
                    'billing_company' => $orderData['BillingCompany'] ?? 'N/A'
                ]);
            }
            
            // Create the person customer with parent ID as separate parameter
            $createdPersonCustomer = $this->createCustomer($personCustomerData, $companyId);
            
            $this->logger->info('Created new person customer', [
                'person_customer_id' => $createdPersonCustomer['id'],
                'firstName' => $firstName,
                'lastName' => $lastName,
                'parent_id' => $companyId,
                'email' => $personCustomerData['email']
            ]);
            
            return $createdPersonCustomer;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to ensure person customer', [
                'customer_id' => $customer['id'] ?? 'N/A',
                'error' => $e->getMessage()
            ]);
            
            // Fallback: return original customer to avoid blocking order creation
            $this->logger->warning('Falling back to original customer due to error', [
                'customer_id' => $customer['id'] ?? 'N/A'
            ]);
            return $customer;
        }
    }

    /**
     * Find or create customer based on BillingPaymentMethod logic
     * Implements the new customer assignment logic based on payment method
     */
    public function findOrCreateCustomerByPaymentMethod($orderData) {
        try {
            $billingPaymentMethod = $orderData['BillingPaymentMethod'] ?? '';
            $this->logger->info('Starting customer assignment by payment method', [
                'order_id' => $orderData['OrderID'] ?? 'N/A',
                'payment_method' => $billingPaymentMethod
            ]);

            // Extract customer email from QuestionList (QuestionID=1)
            $customerEmail = $this->extractCustomerEmailFromQuestionList($orderData);
            $isValidEmail = !empty($customerEmail) && filter_var($customerEmail, FILTER_VALIDATE_EMAIL);

            if ($billingPaymentMethod === 'Dropship to Customer') {
                return $this->handleDropshipCustomer($orderData, $customerEmail, $isValidEmail);
            } else {
                return $this->handleRegularCustomer($orderData, $customerEmail, $isValidEmail);
            }

        } catch (\Exception $e) {
            $this->logger->error('Failed to find or create customer by payment method', [
                'order_id' => $orderData['OrderID'] ?? 'N/A',
                'payment_method' => $orderData['BillingPaymentMethod'] ?? 'N/A',
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Extract customer email from QuestionList where QuestionID = 1
     */
    private function extractCustomerEmailFromQuestionList($orderData) {
        if (!isset($orderData['QuestionList']) || !is_array($orderData['QuestionList'])) {
            return '';
        }

        foreach ($orderData['QuestionList'] as $question) {
            if (isset($question['QuestionID']) && $question['QuestionID'] == 1) {
                return trim($question['QuestionAnswer'] ?? '');
            }
        }

        return '';
    }

    /**
     * Handle dropship customer logic
     */
    private function handleDropshipCustomer($orderData, $customerEmail, $isValidEmail) {
        $this->logger->info('Processing dropship customer', [
            'order_id' => $orderData['OrderID'] ?? 'N/A',
            'customer_email' => $customerEmail,
            'is_valid_email' => $isValidEmail,
            'note' => 'Dropship customers created without email address'
        ]);

        // Search for parent company using parentCompanyQuery
        $parentCustomer = $this->findParentCompanyCustomer($orderData);
        $parentCustomerId = $parentCustomer ? $parentCustomer['id'] : null;

        if ($parentCustomer) {
            $this->logger->info('Found parent company for dropship customer', [
                'parent_customer_id' => $parentCustomerId,
                'parent_email' => $parentCustomer['email'] ?? 'N/A'
            ]);
        } else {
            $this->logger->info('No parent company found for dropship customer');
        }

        // Build customer data to get the expected name format
        $customerData = $this->buildDropshipCustomerData($orderData, $customerEmail, $isValidEmail, $parentCustomerId);
        
        // Check if dropship customer already exists
        $existingCustomer = $this->findExistingDropshipCustomer($customerData, $parentCustomerId);
        if ($existingCustomer) {
            $this->logger->info('Found existing dropship customer', [
                'customer_id' => $existingCustomer['id'],
                'firstName' => $existingCustomer['firstName'] ?? 'N/A',
                'lastName' => $existingCustomer['lastName'] ?? 'N/A',
                'parent_id' => $parentCustomerId
            ]);
            return $existingCustomer['id'];
        }

        // Create new dropship customer
        $newCustomer = $this->createCustomer($customerData, $parentCustomerId);
        return $newCustomer['id'];
    }

    /**
     * Handle regular (non-dropship) customer logic
     */
    private function handleRegularCustomer($orderData, $customerEmail, $isValidEmail) {
        $this->logger->info('Processing regular customer', [
            'order_id' => $orderData['OrderID'] ?? 'N/A',
            'customer_email' => $customerEmail,
            'is_valid_email' => $isValidEmail
        ]);

        // If valid email, search for existing store customer
        if ($isValidEmail) {
            $existingCustomer = $this->findStoreCustomer($customerEmail);
            if ($existingCustomer) {
                $this->logger->info('Found existing store customer', [
                    'customer_id' => $existingCustomer['id'],
                    'customer_email' => $customerEmail
                ]);
                return $existingCustomer['id'];
            }
        }

        // Customer not found or invalid email - create new customer
        $parentCustomer = $this->findParentCompanyCustomer($orderData);
        $parentCustomerId = $parentCustomer ? $parentCustomer['id'] : null;

        $customerData = $this->buildRegularCustomerData($orderData, $customerEmail, $isValidEmail, $parentCustomerId);
        $newCustomer = $this->createCustomer($customerData, $parentCustomerId);
        
        return $newCustomer['id'];
    }

    /**
     * Find parent company customer using billing email and phone
     * SELECT * FROM customer WHERE (email = {BillingEmail} or phone={BillingPhoneNumber}) and isperson='F'
     */
    private function findParentCompanyCustomer($orderData) {
        try {
            $billingEmail = $orderData['BillingEmail'] ?? '';
            $billingPhone = $orderData['BillingPhoneNumber'] ?? '';

            if (empty($billingEmail) && empty($billingPhone)) {
                return null;
            }

            $this->logger->info('Searching for parent company customer', [
                'billing_email' => $billingEmail,
                'billing_phone' => $billingPhone
            ]);

            // Build SuiteQL query with proper escaping (case-insensitive)
            $conditions = [];
            if (!empty($billingEmail)) {
                $escapedEmail = str_replace("'", "''", $billingEmail);
                $conditions[] = "LOWER(email) = LOWER('" . $escapedEmail . "')";
            }
            if (!empty($billingPhone)) {
                $escapedPhone = str_replace("'", "''", $billingPhone);
                $conditions[] = "phone = '" . $escapedPhone . "'";
            }

            $query = "SELECT id, firstName, lastName, email, companyName, phone, isperson FROM customer WHERE (" . 
                     implode(' OR ', $conditions) . ") AND isperson = 'F'";

            $result = $this->executeSuiteQLQuery($query);

            if (isset($result['items']) && count($result['items']) > 0) {
                $customer = $result['items'][0];
                $this->logger->info('Found parent company customer', [
                    'customer_id' => $customer['id'],
                    'company_name' => $customer['companyName'] ?? 'N/A',
                    'email' => $customer['email'] ?? 'N/A'
                ]);
                return $customer;
            }

            $this->logger->info('No parent company customer found');
            return null;

        } catch (\Exception $e) {
            $this->logger->error('Error searching for parent company customer', [
                'error' => $e->getMessage(),
                'billing_email' => $orderData['BillingEmail'] ?? 'N/A',
                'billing_phone' => $orderData['BillingPhoneNumber'] ?? 'N/A'
            ]);
            return null;
        }
    }

    /**
     * Find store customer using email
     * SELECT * FROM customer WHERE email = {QuestionList->QuestionAnswer} and isperson='F'
     */
    private function findStoreCustomer($email) {
        try {
            $this->logger->info('Searching for store customer', ['email' => $email]);

            // Escape single quotes in email for SQL safety (case-insensitive)
            $escapedEmail = str_replace("'", "''", $email);
            $query = "SELECT id, firstName, lastName, email, companyName, phone, isperson FROM customer WHERE LOWER(email) = LOWER('" . $escapedEmail . "') AND isperson = 'F'";
            $result = $this->executeSuiteQLQuery($query);

            if (isset($result['items']) && count($result['items']) > 0) {
                $customer = $result['items'][0];
                $this->logger->info('Found store customer', [
                    'customer_id' => $customer['id'],
                    'email' => $customer['email']
                ]);
                return $customer;
            }

            $this->logger->info('No store customer found', ['email' => $email]);
            return null;

        } catch (\Exception $e) {
            $this->logger->error('Error searching for store customer', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Find existing dropship customer by firstName, lastName, and parent
     * Dropship customers are person customers with specific naming pattern
     */
    private function findExistingDropshipCustomer($customerData, $parentCustomerId) {
        try {
            $firstName = $customerData['firstname'] ?? '';
            $lastName = $customerData['lastName'] ?? '';
            
            if (empty($firstName) && empty($lastName)) {
                $this->logger->info('Skipping dropship customer search - no name provided');
                return null;
            }

            $this->logger->info('Searching for existing dropship customer', [
                'firstName' => $firstName,
                'lastName' => $lastName,
                'parent_id' => $parentCustomerId
            ]);

            // Build query conditions (case-insensitive for names)
            $conditions = [];
            if (!empty($firstName)) {
                $escapedFirstName = str_replace("'", "''", $firstName);
                $conditions[] = "LOWER(firstName) = LOWER('" . $escapedFirstName . "')";
            }
            if (!empty($lastName)) {
                $escapedLastName = str_replace("'", "''", $lastName);
                $conditions[] = "LOWER(lastName) = LOWER('" . $escapedLastName . "')";
            }

            // Add parent condition if provided
            $parentCondition = '';
            if ($parentCustomerId) {
                $parentCondition = " AND parent = " . intval($parentCustomerId);
            }

            // Search for person customers (dropship customers are always persons)
            $query = "SELECT id, firstName, lastName, email, companyName, phone, isperson, parent FROM customer WHERE " . 
                     implode(' AND ', $conditions) . " AND isperson = 'T'" . $parentCondition;

            $result = $this->executeSuiteQLQuery($query);

            if (isset($result['items']) && count($result['items']) > 0) {
                $customer = $result['items'][0];
                $this->logger->info('Found existing dropship customer', [
                    'customer_id' => $customer['id'],
                    'firstName' => $customer['firstName'] ?? 'N/A',
                    'lastName' => $customer['lastName'] ?? 'N/A',
                    'parent' => $customer['parent'] ?? 'N/A'
                ]);
                return $customer;
            }

            $this->logger->info('No existing dropship customer found', [
                'firstName' => $firstName,
                'lastName' => $lastName,
                'parent_id' => $parentCustomerId
            ]);
            return null;

        } catch (\Exception $e) {
            $this->logger->error('Error searching for existing dropship customer', [
                'firstName' => $customerData['firstname'] ?? 'N/A',
                'lastName' => $customerData['lastName'] ?? 'N/A',
                'parent_id' => $parentCustomerId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Build customer data for dropship orders
     */
    private function buildDropshipCustomerData($orderData, $customerEmail, $isValidEmail, $parentCustomerId) {
        $shipment = isset($orderData['ShipmentList'][0]) ? $orderData['ShipmentList'][0] : [];
        
        $firstName = $shipment['ShipmentFirstName'] ?? '';
        $lastName = $shipment['ShipmentLastName'] ?? '';
        
        // Add invoice prefix and number to lastname
        $invoicePrefix = $orderData['InvoiceNumberPrefix'] ?? '';
        $invoiceNumber = $orderData['InvoiceNumber'] ?? '';
        if (!empty($invoicePrefix) || !empty($invoiceNumber)) {
            $lastName .= ': ' . $invoicePrefix . $invoiceNumber;
        }

        $customerData = [
            'firstname' => $firstName,
            'lastName' => $lastName,
            'isPerson' => true, // Always true for dropship
            'email' => '', // Always empty for dropship customers
            'phone' => $shipment['ShipmentPhone'] ?? '',
            'company' => '', // No company for dropship person customers
            
            // Add billing and shipping data for address creation
            'BillingFirstName' => $orderData['BillingFirstName'] ?? '',
            'BillingLastName' => $orderData['BillingLastName'] ?? '',
            'BillingCompany' => $orderData['BillingCompany'] ?? '',
            'BillingAddress' => $orderData['BillingAddress'] ?? '',
            'BillingAddress2' => $orderData['BillingAddress2'] ?? '',
            'BillingCity' => $orderData['BillingCity'] ?? '',
            'BillingState' => $orderData['BillingState'] ?? '',
            'BillingZipCode' => $orderData['BillingZipCode'] ?? '',
            'BillingCountry' => $orderData['BillingCountry'] ?? 'US',
            'BillingPhoneNumber' => $orderData['BillingPhoneNumber'] ?? '',
            'BillingEmail' => $orderData['BillingEmail'] ?? '',
            'ShipmentList' => $orderData['ShipmentList'] ?? []
        ];

        $this->logger->info('Built dropship customer data', [
            'firstname' => $firstName,
            'lastname' => $lastName,
            'email' => $customerData['email'],
            'has_parent' => !empty($parentCustomerId),
            'note' => 'Email always empty for dropship customers'
        ]);

        return $customerData;
    }

    /**
     * Build customer data for regular orders
     */
    private function buildRegularCustomerData($orderData, $customerEmail, $isValidEmail, $parentCustomerId) {
        $shipment = isset($orderData['ShipmentList'][0]) ? $orderData['ShipmentList'][0] : [];
        
        // For company customers, use company name or create one from shipment info
        $companyName = $orderData['BillingCompany'] ?? '';
        if (empty($companyName)) {
            $companyName = trim(($shipment['ShipmentFirstName'] ?? '') . ' ' . ($shipment['ShipmentLastName'] ?? ''));
        }
        
        $customerData = [
            'firstname' => '', // Empty for company customers
            'lastName' => '', // Empty for company customers  
            'isPerson' => false, // Always false for regular customers (company)
            'email' => $isValidEmail ? $customerEmail : '',
            'phone' => $shipment['ShipmentPhone'] ?? '',
            'company' => $companyName,
            
            // Add billing and shipping data for address creation
            'BillingFirstName' => $orderData['BillingFirstName'] ?? '',
            'BillingLastName' => $orderData['BillingLastName'] ?? '',
            'BillingCompany' => $orderData['BillingCompany'] ?? '',
            'BillingAddress' => $orderData['BillingAddress'] ?? '',
            'BillingAddress2' => $orderData['BillingAddress2'] ?? '',
            'BillingCity' => $orderData['BillingCity'] ?? '',
            'BillingState' => $orderData['BillingState'] ?? '',
            'BillingZipCode' => $orderData['BillingZipCode'] ?? '',
            'BillingCountry' => $orderData['BillingCountry'] ?? 'US',
            'BillingPhoneNumber' => $orderData['BillingPhoneNumber'] ?? '',
            'BillingEmail' => $orderData['BillingEmail'] ?? '',
            'ShipmentList' => $orderData['ShipmentList'] ?? []
        ];

        $this->logger->info('Built regular customer data', [
            'company' => $customerData['company'],
            'email' => $customerData['email'],
            'phone' => $customerData['phone'],
            'has_parent' => !empty($parentCustomerId)
        ]);

        return $customerData;
    }

    /**
     * Enhanced parent customer search using email and phone
     * First searches by email, if no record found or multiple records returned, uses phone to identify correct record
     */
    public function findParentCustomer($email, $phone = null) {
        try {
            $this->logger->info('Starting parent customer search', [
                'email' => $email,
                'phone' => $phone
            ]);

            // Step 1: Search by email
            $emailResults = [];
            if ($email) {
                $startTime = microtime(true);
                $query = 'email IS "' . $email . '"';
                
                $response = $this->makeRequest('GET', '/customer', null, [
                    'q' => $query
                ]);
                
                $duration = (microtime(true) - $startTime) * 1000;
                $this->logger->logApiCall('NetSuite', '/customer', 'GET', $response->getStatusCode(), $duration);
                
                $data = json_decode($response->getBody()->getContents(), true);
                
                if (isset($data['items'])) {
                    $emailResults = $data['items'];
                }
            }

            // If exactly one email match found, return it
            if (count($emailResults) === 1) {
                $customer = $emailResults[0];
                $this->logger->info('Found unique parent customer by email', [
                    'email' => $email,
                    'customer_id' => $customer['id']
                ]);
                return $customer;
            }

            // If no email results and phone provided, search by phone
            if (count($emailResults) === 0 && $phone) {
                $phoneCustomer = $this->findCustomerByPhone($phone);
                if ($phoneCustomer) {
                    $this->logger->info('Found parent customer by phone (no email match)', [
                        'phone' => $phone,
                        'customer_id' => $phoneCustomer['id']
                    ]);
                    return $phoneCustomer;
                }
            }

            // If multiple email results and phone provided, use phone to identify correct record
            if (count($emailResults) > 1 && $phone) {
                foreach ($emailResults as $customer) {
                    $customerPhone = $customer['phone'] ?? '';
                    if ($customerPhone === $phone) {
                        $this->logger->info('Found parent customer by email+phone match', [
                            'email' => $email,
                            'phone' => $phone,
                            'customer_id' => $customer['id'],
                            'total_email_matches' => count($emailResults)
                        ]);
                        return $customer;
                    }
                }
                
                // If phone doesn't match any email results, return first email result
                $customer = $emailResults[0];
                $this->logger->warning('Multiple email matches, phone mismatch - using first email result', [
                    'email' => $email,
                    'phone' => $phone,
                    'customer_id' => $customer['id'],
                    'total_email_matches' => count($emailResults)
                ]);
                return $customer;
            }

            // If multiple email results but no phone, return first result
            if (count($emailResults) > 1) {
                $customer = $emailResults[0];
                $this->logger->warning('Multiple email matches, no phone provided - using first result', [
                    'email' => $email,
                    'customer_id' => $customer['id'],
                    'total_email_matches' => count($emailResults)
                ]);
                return $customer;
            }

            // No customer found
            $this->logger->info('No parent customer found', [
                'email' => $email,
                'phone' => $phone
            ]);
            return null;

        } catch (RequestException $e) {
            $this->logger->error('Failed to search for parent customer', [
                'email' => $email,
                'phone' => $phone,
                'error' => $e->getMessage()
            ]);
            throw new \Exception("Failed to search for parent customer: " . $e->getMessage());
        }
    }
    
    /**
     * Get sales order by internal ID
     */
    public function getSalesOrderById($orderId) {
        try {
            $startTime = microtime(true);
            
            $response = $this->makeRequest('GET', '/salesOrder/' . $orderId);
            
            $duration = (microtime(true) - $startTime) * 1000;
            $this->logger->logApiCall('NetSuite', '/salesOrder/' . $orderId, 'GET', $response->getStatusCode(), $duration);
            
            $data = json_decode($response->getBody()->getContents(), true);
            
            if ($data) {
                $this->logger->info('Found sales order by internal ID', [
                    'internal_id' => $orderId,
                    'tranid' => $data['tranid'] ?? 'N/A'
                ]);
                return $data;
            }
            
            return null;
            
        } catch (RequestException $e) {
            if ($e->getResponse() && $e->getResponse()->getStatusCode() === 404) {
                $this->logger->info('Sales order not found by internal ID', ['internal_id' => $orderId]);
                return null;
            }
            
            $this->logger->error('Failed to get sales order by internal ID', [
                'internal_id' => $orderId,
                'error' => $e->getMessage()
            ]);
            throw new \Exception("Failed to get sales order by internal ID: " . $e->getMessage());
        }
    }

    /**
     * Create customer in NetSuite with enhanced 3DCart integration support
     */
    public function createCustomer($customerData, $parentCustomerId = null) {
        try {
            // Map 3DCart customer data to NetSuite format with validation
            $netsuiteCustomer = [
                'companyName' => $this->validateAndTruncateField($customerData['company'] ?? $customerData['companyName'] ?? null, 83, 'companyName'),
                'firstName' => $this->validateAndTruncateField($customerData['firstname'] ?? $customerData['firstName'] ?? '', 32, 'firstName'),
                'lastName' => $this->validateAndTruncateField($customerData['lastname'] ?? $customerData['lastName'] ?? '', 32, 'lastName'),
                'email' => $this->validateEmailField($customerData['email'] ?? ''),
                'phone' => $this->validateAndTruncateField($customerData['phone'] ?? null, 22, 'phone'),
                'isPerson' => $customerData['isPerson'] ?? true,
                'subsidiary' => ['id' => $this->config['netsuite']['default_subsidiary_id']],
            ];

            // Add parent customer if provided
            if ($parentCustomerId) {
                $netsuiteCustomer['parent'] = ['id' => (int)$parentCustomerId];
                $this->logger->info('Setting parent customer for new customer', [
                    'parent_customer_id' => $parentCustomerId,
                    'child_email' => $customerData['email']
                ]);
            }

            // Add custom field for second email address if provided
            if (isset($customerData['second_email'])) {
                $netsuiteCustomer['custentity2nd_email_address'] = $customerData['second_email'];
            }
            
            // Add defaultAddress and addressbook from 3DCart billing and shipping data
            $this->addCustomerAddresses($netsuiteCustomer, $customerData);
            
            // Debug: Log the complete customer payload being sent to NetSuite
            $this->logger->info('Customer payload being sent to NetSuite', [
                'email' => $customerData['email'] ?? 'N/A',
                'payload' => $netsuiteCustomer,
                'has_default_address' => isset($netsuiteCustomer['defaultAddress']),
                'has_addressbook' => isset($netsuiteCustomer['addressbook']),
                'addressbook_items' => count($netsuiteCustomer['addressbook']['items'] ?? [])
            ]);
            
            $startTime = microtime(true);
            $response = $this->makeRequest('POST', '/customer', $netsuiteCustomer);
            $duration = (microtime(true) - $startTime) * 1000;
            
            $this->logger->logApiCall('NetSuite', '/customer', 'POST', $response->getStatusCode(), $duration);
            
            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            
            $firstName = $customerData['firstname'] ?? $customerData['firstName'] ?? '';
            $lastName = $customerData['lastname'] ?? $customerData['lastName'] ?? '';
            
            // Handle different response types
            if ($statusCode === 204) {
                // 204 No Content - Customer created successfully but no body returned
                $this->logger->info('Customer created with 204 response - retrieving customer details', [
                    'email' => $customerData['email'],
                    'name' => trim($firstName . ' ' . $lastName),
                    'parent_customer_id' => $parentCustomerId,
                    'response_code' => $statusCode
                ]);
                
                // Get customer ID from Location header
                $locationHeader = $response->getHeader('Location');
                $customerId = null;
                
                if (!empty($locationHeader)) {
                    $location = $locationHeader[0];
                    // Extract ID from location like "https://11134099-sb2.suitetalk.api.netsuite.com/services/rest/record/v1/customer/213309"
                    if (preg_match('/\/customer\/(\d+)$/', $location, $matches)) {
                        $customerId = $matches[1];
                        $this->logger->info('Extracted customer ID from Location header', [
                            'location' => $location,
                            'customer_id' => $customerId
                        ]);
                    } else {
                        $this->logger->warning('Could not parse customer ID from Location header', [
                            'location' => $location
                        ]);
                    }
                } else {
                    $this->logger->warning('No Location header found in 204 response');
                }
                
                // If we couldn't get ID from header, search for the customer
                if (!$customerId) {
                    $this->logger->warning('Could not extract customer ID from Location header - attempting email search', [
                        'email' => $customerData['email'],
                        'location_header' => $locationHeader
                    ]);
                    
                    // Search for the customer we just created
                    try {
                        $searchResult = $this->findCustomerByEmail($customerData['email']);
                        if ($searchResult && isset($searchResult['id'])) {
                            $customerId = $searchResult['id'];
                            $this->logger->info('Found created customer by email search', [
                                'customer_id' => $customerId,
                                'email' => $customerData['email']
                            ]);
                        } else {
                            throw new \Exception("Customer created (204 response) but could not retrieve customer ID from Location header or email search");
                        }
                    } catch (Exception $searchException) {
                        $this->logger->error('Failed to find created customer by email', [
                            'email' => $customerData['email'],
                            'search_error' => $searchException->getMessage()
                        ]);
                        throw new \Exception("Customer created but could not retrieve customer ID: " . $searchException->getMessage());
                    }
                }
                
                // Return customer data with the ID we found
                $createdCustomer = [
                    'id' => $customerId,
                    'firstName' => $firstName,
                    'lastName' => $lastName,
                    'email' => $customerData['email'],
                    'isPerson' => $customerData['isPerson'] ?? true
                ];
                
            } else {
                // 200 or other success codes with body content
                $createdCustomer = json_decode($responseBody, true);
                
                if (!$createdCustomer || !isset($createdCustomer['id'])) {
                    throw new \Exception("Invalid response format from NetSuite customer creation");
                }
            }
            
            $this->logger->info('Created customer in NetSuite', [
                'email' => $customerData['email'],
                'customer_id' => $createdCustomer['id'],
                'name' => trim($firstName . ' ' . $lastName),
                'parent_customer_id' => $parentCustomerId,
                'second_email' => $customerData['second_email'] ?? null,
                'response_code' => $statusCode
            ]);
            
            return $createdCustomer;
        } catch (RequestException $e) {
            // Get the full response body for better error diagnosis
            $responseBody = '';
            $statusCode = 0;
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $statusCode = $response->getStatusCode();
                $responseBody = $response->getBody()->getContents();
            }
            
            // Log the full NetSuite error response separately to avoid truncation
            $this->logger->error('NetSuite customer creation failed - Full error details', [
                'status_code' => $statusCode,
                'response_body' => $responseBody,
                'error_message' => $e->getMessage()
            ]);
            
            $this->logger->error('NetSuite customer creation failed - Request payload', [
                'request_payload' => $netsuiteCustomer
            ]);
            
            $this->logger->error('Failed to create customer in NetSuite', [
                'customer_data' => $customerData,
                'parent_customer_id' => $parentCustomerId,
                'error' => $e->getMessage()
            ]);
            
            // Include response body in exception for better debugging
            $errorMessage = "Failed to create customer: " . $e->getMessage();
            if (!empty($responseBody)) {
                $errorMessage .= " | Response: " . $responseBody;
            }
            throw new \Exception($errorMessage);
        }
    }
    
    /**
     * Create sales order in NetSuite with enhanced 3DCart integration support
     */
    public function createSalesOrder($orderData, $customerId, $options = []) {
        try {
            // Step 1: Validate customer isPerson status
            $this->logger->info('Validating customer isPerson status for sales order', [
                'customer_id' => $customerId,
                'order_id' => $orderData['OrderID'] ?? 'N/A'
            ]);
            
            // Get customer details to check isPerson status
            $customerQuery = "SELECT id, firstName, lastName, email, companyName, phone, isperson FROM customer WHERE id = " . (int)$customerId;
            $customerResult = $this->executeSuiteQLQuery($customerQuery);
            
            if (empty($customerResult['items'])) {
                throw new \Exception("Customer with ID {$customerId} not found in NetSuite");
            }
            
            $customer = $customerResult['items'][0];
            
            // Ensure we have a person customer for the sales order
            $validatedCustomer = $this->ensurePersonCustomer($customer, $orderData);
            $validatedCustomerId = $validatedCustomer['id'];
            
            if ($validatedCustomerId != $customerId) {
                $this->logger->info('Customer changed from company to person for sales order', [
                    'original_customer_id' => $customerId,
                    'validated_customer_id' => $validatedCustomerId,
                    'original_isPerson' => $customer['isperson'] ?? 'N/A',
                    'validated_isPerson' => $validatedCustomer['isperson'] ?? 'N/A'
                ]);
            }
            
            // Step 2: Start with minimal required fields for NetSuite sales order
            $salesOrder = [
                'entity' => ['id' => (int)$validatedCustomerId],
                'subsidiary' => ['id' => $this->config['netsuite']['default_subsidiary_id']],
                'department' => ['id' => $this->config['netsuite']['default_department_id'] ?? 1]
            ];
            
            // Set tax option based on configuration or parameter
            $isTaxable = $options['is_taxable'] ?? $this->config['netsuite']['sales_order_taxable'] ?? false;
            $salesOrder['istaxable'] = $isTaxable;
            
            // Add optional fields that are commonly used
            if (!empty($orderData['OrderDate'])) {
                $salesOrder['tranDate'] = date('Y-m-d', strtotime($orderData['OrderDate']));
            }
            
            if (!empty($orderData['OrderID'])) {
                $salesOrder['memo'] = 'Order imported from 3DCart - Order #' . $orderData['OrderID'];
                $salesOrder['externalId'] = '3DCART_' . $orderData['OrderID'];
            }

            // Extract and set otherrefnum from QuestionList (QuestionID=2)
            if (isset($orderData['QuestionList']) && is_array($orderData['QuestionList'])) {
                foreach ($orderData['QuestionList'] as $question) {
                    if (isset($question['QuestionID']) && $question['QuestionID'] == 2) {
                        $salesOrder['otherrefnum'] = $question['QuestionAnswer'] ?? '';
                        $this->logger->info('Set otherrefnum from QuestionList', [
                            'order_id' => $orderData['OrderID'],
                            'otherrefnum' => $salesOrder['otherrefnum']
                        ]);
                        break;
                    }
                }
            }

            // Map CustomerComments to custbody2 field
            if (!empty($orderData['CustomerComments'])) {
                $salesOrder['custbody2'] = $orderData['CustomerComments'];
                $this->logger->info('Set custbody2 from CustomerComments', [
                    'order_id' => $orderData['OrderID'],
                    'customer_comments' => $orderData['CustomerComments']
                ]);
            }

            // Note: NetSuite sales orders use customer's default billing address automatically
            // Billing address is not set directly on sales orders but managed through customer records

            // Add shipping information from ShipmentList
            if (isset($orderData['ShipmentList']) && is_array($orderData['ShipmentList']) && !empty($orderData['ShipmentList'])) {
                $shipment = $orderData['ShipmentList'][0]; // Use first shipment
                
                $shippingAddress = [];
                
                // Check if this is a dropship order
                $isDropship = isset($orderData['BillingPaymentMethod']) && 
                             $orderData['BillingPaymentMethod'] === 'Dropship to Customer';
                
                // Set addressee to person/company name (not full address)
                if (!empty($shipment['ShipmentCompany'])) {
                    $shippingAddress['addressee'] = trim(($shipment['ShipmentFirstName'] ?? '') . ' ' . ($shipment['ShipmentLastName'] ?? '')) . "\n" . $shipment['ShipmentCompany'];
                } else {
                    // If ShipmentCompany is empty, use ShipmentFirstName + ShipmentLastName
                    $shippingAddress['addressee'] = trim(($shipment['ShipmentFirstName'] ?? '') . ' ' . ($shipment['ShipmentLastName'] ?? ''));
                }
                
                // Log dropship detection if applicable
                if ($isDropship) {
                    $this->logger->info('Dropship order detected - using standard addressee format', [
                        'order_id' => $orderData['OrderID'],
                        'payment_method' => $orderData['BillingPaymentMethod'],
                        'addressee' => $shippingAddress['addressee']
                    ]);
                }
                
                // Add phone from ShipmentList
                if (!empty($shipment['ShipmentPhone'])) {
                    $shippingAddress['addrphone'] = $shipment['ShipmentPhone'];
                }

                // Use ShipmentList fields for shipping address (not the old ShippingAddress fields)
                if (!empty($shipment['ShipmentAddress'])) {
                    $shippingAddress['addr1'] = $shipment['ShipmentAddress'];
                }
                if (!empty($shipment['ShipmentAddress2'])) {
                    $shippingAddress['addr2'] = $shipment['ShipmentAddress2'];
                }
                if (!empty($shipment['ShipmentCity'])) {
                    $shippingAddress['city'] = $shipment['ShipmentCity'];
                }
                if (!empty($shipment['ShipmentState'])) {
                    $shippingAddress['state'] = $shipment['ShipmentState'];
                }
                if (!empty($shipment['ShipmentZipCode'])) {
                    $shippingAddress['zip'] = $shipment['ShipmentZipCode'];
                }
                if (!empty($shipment['ShipmentCountry'])) {
                    $shippingAddress['country'] = $shipment['ShipmentCountry'];
                } else {
                    $shippingAddress['country'] = 'US'; // Default to US if not specified
                }

                if (!empty($shippingAddress)) {
                    $salesOrder['shippingAddress'] = $shippingAddress;
                    $this->logger->info('Added shipping address from ShipmentList', [
                        'order_id' => $orderData['OrderID'],
                        'shipping_address' => $shippingAddress,
                        'is_dropship' => $isDropship ?? false,
                        'payment_method' => $orderData['BillingPaymentMethod'] ?? 'N/A'
                    ]);
                }
            }
            
            // Set custom fields
            $salesOrder['custbodycustbody4'] = '3DCart Integration';
            $salesOrder['custbodyship_immediate'] = 2;
            
            // Add order items using the correct NetSuite format
            $items = [];
            if (isset($orderData['OrderItemList']) && is_array($orderData['OrderItemList'])) {
                foreach ($orderData['OrderItemList'] as $item) {
                    // Defensive coding: handle both ItemID and OrderItemID field names
                    $originalItemId = $item['ItemID'] ?? $item['OrderItemID'] ?? null;
                    
                    if (empty($originalItemId)) {
                        $this->logger->error('No item identifier found in order item', [
                            'order_id' => $orderData['OrderID'],
                            'item_fields' => array_keys($item),
                            'item_data' => $item
                        ]);
                        throw new \Exception("No item identifier (ItemID or OrderItemID) found in order item");
                    }
                    
                    // Create a normalized item data array for findOrCreateItem
                    $normalizedItem = $item;
                    $normalizedItem['ItemID'] = $originalItemId; // Ensure ItemID is set for findOrCreateItem
                    
                    $itemId = $this->findOrCreateItem($normalizedItem);
                    
                    // Calculate total line cost: ItemUnitPrice + ItemOptionPrice
                    $unitPrice = (float)($item['ItemUnitPrice'] ?? $item['ItemPrice'] ?? 0);
                    $optionPrice = (float)($item['ItemOptionPrice'] ?? 0);
                    $totalLinePrice = $unitPrice + $optionPrice;
                    
                    // Validate item ID before adding to order
                    if (empty($itemId) || !is_numeric($itemId) || $itemId <= 0) {
                        $this->logger->error('Invalid item ID detected - this will cause order creation to fail', [
                            'order_id' => $orderData['OrderID'],
                            'original_item_id' => $originalItemId,
                            'resolved_item_id' => $itemId,
                            'item_description' => $item['ItemDescription'] ?? 'N/A',
                            'available_fields' => array_keys($item)
                        ]);
                        throw new \Exception("Invalid item ID '{$itemId}' for item '{$originalItemId}'. Check NetSuite default_item_id configuration.");
                    }
                    
                    $orderItem = [
                        'item' => ['id' => (int)$itemId],
                        'quantity' => (float)$item['ItemQuantity'],
                        'rate' => $totalLinePrice
                    ];
                    
                    // Set item taxable status based on sales order setting
                    $orderItem['istaxable'] = $isTaxable;
                    
                    $this->logger->info('Added order item with combined pricing', [
                        'order_id' => $orderData['OrderID'],
                        'original_item_id' => $originalItemId,
                        'netsuite_item_id' => $itemId,
                        'unit_price' => $unitPrice,
                        'option_price' => $optionPrice,
                        'total_line_price' => $totalLinePrice,
                        'quantity' => $item['ItemQuantity'],
                        'field_used' => isset($item['ItemID']) ? 'ItemID' : 'OrderItemID'
                    ]);
                    
                    $items[] = $orderItem;
                }
            }
            
            // Add tax as a line item if enabled and tax amount > 0
            $taxAmount = (float)($orderData['SalesTax'] ?? 0);
            if ($this->config['netsuite']['include_tax_as_line_item'] && $taxAmount > 0) {
                $taxItemId = (int)$this->config['netsuite']['tax_item_id'];
                
                // Validate tax item exists before using it
                $itemValidation = $this->validateItem($taxItemId);
                if ($itemValidation['exists'] && $itemValidation['usable']) {
                    $items[] = [
                        'item' => ['id' => $taxItemId],
                        'quantity' => 1,
                        'rate' => $taxAmount,
                        'istaxable' => false // Tax items are not taxable
                    ];
                    
                    $this->logger->info('Added tax line item', [
                        'order_id' => $orderData['OrderID'],
                        'tax_amount' => $taxAmount,
                        'tax_item_id' => $taxItemId
                    ]);
                } else {
                    $this->logger->warning('Tax item validation failed, skipping tax line item', [
                        'order_id' => $orderData['OrderID'],
                        'tax_amount' => $taxAmount,
                        'tax_item_id' => $taxItemId,
                        'validation_result' => $itemValidation
                    ]);
                }
            }
            
            // Add shipping as a line item if enabled and shipping cost > 0
            $shippingCost = (float)($orderData['ShippingCost'] ?? 0);
            if ($this->config['netsuite']['include_shipping_as_line_item'] && $shippingCost > 0) {
                $shippingItemId = (int)$this->config['netsuite']['shipping_item_id'];
                
                // Validate shipping item exists before using it
                $itemValidation = $this->validateItem($shippingItemId);
                if ($itemValidation['exists'] && $itemValidation['usable']) {
                    $items[] = [
                        'item' => ['id' => $shippingItemId],
                        'quantity' => 1,
                        'rate' => $shippingCost,
                        'istaxable' => false // Shipping typically not taxable
                    ];
                    
                    $this->logger->info('Added shipping line item', [
                        'order_id' => $orderData['OrderID'],
                        'shipping_cost' => $shippingCost,
                        'shipping_item_id' => $shippingItemId
                    ]);
                } else {
                    $this->logger->warning('Shipping item validation failed, skipping shipping line item', [
                        'order_id' => $orderData['OrderID'],
                        'shipping_cost' => $shippingCost,
                        'shipping_item_id' => $shippingItemId,
                        'validation_result' => $itemValidation
                    ]);
                }
            }
            
            // Use 3DCart calculated totals - OrderAmount is the final total after discount
            $orderAmount = (float)($orderData['OrderAmount'] ?? $orderData['OrderTotal'] ?? 0);
            $discountAmount = (float)($orderData['OrderDiscount'] ?? $orderData['DiscountAmount'] ?? 0);
            $taxAmount = (float)($orderData['SalesTax'] ?? 0);
            $shippingCost = (float)($orderData['ShippingCost'] ?? 0);
            
            // OrderAmount is already the final amount after discount
            // Target subtotal = OrderAmount - Tax - Shipping
            $targetSubtotal = $orderAmount - $taxAmount - $shippingCost;
            
            // Calculate current item total (should now include ItemUnitPrice + ItemOptionPrice)
            $currentItemTotal = 0;
            foreach ($items as $item) {
                $currentItemTotal += $item['quantity'] * $item['rate'];
            }
            
            $this->logger->info('3DCart total analysis with corrected item pricing', [
                'order_id' => $orderData['OrderID'],
                'threeDCart_order_amount' => $orderAmount, // Final amount after discount
                'threeDCart_discount' => $discountAmount,
                'threeDCart_tax' => $taxAmount,
                'threeDCart_shipping' => $shippingCost,
                'target_subtotal' => $targetSubtotal,
                'current_item_total' => $currentItemTotal,
                'adjustment_needed' => $targetSubtotal - $currentItemTotal,
                'note' => 'OrderAmount is final total after discount, items now include UnitPrice + OptionPrice'
            ]);
            
            // Check if adjustment is needed after fixing item pricing
            $adjustmentNeeded = $targetSubtotal - $currentItemTotal;
            if (abs($adjustmentNeeded) > 0.01) {
                $this->logger->warning('Total adjustment needed but skipped to avoid invalid item errors', [
                    'order_id' => $orderData['OrderID'],
                    'adjustment_amount' => $adjustmentNeeded,
                    'current_item_total' => $currentItemTotal,
                    'target_subtotal' => $targetSubtotal,
                    'reason' => 'Discount adjustment disabled - using item totals as-is'
                ]);
                
                // Note: We're not adding adjustment items to avoid invalid item ID errors
                // The order will be created with the calculated item totals
                // Any discount is already reflected in the 3DCart OrderAmount vs individual item costs
            } else {
                $this->logger->info('No adjustment needed - item totals match 3DCart OrderAmount', [
                    'order_id' => $orderData['OrderID'],
                    'current_item_total' => $currentItemTotal,
                    'target_subtotal' => $targetSubtotal
                ]);
            }
            
            // Note: We're not setting discountTotal to avoid potential issues
            // The discount effect is already built into the 3DCart OrderAmount
            // Memo will only contain the standard order information without discount details
            if ($discountAmount > 0) {
                $this->logger->info('Discount amount noted but not added to memo or NetSuite fields', [
                    'order_id' => $orderData['OrderID'],
                    'discount_amount' => $discountAmount,
                    'note' => 'Discount effect is built into 3DCart OrderAmount - no separate tracking needed'
                ]);
            }
            
            if (empty($items)) {
                throw new \Exception("No valid items found in order");
            }
            
            // Validate the corrected item pricing (without adjustment items)
            $finalCalculatedTotal = 0;
            foreach ($items as $item) {
                $finalCalculatedTotal += $item['quantity'] * $item['rate'];
            }
            
            // Note: We're not forcing exact matches anymore to avoid invalid item errors
            $expectedSubtotal = $orderAmount - $taxAmount - $shippingCost;
            $difference = $finalCalculatedTotal - $expectedSubtotal;
            
            $this->logger->info('Final validation with corrected item pricing (no adjustments)', [
                'order_id' => $orderData['OrderID'],
                'final_calculated_total' => $finalCalculatedTotal,
                'expected_subtotal' => $expectedSubtotal,
                'threeDCart_order_amount' => $orderAmount,
                'threeDCart_discount' => $discountAmount,
                'difference' => $difference,
                'items_count' => count($items),
                'validation_note' => 'Using corrected item pricing (UnitPrice + OptionPrice), no adjustment items added'
            ]);
            
            if (abs($difference) > 0.01) {
                $this->logger->info('NetSuite total differs from 3DCart but proceeding without adjustment', [
                    'order_id' => $orderData['OrderID'],
                    'final_calculated_total' => $finalCalculatedTotal,
                    'expected_subtotal' => $expectedSubtotal,
                    'difference' => $difference,
                    'note' => 'Difference exists but order will be created with item-based totals to avoid invalid item errors'
                ]);
            } else {
                $this->logger->info('SUCCESS: NetSuite totals match 3DCart OrderAmount exactly', [
                    'order_id' => $orderData['OrderID'],
                    'final_total' => $finalCalculatedTotal,
                    'perfect_match' => true
                ]);
            }
            
            // Use the correct NetSuite payload structure
            $salesOrder['item'] = [
                'items' => $items
            ];
            
            // Log the payload for debugging
            $this->logger->info('Creating sales order with enhanced payload', [
                'payload' => $salesOrder,
                'order_id' => $orderData['OrderID'],
                'is_taxable' => $isTaxable,
                'has_shipping_info' => isset($salesOrder['shippingAddress']),
                'has_otherrefnum' => isset($salesOrder['otherrefnum']),
                'amount_breakdown' => [
                    'items_count' => count($items),
                    'tax_amount' => $taxAmount ?? 0,
                    'shipping_cost' => $shippingCost ?? 0,
                    'discount_amount' => $discountAmount ?? 0,
                    'order_total' => $orderData['OrderAmount'] ?? $orderData['OrderTotal'] ?? 0
                ]
            ]);
            
            $startTime = microtime(true);
            $response = $this->makeRequest('POST', '/salesOrder', $salesOrder);
            $duration = (microtime(true) - $startTime) * 1000;
            
            $this->logger->logApiCall('NetSuite', '/salesOrder', 'POST', $response->getStatusCode(), $duration);
            
            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            $createdOrder = null;
            
            // Handle different response formats based on status code
            if ($statusCode === 204) {
                // 204 No Content - record created successfully, ID in Location header
                $locationHeader = $response->getHeader('Location');
                if (!empty($locationHeader)) {
                    $location = $locationHeader[0];
                    // Extract ID from Location header (e.g., .../salesOrder/123456)
                    if (preg_match('/\/salesOrder\/(\d+)$/', $location, $matches)) {
                        $salesOrderId = $matches[1];
                        $createdOrder = ['id' => $salesOrderId];
                        
                        $this->logger->info('Sales order created with 204 response - extracted ID from Location header', [
                            'threedcart_order_id' => $orderData['OrderID'],
                            'netsuite_order_id' => $salesOrderId,
                            'location' => $location,
                            'customer_id' => $validatedCustomerId,
                            'original_customer_id' => $customerId,
                            'response_code' => $statusCode
                        ]);
                    } else {
                        $this->logger->warning('Sales order created but could not extract ID from Location header', [
                            'threedcart_order_id' => $orderData['OrderID'],
                            'location' => $location,
                            'customer_id' => $validatedCustomerId,
                            'response_code' => $statusCode
                        ]);
                    }
                } else {
                    $this->logger->warning('Sales order created but no Location header found', [
                        'threedcart_order_id' => $orderData['OrderID'],
                        'customer_id' => $validatedCustomerId,
                        'response_code' => $statusCode
                    ]);
                }
            } else if ($statusCode === 200 || $statusCode === 201) {
                // 200/201 - record created with response body
                $createdOrder = json_decode($responseBody, true);
                
                if ($createdOrder && isset($createdOrder['id'])) {
                    $this->logger->info('Created sales order in NetSuite', [
                        'threedcart_order_id' => $orderData['OrderID'],
                        'netsuite_order_id' => $createdOrder['id'],
                        'customer_id' => $validatedCustomerId,
                        'original_customer_id' => $customerId,
                        'total_items' => count($items),
                        'response_code' => $statusCode
                    ]);
                } else {
                    $this->logger->warning('Sales order response received but format unexpected', [
                        'threedcart_order_id' => $orderData['OrderID'],
                        'response' => $createdOrder,
                        'customer_id' => $validatedCustomerId,
                        'response_code' => $statusCode
                    ]);
                }
            } else {
                $this->logger->warning('Unexpected response code for sales order creation', [
                    'threedcart_order_id' => $orderData['OrderID'],
                    'response_code' => $statusCode,
                    'response_body' => $responseBody,
                    'customer_id' => $validatedCustomerId
                ]);
            }
            
            return $createdOrder;
        } catch (RequestException $e) {
            $responseBody = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : 'No response body';
            
            // Parse the error response to get detailed error information
            $errorDetails = [];
            if ($responseBody) {
                $errorData = json_decode($responseBody, true);
                if ($errorData && isset($errorData['o:errorDetails'])) {
                    foreach ($errorData['o:errorDetails'] as $detail) {
                        $errorDetails[] = [
                            'detail' => $detail['detail'] ?? 'Unknown error',
                            'errorCode' => $detail['o:errorCode'] ?? 'Unknown code',
                            'errorPath' => $detail['o:errorPath'] ?? 'Unknown path'
                        ];
                    }
                }
            }
            
            $this->logger->error('Failed to create sales order in NetSuite', [
                'threedcart_order_id' => $orderData['OrderID'],
                'customer_id' => $validatedCustomerId ?? $customerId,
                'original_customer_id' => $customerId,
                'error' => $e->getMessage(),
                'response_body' => $responseBody,
                'status_code' => $e->getResponse() ? $e->getResponse()->getStatusCode() : null,
                'error_details' => $errorDetails,
                'payload_sent' => $salesOrder
            ]);
            
            $errorMessage = "Failed to create sales order: " . $e->getMessage();
            if (!empty($errorDetails)) {
                $errorMessage .= " Details: " . json_encode($errorDetails);
            }
            
            throw new \Exception($errorMessage);
        }
    }
    
    /**
     * Find or create item in NetSuite
     */
    private function findOrCreateItem($itemData) {
        try {
            $itemIdentifier = $itemData['ItemID'];
            
            $this->logger->debug('Searching for item in NetSuite', [
                'item_identifier' => $itemIdentifier,
                'item_description' => $itemData['ItemDescription'] ?? 'N/A',
                'search_method' => 'exact_match'
            ]);
            
            // First, try to find existing item by exact SKU match using the correct endpoint
            $query = 'itemId IS "' . $itemIdentifier . '"';
            
            // Try different item type endpoints in order of preference
            $itemEndpoints = ['/inventoryItem', '/noninventoryItem', '/serviceItem', '/item'];
            
            foreach ($itemEndpoints as $endpoint) {
                try {
                    $response = $this->makeRequest('GET', $endpoint, null, [
                        'q' => $query,
                        'limit' => 1
                    ]);
                    
                    $data = json_decode($response->getBody()->getContents(), true);
                    
                    if (isset($data['items']) && count($data['items']) > 0) {
                        $this->logger->info('Found exact item match in NetSuite', [
                            'item_id' => $itemData['ItemID'],
                            'netsuite_id' => $data['items'][0]['id'],
                            'endpoint' => $endpoint,
                            'query_type' => 'exact_match'
                        ]);
                        return $data['items'][0]['id'];
                    }
                } catch (RequestException $e) {
                    // Continue to next endpoint if this one fails
                    $this->logger->debug('Exact item search failed on endpoint', [
                        'endpoint' => $endpoint,
                        'item_id' => $itemData['ItemID'],
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }
            
            // If exact match fails, try partial match as fallback
            $this->logger->info('Exact match failed, trying partial match', [
                'item_id' => $itemData['ItemID']
            ]);
            
            $partialQuery = 'itemId CONTAIN "' . $itemData['ItemID'] . '"';
            
            foreach ($itemEndpoints as $endpoint) {
                try {
                    $response = $this->makeRequest('GET', $endpoint, null, [
                        'q' => $partialQuery,
                        'limit' => 1
                    ]);
                    
                    $data = json_decode($response->getBody()->getContents(), true);
                    
                    if (isset($data['items']) && count($data['items']) > 0) {
                        $this->logger->warning('Found partial item match in NetSuite', [
                            'item_id' => $itemData['ItemID'],
                            'netsuite_id' => $data['items'][0]['id'],
                            'endpoint' => $endpoint,
                            'query_type' => 'partial_match',
                            'warning' => 'Using partial match - verify this is the correct item'
                        ]);
                        return $data['items'][0]['id'];
                    }
                } catch (RequestException $e) {
                    // Continue to next endpoint if this one fails
                    $this->logger->debug('Partial item search failed on endpoint', [
                        'endpoint' => $endpoint,
                        'item_id' => $itemData['ItemID'],
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }
            
            // If item doesn't exist, try to create it (if enabled in config)
            if ($this->config['netsuite']['create_missing_items']) {
                $newItem = [
                    'itemId' => $itemData['ItemID'], // Use ItemID as the SKU
                    'displayName' => $itemData['ItemDescription'],
                    'description' => $itemData['ItemDescription'],
                    'basePrice' => (float)$itemData['ItemUnitPrice'],
                    'includeChildren' => false,
                    'isInactive' => false,
                    'subsidiary' => [['id' => $this->config['netsuite']['default_subsidiary_id']]],
                ];
                
                try {
                    $itemType = $this->config['netsuite']['item_type'];
                    $response = $this->makeRequest('POST', '/' . $itemType, $newItem);
                    $createdItem = json_decode($response->getBody()->getContents(), true);
                    
                    $this->logger->info('Created new item in NetSuite', [
                        'item_id' => $itemData['ItemID'],
                        'name' => $itemData['ItemDescription'],
                        'netsuite_id' => $createdItem['id'],
                        'item_type' => $itemType
                    ]);
                    
                    return $createdItem['id'];
                } catch (RequestException $createError) {
                    $this->logger->warning('Failed to create item, using default', [
                        'item_id' => $itemData['ItemID'],
                        'item_type' => $itemType,
                        'create_error' => $createError->getMessage()
                    ]);
                }
            }
            
            // Return default item ID if creation is disabled or failed
            $defaultItemId = $this->config['netsuite']['default_item_id'];
            $this->logger->warning('Using default item ID - verify this item exists in NetSuite', [
                'original_item_id' => $itemData['ItemID'],
                'default_item_id' => $defaultItemId,
                'reason' => 'Item not found and creation disabled or failed',
                'warning' => 'If default_item_id does not exist in NetSuite, order creation will fail'
            ]);
            
            return $defaultItemId;
        } catch (RequestException $e) {
            $defaultItemId = $this->config['netsuite']['default_item_id'];
            $this->logger->error('Failed to search for item, using default - verify default item exists', [
                'item_id' => $itemData['ItemID'],
                'default_item_id' => $defaultItemId,
                'error' => $e->getMessage(),
                'warning' => 'If default_item_id does not exist in NetSuite, order creation will fail'
            ]);
            
            return $defaultItemId;
        }
    }
    
    /**
     * Format billing address from 3DCart order data
     */
    private function formatBillingAddress($orderData) {
        return [
            'addr1' => $orderData['BillingAddress'] ?? '',
            'addr2' => $orderData['BillingAddress2'] ?? '',
            'city' => $orderData['BillingCity'] ?? '',
            'state' => $orderData['BillingState'] ?? '',
            'zip' => $orderData['BillingZipCode'] ?? '',
            'country' => $orderData['BillingCountry'] ?? 'US',
        ];
    }
    
    /**
     * Format shipping address from 3DCart order data
     */
    private function formatShippingAddress($orderData) {
        return [
            'addr1' => $orderData['ShippingAddress'] ?? $orderData['BillingAddress'] ?? '',
            'addr2' => $orderData['ShippingAddress2'] ?? $orderData['BillingAddress2'] ?? '',
            'city' => $orderData['ShippingCity'] ?? $orderData['BillingCity'] ?? '',
            'state' => $orderData['ShippingState'] ?? $orderData['BillingState'] ?? '',
            'zip' => $orderData['ShippingZipCode'] ?? $orderData['BillingZipCode'] ?? '',
            'country' => $orderData['ShippingCountry'] ?? $orderData['BillingCountry'] ?? 'US',
        ];
    }
    
    /**
     * Format address for NetSuite (legacy method - returns array)
     */
    private function formatAddress($addressData) {
        return [
            'addr1' => $addressData['Address1'] ?? '',
            'addr2' => $addressData['Address2'] ?? '',
            'city' => $addressData['City'] ?? '',
            'state' => $addressData['State'] ?? '',
            'zip' => $addressData['PostalCode'] ?? '',
            'country' => $addressData['Country'] ?? 'US',
        ];
    }
    
    /**
     * Format address as string for NetSuite customer defaultAddress field
     */
    private function formatAddressAsString($addressData) {
        // Handle different address data formats
        $addr1 = $addressData['Address1'] ?? $addressData['addr1'] ?? $addressData['address1'] ?? '';
        $addr2 = $addressData['Address2'] ?? $addressData['addr2'] ?? $addressData['address2'] ?? '';
        $city = $addressData['City'] ?? $addressData['city'] ?? '';
        $state = $addressData['State'] ?? $addressData['state'] ?? '';
        $zip = $addressData['PostalCode'] ?? $addressData['zip'] ?? $addressData['postal_code'] ?? '';
        $country = $addressData['Country'] ?? $addressData['country'] ?? 'US';
        
        // Build address string
        $addressParts = [];
        
        if (!empty($addr1)) {
            $addressParts[] = $addr1;
        }
        
        if (!empty($addr2)) {
            $addressParts[] = $addr2;
        }
        
        if (!empty($city)) {
            $addressParts[] = $city;
        }
        
        if (!empty($state)) {
            $addressParts[] = $state;
        }
        
        if (!empty($zip)) {
            $addressParts[] = $zip;
        }
        
        if (!empty($country) && $country !== 'US') {
            $addressParts[] = $country;
        }
        
        return implode(', ', $addressParts);
    }
    
    /**
     * Validate and truncate field to NetSuite limits
     */
    private function validateAndTruncateField($value, $maxLength, $fieldName) {
        if (empty($value)) {
            return $value;
        }
        
        // Convert to string and trim
        $value = trim((string)$value);
        
        // Check length and truncate if necessary
        if (strlen($value) > $maxLength) {
            $originalValue = $value;
            $value = substr($value, 0, $maxLength);
            $this->logger->warning('Field truncated for NetSuite limits', [
                'field' => $fieldName,
                'original_length' => strlen($originalValue),
                'max_length' => $maxLength,
                'original_value' => $originalValue,
                'truncated_value' => $value
            ]);
        }
        
        return $value;
    }
    
    /**
     * Validate email field
     */
    private function validateEmailField($email) {
        if (empty($email)) {
            return '';
        }
        
        $email = trim((string)$email);
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->logger->warning('Invalid email format detected', [
                'email' => $email
            ]);
            return ''; // Return empty string for invalid emails
        }
        
        // Check length (NetSuite email field limit)
        if (strlen($email) > 254) {
            $this->logger->warning('Email too long for NetSuite', [
                'email' => $email,
                'length' => strlen($email)
            ]);
            return ''; // Return empty string for too long emails
        }
        
        return $email;
    }

    /**
     * Add defaultAddress and addressbook to customer data from 3DCart billing and shipping information
     */
    private function addCustomerAddresses(&$netsuiteCustomer, $customerData) {
        // Build defaultAddress string from billing information
        $defaultAddress = $this->buildDefaultAddressString($customerData);
        if (!empty($defaultAddress)) {
            $netsuiteCustomer['defaultAddress'] = $defaultAddress;
        }
        
        // Build addressbook with billing and shipping addresses
        $addressbook = $this->buildAddressbook($customerData);
        if (!empty($addressbook)) {
            $netsuiteCustomer['addressbook'] = $addressbook;
        }
        
        $this->logger->info('Added customer addresses', [
            'email' => $customerData['email'] ?? 'N/A',
            'has_default_address' => !empty($defaultAddress),
            'addressbook_items' => count($addressbook['items'] ?? [])
        ]);
    }
    
    /**
     * Build defaultAddress string from 3DCart billing data
     * Format: "75 W Overton Rd, Boise, ID, 83709" (simple comma-separated format)
     */
    private function buildDefaultAddressString($customerData) {
        $addressParts = [];
        
        // Add address components in simple format
        if (!empty($customerData['BillingAddress'])) {
            $addressParts[] = $customerData['BillingAddress'];
        }
        
        if (!empty($customerData['BillingCity'])) {
            $addressParts[] = $customerData['BillingCity'];
        }
        
        if (!empty($customerData['BillingState'])) {
            $addressParts[] = $customerData['BillingState'];
        }
        
        if (!empty($customerData['BillingZipCode'])) {
            $addressParts[] = $customerData['BillingZipCode'];
        }
        
        // Return simple comma-separated format like "75 W Overton Rd, Boise, ID, 83709"
        return implode(', ', $addressParts);
    }
    
    /**
     * Build addressbook with billing and shipping addresses
     */
    private function buildAddressbook($customerData) {
        $addressbook = [
            'items' => []
        ];
        
        // Add billing address
        $billingAddress = $this->buildBillingAddressItem($customerData);
        if (!empty($billingAddress)) {
            $addressbook['items'][] = $billingAddress;
        }
        
        // Add shipping address from ShipmentList
        $shippingAddress = $this->buildShippingAddressItem($customerData);
        if (!empty($shippingAddress)) {
            $addressbook['items'][] = $shippingAddress;
        }
        
        return !empty($addressbook['items']) ? $addressbook : null;
    }
    
    /**
     * Build billing address item for addressbook
     */
    private function buildBillingAddressItem($customerData) {
        // Check if we have billing address data
        if (empty($customerData['BillingAddress']) && empty($customerData['BillingCity'])) {
            return null;
        }
        
        $addressItem = [
            'defaultBilling' => true,
            'defaultShipping' => false,
            'addressbookaddress' => []
        ];
        
        // Add address fields with validation
        if (!empty($customerData['BillingCountry'])) {
            $addressItem['addressbookaddress']['country'] = $this->validateAndTruncateField($customerData['BillingCountry'], 2, 'billing_country');
        }
        if (!empty($customerData['BillingZipCode'])) {
            $addressItem['addressbookaddress']['zip'] = $this->validateAndTruncateField($customerData['BillingZipCode'], 36, 'billing_zip');
        }
        if (!empty($customerData['BillingCompany'])) {
            $addressItem['addressbookaddress']['addressee'] = $this->validateAndTruncateField($customerData['BillingCompany'], 150, 'billing_addressee');
        }
        if (!empty($customerData['BillingAddress'])) {
            $addressItem['addressbookaddress']['addr1'] = $this->validateAndTruncateField($customerData['BillingAddress'], 150, 'billing_addr1');
        }
        if (!empty($customerData['BillingAddress2'])) {
            $addressItem['addressbookaddress']['addr2'] = $this->validateAndTruncateField($customerData['BillingAddress2'], 150, 'billing_addr2');
        }
        if (!empty($customerData['BillingCity'])) {
            $addressItem['addressbookaddress']['city'] = $this->validateAndTruncateField($customerData['BillingCity'], 50, 'billing_city');
        }
        if (!empty($customerData['BillingState'])) {
            $addressItem['addressbookaddress']['state'] = $this->validateAndTruncateField($customerData['BillingState'], 50, 'billing_state');
        }
        
        return !empty($addressItem['addressbookaddress']) ? $addressItem : null;
    }
    
    /**
     * Build shipping address item for addressbook from ShipmentList
     */
    private function buildShippingAddressItem($customerData) {
        // Check if we have ShipmentList data
        if (!isset($customerData['ShipmentList']) || !is_array($customerData['ShipmentList']) || empty($customerData['ShipmentList'])) {
            return null;
        }
        
        $shipment = $customerData['ShipmentList'][0]; // Use first shipment
        
        // Check if we have shipping address data
        if (empty($shipment['ShipmentAddress']) && empty($shipment['ShipmentCity'])) {
            return null;
        }
        
        $addressItem = [
            'defaultBilling' => false,
            'defaultShipping' => true,
            'addressbookaddress' => []
        ];
        
        // Add address fields with validation
        if (!empty($shipment['ShipmentCountry'])) {
            $addressItem['addressbookaddress']['country'] = $this->validateAndTruncateField($shipment['ShipmentCountry'], 2, 'shipping_country');
        }
        if (!empty($shipment['ShipmentZipCode'])) {
            $addressItem['addressbookaddress']['zip'] = $this->validateAndTruncateField($shipment['ShipmentZipCode'], 36, 'shipping_zip');
        }
        
        // Set addressee - use company if available, otherwise use person name
        if (!empty($shipment['ShipmentCompany'])) {
            $addressItem['addressbookaddress']['addressee'] = $this->validateAndTruncateField($shipment['ShipmentCompany'], 150, 'shipping_addressee');
        } else {
            // Fallback to person name if no company
            $personName = trim(($shipment['ShipmentFirstName'] ?? '') . ' ' . ($shipment['ShipmentLastName'] ?? ''));
            if (!empty($personName)) {
                $addressItem['addressbookaddress']['addressee'] = $this->validateAndTruncateField($personName, 150, 'shipping_addressee');
            }
        }
        if (!empty($shipment['ShipmentAddress'])) {
            $addressItem['addressbookaddress']['addr1'] = $this->validateAndTruncateField($shipment['ShipmentAddress'], 150, 'shipping_addr1');
        }
        if (!empty($shipment['ShipmentAddress2'])) {
            $addressItem['addressbookaddress']['addr2'] = $this->validateAndTruncateField($shipment['ShipmentAddress2'], 150, 'shipping_addr2');
        }
        if (!empty($shipment['ShipmentCity'])) {
            $addressItem['addressbookaddress']['city'] = $this->validateAndTruncateField($shipment['ShipmentCity'], 50, 'shipping_city');
        }
        if (!empty($shipment['ShipmentState'])) {
            $addressItem['addressbookaddress']['state'] = $this->validateAndTruncateField($shipment['ShipmentState'], 50, 'shipping_state');
        }
        
        return !empty($addressItem['addressbookaddress']) ? $addressItem : null;
    }
    
    /**
     * Get sales order by external ID using SuiteQL
     */
    public function getSalesOrderByExternalId($externalId) {
        try {
            $suiteQLQuery = "SELECT id, tranid, externalid, status, trandate, entity FROM transaction WHERE recordtype = 'salesorder' AND externalid = '" . $externalId . "'";
            
            $result = $this->executeSuiteQLQuery($suiteQLQuery);
            
            if (!empty($result['items'])) {
                return $result['items'][0];
            }
            
            return null;
        } catch (\Exception $e) {
            $this->logger->error('Failed to find sales order by external ID', [
                'external_id' => $externalId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Get multiple sales orders by external IDs using SuiteQL
     */
    public function getSalesOrdersByExternalIds($externalIds) {
        try {
            if (empty($externalIds)) {
                return [];
            }
            
            // Format external IDs for SQL IN clause
            $formattedIds = array_map(function($id) {
                return "'" . $id . "'";
            }, $externalIds);
            $idsString = implode(', ', $formattedIds);
            
            $suiteQLQuery = "SELECT id, tranid, externalid, status, trandate, entity FROM transaction WHERE recordtype = 'salesorder' AND externalid IN (" . $idsString . ")";
            
            $result = $this->executeSuiteQLQuery($suiteQLQuery);
            
            return $result['items'] ?? [];
        } catch (\Exception $e) {
            $this->logger->error('Failed to find sales orders by external IDs', [
                'external_ids' => $externalIds,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Execute SuiteQL query
     */
    public function executeSuiteQLQuery($query, $offset = 0, $limit = 1000) {
        try {
            $payload = [
                'q' => $query
            ];
            
            $url = '/query/v1/suiteql';
            if ($offset > 0) {
                $url .= '?offset=' . $offset;
            }
            
            $startTime = microtime(true);
            $response = $this->makeRequest('POST', $url, $payload);
            $duration = (microtime(true) - $startTime) * 1000;
            
            $this->logger->logApiCall('NetSuite', $url, 'POST', $response->getStatusCode(), $duration);
            
            $data = json_decode($response->getBody()->getContents(), true);
            
            $this->logger->info('SuiteQL query executed', [
                'query' => $query,
                'offset' => $offset,
                'results_count' => count($data['items'] ?? []),
                'has_more' => $data['hasMore'] ?? false
            ]);
            
            return $data;
        } catch (RequestException $e) {
            $responseBody = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : 'No response body';
            
            $this->logger->error('SuiteQL query failed', [
                'query' => $query,
                'error' => $e->getMessage(),
                'response_body' => $responseBody,
                'status_code' => $e->getResponse() ? $e->getResponse()->getStatusCode() : null
            ]);
            
            throw new \Exception("SuiteQL query failed: " . $e->getMessage());
        }
    }
    
    /**
     * Check sync status for multiple 3DCart orders
     */
    public function checkOrdersSyncStatus($orderIds) {
        try {
            // Convert 3DCart order IDs to external IDs
            $externalIds = array_map(function($orderId) {
                return '3DCART_' . $orderId;
            }, $orderIds);
            
            $netsuiteOrders = $this->getSalesOrdersByExternalIds($externalIds);
            
            // Create a map of external ID to NetSuite order data
            $syncStatusMap = [];
            foreach ($netsuiteOrders as $order) {
                $externalId = $order['externalid'];
                $threeDCartOrderId = str_replace('3DCART_', '', $externalId);
                
                $syncStatusMap[$threeDCartOrderId] = [
                    'synced' => true,
                    'netsuite_id' => $order['id'],
                    'netsuite_tranid' => $order['tranid'],
                    'status' => $order['status'],
                    'sync_date' => $order['trandate'],
                    'customer_id' => $order['entity']
                ];
            }
            
            // Add unsynced orders
            foreach ($orderIds as $orderId) {
                if (!isset($syncStatusMap[$orderId])) {
                    $syncStatusMap[$orderId] = [
                        'synced' => false,
                        'netsuite_id' => null,
                        'netsuite_tranid' => null,
                        'status' => null,
                        'sync_date' => null,
                        'customer_id' => null
                    ];
                }
            }
            
            return $syncStatusMap;
        } catch (\Exception $e) {
            $this->logger->error('Failed to check orders sync status', [
                'order_ids' => $orderIds,
                'error' => $e->getMessage()
            ]);
            
            // Return all orders as unsynced on error
            $errorStatusMap = [];
            foreach ($orderIds as $orderId) {
                $errorStatusMap[$orderId] = [
                    'synced' => false,
                    'netsuite_id' => null,
                    'netsuite_tranid' => null,
                    'status' => null,
                    'total' => null,
                    'sync_date' => null,
                    'customer_id' => null,
                    'error' => $e->getMessage()
                ];
            }
            
            return $errorStatusMap;
        }
    }
    
    /**
     * Validate if an item ID exists and is usable in NetSuite
     */
    public function validateItem($itemId) {
        try {
            $response = $this->makeRequest('GET', "/item/$itemId");
            
            if ($response->getStatusCode() == 200) {
                $itemData = json_decode($response->getBody()->getContents(), true);
                
                return [
                    'exists' => true,
                    'id' => $itemData['id'] ?? $itemId,
                    'itemid' => $itemData['itemid'] ?? 'N/A',
                    'displayname' => $itemData['displayname'] ?? 'N/A',
                    'itemtype' => $itemData['itemtype'] ?? 'N/A',
                    'isinactive' => $itemData['isinactive'] ?? false,
                    'issaleitem' => $itemData['issaleitem'] ?? false,
                    'usable' => !($itemData['isinactive'] ?? false) && ($itemData['issaleitem'] ?? false)
                ];
            }
            
            return [
                'exists' => false,
                'error' => 'Item not found'
            ];
            
        } catch (RequestException $e) {
            $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : null;
            
            if ($statusCode == 404) {
                return [
                    'exists' => false,
                    'error' => 'Item does not exist'
                ];
            }
            
            return [
                'exists' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Delete a sales order by ID
     */
    public function deleteSalesOrder($salesOrderId) {
        try {
            $this->logger->info('Attempting to delete sales order', [
                'sales_order_id' => $salesOrderId
            ]);
            
            $response = $this->makeRequest('DELETE', "/salesOrder/$salesOrderId");
            
            if ($response->getStatusCode() == 204 || $response->getStatusCode() == 200) {
                $this->logger->info('Successfully deleted sales order', [
                    'sales_order_id' => $salesOrderId,
                    'status_code' => $response->getStatusCode()
                ]);
                return true;
            } else {
                $this->logger->warning('Unexpected response when deleting sales order', [
                    'sales_order_id' => $salesOrderId,
                    'status_code' => $response->getStatusCode(),
                    'response_body' => $response->getBody()->getContents()
                ]);
                return false;
            }
            
        } catch (RequestException $e) {
            $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : null;
            $responseBody = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : null;
            
            $this->logger->error('Failed to delete sales order', [
                'sales_order_id' => $salesOrderId,
                'status_code' => $statusCode,
                'error' => $e->getMessage(),
                'response_body' => $responseBody
            ]);
            
            throw new \Exception("Failed to delete sales order: " . $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error('Exception while deleting sales order', [
                'sales_order_id' => $salesOrderId,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Search for campaign by campaign ID
     */
    public function searchCampaign($campaignId) {
        try {
            $this->logger->info('Searching for campaign', ['campaign_id' => $campaignId]);
            
            $query = "SELECT title, campaignId, id FROM SearchCampaign WHERE title='{$campaignId}'";
            
            $queryUrl = rtrim($this->credentials['base_url'], '/') . '/services/rest/query/v1/suiteql/?offset=0';
            
            $response = $this->makeRequest('POST', $queryUrl, [
                'q' => $query
            ], true); // Use full URL
            
            if ($response->getStatusCode() == 200) {
                $data = json_decode($response->getBody()->getContents(), true);
                
                $campaigns = [];
                if (isset($data['items']) && !empty($data['items'])) {
                    foreach ($data['items'] as $item) {
                        $campaigns[] = [
                            'id' => $item['id'],
                            'campaignid' => $item['campaignid'],
                            'title' => $item['title']
                        ];
                    }
                }
                
                $this->logger->info('Campaign search completed', [
                    'campaign_id' => $campaignId,
                    'found_count' => count($campaigns)
                ]);
                
                return [
                    'success' => true,
                    'campaigns' => $campaigns
                ];
            }
            
            throw new \Exception('Unexpected status code: ' . $response->getStatusCode());
            
        } catch (RequestException $e) {
            $error = 'Campaign search failed: ' . $e->getMessage();
            $this->logger->error($error, ['campaign_id' => $campaignId]);
            
            return [
                'success' => false,
                'error' => $error,
                'campaigns' => []
            ];
        } catch (\Exception $e) {
            $error = 'Campaign search error: ' . $e->getMessage();
            $this->logger->error($error, ['campaign_id' => $campaignId]);
            
            return [
                'success' => false,
                'error' => $error,
                'campaigns' => []
            ];
        }
    }
    
    /**
     * Create a new campaign in NetSuite
     */
    public function createCampaign($campaignData) {
        try {
            $this->logger->info('Creating campaign in NetSuite', $campaignData);
            
            $campaignUrl = rtrim($this->credentials['base_url'], '/') . '/services/rest/record/v1/campaign/';
            
            $response = $this->makeRequest('POST', $campaignUrl, $campaignData, true); // Use full URL
            
            if ($response->getStatusCode() == 201) {
                // Extract campaign ID from Location header
                $locationHeader = $response->getHeader('Location');
                $campaignId = null;
                
                if (!empty($locationHeader)) {
                    $location = $locationHeader[0];
                    $campaignId = basename($location);
                }
                
                $this->logger->info('Campaign created successfully', [
                    'campaign_id' => $campaignId,
                    'title' => $campaignData['title'] ?? 'N/A'
                ]);
                
                return [
                    'success' => true,
                    'campaign_id' => $campaignId
                ];
            }
            
            throw new \Exception('Unexpected status code: ' . $response->getStatusCode());
            
        } catch (RequestException $e) {
            $error = 'Campaign creation failed: ' . $e->getMessage();
            $this->logger->error($error, ['campaign_data' => $campaignData]);
            
            return [
                'success' => false,
                'error' => $error
            ];
        } catch (\Exception $e) {
            $error = 'Campaign creation error: ' . $e->getMessage();
            $this->logger->error($error, ['campaign_data' => $campaignData]);
            
            return [
                'success' => false,
                'error' => $error
            ];
        }
    }
    
    /**
     * Create a lead in NetSuite
     */
    public function createLead($leadData) {
        try {
            $this->logger->info('Creating lead in NetSuite', [
                'email' => $leadData['email'] ?? 'N/A',
                'hubspot_id' => $leadData['custentity_hs_vid'] ?? 'N/A'
            ]);
            
            $leadUrl = rtrim($this->credentials['base_url'], '/') . '/services/rest/record/v1/lead/';
            
            $response = $this->makeRequest('POST', $leadUrl, $leadData, true); // Use full URL
            
            if ($response->getStatusCode() == 201) {
                // Extract lead ID from Location header
                $locationHeader = $response->getHeader('Location');
                $leadId = null;
                
                if (!empty($locationHeader)) {
                    $location = $locationHeader[0];
                    $leadId = basename($location);
                }
                
                $this->logger->info('Lead created successfully', [
                    'lead_id' => $leadId,
                    'email' => $leadData['email'] ?? 'N/A',
                    'hubspot_id' => $leadData['custentity_hs_vid'] ?? 'N/A'
                ]);
                
                return [
                    'success' => true,
                    'lead_id' => $leadId
                ];
            }
            
            throw new \Exception('Unexpected status code: ' . $response->getStatusCode());
            
        } catch (RequestException $e) {
            $error = 'Lead creation failed: ' . $e->getMessage();
            $responseBody = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : 'No response body';
            
            $this->logger->error($error, [
                'lead_data' => $leadData,
                'response_body' => $responseBody
            ]);
            
            return [
                'success' => false,
                'error' => $error,
                'response_body' => $responseBody
            ];
        } catch (\Exception $e) {
            $error = 'Lead creation error: ' . $e->getMessage();
            $this->logger->error($error, ['lead_data' => $leadData]);
            
            return [
                'success' => false,
                'error' => $error
            ];
        }
    }
}