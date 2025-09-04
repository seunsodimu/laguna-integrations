<?php

namespace Laguna\Integration\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Laguna\Integration\Utils\Logger;

/**
 * 3DCart API Service
 * 
 * Handles all interactions with the 3DCart REST API.
 * Documentation: https://apirest.3dcart.com/v2/getting-started/index.html
 */
class ThreeDCartService {
    private $client;
    private $credentials;
    private $logger;
    private $baseUrl;
    
    public function __construct() {
        $credentials = require __DIR__ . '/../../config/credentials.php';
        $this->credentials = $credentials['3dcart'];
        $this->logger = Logger::getInstance();
        
        // Use the correct 3DCart API base URL
        $this->baseUrl = 'https://apirest.3dcart.com/3dCartWebAPI/v2/';
        
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 30,
            'verify' => false, // Disable SSL verification for development - CHANGE IN PRODUCTION
            'allow_redirects' => [
                'max' => 10,
                'strict' => true,
                'referer' => true,
                'track_redirects' => true
            ],
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => '3DCart-Integration/1.0 (PHP/' . PHP_VERSION . ')',
                'SecureURL' => $this->credentials['secure_url'],
                'PrivateKey' => $this->credentials['private_key'],
                'Token' => $this->credentials['token'],
                'Authorization' => 'Bearer ' . $this->credentials['bearer_token'],
            ]
        ]);
    }
    
    /**
     * Test connection to 3DCart API
     */
    public function testConnection() {
        try {
            $this->logger->info("Testing 3DCart connection to: " . $this->baseUrl);
            
            // Add a small delay to avoid rate limiting
            usleep(500000); // 0.5 seconds
            
            $startTime = microtime(true);
            $response = $this->client->get('Orders', [
                'query' => ['limit' => 1]
            ]);
            $duration = (microtime(true) - $startTime) * 1000;
            
            $this->logger->logApiCall('3DCart', '/Orders', 'GET', $response->getStatusCode(), $duration);
            
            // Log redirect information if available
            if ($response->hasHeader('X-Guzzle-Redirect-History')) {
                $redirects = $response->getHeader('X-Guzzle-Redirect-History');
                $this->logger->info("3DCart API redirects: " . implode(' -> ', $redirects));
            }
            
            return [
                'success' => true,
                'status_code' => $response->getStatusCode(),
                'response_time' => round($duration, 2) . 'ms',
                'final_url' => $this->baseUrl . '/Orders'
            ];
        } catch (RequestException $e) {
            $errorDetails = [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'request_url' => $this->baseUrl . '/Orders',
                'headers' => [
                    'PrivateKey' => $this->credentials['private_key'],
                    'Token' => $this->credentials['token'],
                    'SecureURL' => $this->credentials['store_url']
                ]
            ];
            
            if ($e->getResponse()) {
                $errorDetails['response_status'] = $e->getResponse()->getStatusCode();
                $errorDetails['response_body'] = $e->getResponse()->getBody()->getContents();
            }
            
            $this->logger->error('3DCart connection test failed', $errorDetails);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'status_code' => $e->getResponse() ? $e->getResponse()->getStatusCode() : null,
                'details' => $errorDetails
            ];
        }
    }
    
    /**
     * Get order by ID
     */
    public function getOrder($orderId) {
        try {
            $startTime = microtime(true);
            $response = $this->client->get("Orders/{$orderId}");
            $duration = (microtime(true) - $startTime) * 1000;
            
            $this->logger->logApiCall('3DCart', "/Orders/{$orderId}", 'GET', $response->getStatusCode(), $duration);
            
            $responseData = json_decode($response->getBody()->getContents(), true);
            
            // 3DCart API returns an array with the order as the first element
            $orderData = is_array($responseData) && isset($responseData[0]) ? $responseData[0] : $responseData;
            
            $this->logger->info('Retrieved order from 3DCart', [
                'order_id' => $orderId,
                'customer_id' => $orderData['CustomerID'] ?? null
            ]);
            
            return $orderData;
        } catch (RequestException $e) {
            $this->logger->error('Failed to retrieve order from 3DCart', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
            throw new \Exception("Failed to retrieve order {$orderId}: " . $e->getMessage());
        }
    }
    
    /**
     * Get customer by ID
     */
    public function getCustomer($customerId) {
        try {
            $startTime = microtime(true);
            $response = $this->client->get("Customers/{$customerId}");
            $duration = (microtime(true) - $startTime) * 1000;
            
            $this->logger->logApiCall('3DCart', "/Customers/{$customerId}", 'GET', $response->getStatusCode(), $duration);
            
            $customerData = json_decode($response->getBody()->getContents(), true);
            
            $this->logger->info('Retrieved customer from 3DCart', [
                'customer_id' => $customerId,
                'email' => $customerData['Email'] ?? null
            ]);
            
            return $customerData;
        } catch (RequestException $e) {
            $this->logger->error('Failed to retrieve customer from 3DCart', [
                'customer_id' => $customerId,
                'error' => $e->getMessage()
            ]);
            throw new \Exception("Failed to retrieve customer {$customerId}: " . $e->getMessage());
        }
    }
    
    /**
     * Get orders with filters
     */
    public function getOrders($filters = []) {
        try {
            $queryParams = array_merge([
                'limit' => 50,
                'offset' => 0
            ], $filters);
            
            $startTime = microtime(true);
            $response = $this->client->get('Orders', [
                'query' => $queryParams
            ]);
            $duration = (microtime(true) - $startTime) * 1000;
            
            $this->logger->logApiCall('3DCart', '/Orders', 'GET', $response->getStatusCode(), $duration);
            
            $ordersData = json_decode($response->getBody()->getContents(), true);
            
            $this->logger->info('Retrieved orders from 3DCart', [
                'count' => count($ordersData),
                'filters' => $filters
            ]);
            
            return $ordersData;
        } catch (RequestException $e) {
            $this->logger->error('Failed to retrieve orders from 3DCart', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            throw new \Exception("Failed to retrieve orders: " . $e->getMessage());
        }
    }
    
    /**
     * Update order status
     */
    public function updateOrderStatus($orderId, $statusId, $comments = '') {
        try {
            $updateData = [
                'OrderStatusID' => $statusId
            ];
            
            if (!empty($comments)) {
                $updateData['InternalComments'] = $comments;
            }
            
            $startTime = microtime(true);
            $response = $this->client->put("Orders/{$orderId}", [
                'json' => $updateData
            ]);
            $duration = (microtime(true) - $startTime) * 1000;
            
            $this->logger->logApiCall('3DCart', "/Orders/{$orderId}", 'PUT', $response->getStatusCode(), $duration);
            
            $this->logger->info('Updated order status in 3DCart', [
                'order_id' => $orderId,
                'status_id' => $statusId,
                'comments' => $comments
            ]);
            
            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            $this->logger->error('Failed to update order status in 3DCart', [
                'order_id' => $orderId,
                'status_id' => $statusId,
                'error' => $e->getMessage()
            ]);
            throw new \Exception("Failed to update order status: " . $e->getMessage());
        }
    }
    
    /**
     * Verify webhook signature (if applicable)
     */
    public function verifyWebhookSignature($payload, $signature, $secret = null) {
        if (!$secret) {
            $config = require __DIR__ . '/../../config/config.php';
            $secret = $config['webhook']['secret_key'];
        }
        
        $expectedSignature = hash_hmac('sha256', $payload, $secret);
        
        return hash_equals($expectedSignature, $signature);
    }
    
    /**
     * Process webhook payload
     */
    public function processWebhookPayload($payload) {
        $this->logger->logWebhook('3DCart', 'order_webhook', [
            'payload_size' => strlen($payload)
        ]);
        
        try {
            $data = json_decode($payload, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON payload: ' . json_last_error_msg());
            }
            
            // Handle both array and object formats from 3DCart webhooks
            // Sometimes 3DCart sends [{...}] and sometimes {...}
            if (is_array($data) && isset($data[0]) && is_array($data[0])) {
                // Array format: [{OrderID: 123, ...}]
                $orderData = $data[0];
            } else {
                // Object format: {OrderID: 123, ...}
                $orderData = $data;
            }
            
            // Validate required webhook fields
            if (!isset($orderData['OrderID'])) {
                throw new \Exception('Missing OrderID in webhook payload');
            }
            
            $this->logger->info('Processed 3DCart webhook', [
                'order_id' => $orderData['OrderID'],
                'event_type' => $orderData['EventType'] ?? 'unknown',
                'format' => is_array($data) && isset($data[0]) ? 'array' : 'object'
            ]);
            
            return $orderData;
        } catch (\Exception $e) {
            $this->logger->error('Failed to process 3DCart webhook payload', [
                'error' => $e->getMessage(),
                'payload' => substr($payload, 0, 500) // Log first 500 chars for debugging
            ]);
            throw $e;
        }
    }
    
    /**
     * Get order status name from status ID
     */
    public function getOrderStatusName($statusId) {
        $statusMap = [
            1 => 'New',
            2 => 'Processing',
            3 => 'Partial',
            4 => 'Shipped',
            5 => 'Cancelled',
            6 => 'Not Completed',
            7 => 'Unpaid',
            8 => 'Backordered',
            9 => 'Pending Review',
            10 => 'Partially Shipped'
        ];
        
        return $statusMap[$statusId] ?? 'Unknown';
    }
    
    /**
     * Get orders by date range
     */
    public function getOrdersByDateRange($startDate, $endDate, $status = '') {
        try {
            // Convert date format from YYYY-MM-DD to MM/dd/yyyy HH:mm:ss
            $startDateTime = \DateTime::createFromFormat('Y-m-d', $startDate);
            $endDateTime = \DateTime::createFromFormat('Y-m-d', $endDate);
            
            if (!$startDateTime || !$endDateTime) {
                throw new \Exception('Invalid date format. Expected YYYY-MM-DD');
            }
            
            // Format dates as required by 3DCart API: MM/dd/yyyy HH:mm:ss
            $formattedStartDate = $startDateTime->format('m/d/Y') . ' 01:00:00';
            $formattedEndDate = $endDateTime->format('m/d/Y') . ' 23:59:00';
            
            $params = [
                'datestart' => $formattedStartDate,
                'dateend' => $formattedEndDate,
                'limit' => 100, // Limit to prevent memory issues
                'offset' => 0
            ];
            
            if (!empty($status)) {
                $params['orderstatus'] = $status;
            }
            
            $this->logger->info('Fetching orders by date range', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'formatted_start' => $formattedStartDate,
                'formatted_end' => $formattedEndDate,
                'status' => $status
            ]);
            
            $response = $this->client->get('Orders', [
                'query' => $params
            ]);
            
            $orders = json_decode($response->getBody()->getContents(), true);
            
            // Handle both array and single object responses
            if (!is_array($orders)) {
                $orders = [];
            } elseif (isset($orders['OrderID'])) {
                // Single order returned as object
                $orders = [$orders];
            }
            
            $this->logger->info('Retrieved orders from 3DCart', [
                'count' => count($orders),
                'date_range' => "$startDate to $endDate"
            ]);
            
            return $orders;
            
        } catch (RequestException $e) {
            $this->logger->error('Failed to retrieve orders by date range', [
                'error' => $e->getMessage(),
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);
            throw new \Exception('Failed to fetch orders from 3DCart: ' . $e->getMessage());
        }
    }
    
    /**
     * Get order statuses
     */
    public function getOrderStatuses() {
        try {
            $response = $this->client->get('OrderStatuses');
            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            $this->logger->error('Failed to retrieve order statuses', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
}