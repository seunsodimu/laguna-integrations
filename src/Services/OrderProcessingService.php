<?php

namespace Laguna\Integration\Services;

use Laguna\Integration\Utils\Logger;

/**
 * Enhanced Order Processing Service
 * 
 * Handles the complete order processing workflow with enhanced customer search
 * and parent customer assignment functionality for 3DCart to NetSuite integration.
 */
class OrderProcessingService {
    private $netsuiteService;
    private $logger;
    private $config;
    
    public function __construct() {
        $this->netsuiteService = new NetSuiteService();
        $this->logger = Logger::getInstance();
        $this->config = require __DIR__ . '/../../config/config.php';
    }
    
    /**
     * Process a 3DCart order with enhanced customer handling
     */
    public function processOrder($orderData) {
        try {
            $this->logger->info('Starting enhanced order processing', [
                'order_id' => $orderData['OrderID'],
                'customer_email' => $orderData['BillingEmailAddress'] ?? 'unknown'
            ]);

            // Step 1: Extract customer information from order
            $customerInfo = $this->extractCustomerInfo($orderData);
            
            // Step 2: Find or create customer with parent relationship
            $customerId = $this->findOrCreateCustomer($customerInfo, $orderData);
            
            // Step 3: Create sales order with enhanced features
            $salesOrder = $this->createSalesOrder($orderData, $customerId);
            
            $this->logger->info('Order processing completed successfully', [
                'order_id' => $orderData['OrderID'],
                'customer_id' => $customerId,
                'sales_order_id' => $salesOrder['id'] ?? 'unknown'
            ]);
            
            return [
                'success' => true,
                'customer_id' => $customerId,
                'sales_order' => $salesOrder,
                'order_id' => $orderData['OrderID']
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Order processing failed', [
                'order_id' => $orderData['OrderID'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'order_id' => $orderData['OrderID']
            ];
        }
    }
    
    /**
     * Extract customer information from 3DCart order data
     */
    private function extractCustomerInfo($orderData) {
        $customerInfo = [
            'billing_email' => $orderData['BillingEmailAddress'] ?? '',
            'billing_phone' => $orderData['BillingPhoneNumber'] ?? '',
            'firstname' => $orderData['BillingFirstName'] ?? '',
            'lastname' => $orderData['BillingLastName'] ?? '',
            'company' => $orderData['BillingCompany'] ?? '',
            'phone' => $orderData['BillingPhoneNumber'] ?? '',
            
            // Add raw billing fields for new address methods
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
            
            // Add ShipmentList for shipping address
            'ShipmentList' => $orderData['ShipmentList'] ?? []
        ];

        // Extract customer email from QuestionList (QuestionID=1)
        $customerEmail = '';
        if (isset($orderData['QuestionList']) && is_array($orderData['QuestionList'])) {
            foreach ($orderData['QuestionList'] as $question) {
                if (isset($question['QuestionID']) && $question['QuestionID'] == 1) {
                    $customerEmail = $question['QuestionAnswer'] ?? '';
                    break;
                }
            }
        }

        // Use customer email from QuestionList if available, otherwise use billing email
        $customerInfo['email'] = !empty($customerEmail) ? $customerEmail : $customerInfo['billing_email'];
        $customerInfo['second_email'] = $customerInfo['email']; // For custentity2nd_email_address field

        $this->logger->info('Extracted customer information', [
            'order_id' => $orderData['OrderID'],
            'customer_email' => $customerInfo['email'],
            'billing_email' => $customerInfo['billing_email'],
            'billing_phone' => $customerInfo['billing_phone'],
            'email_source' => !empty($customerEmail) ? 'QuestionList' : 'BillingEmailAddress'
        ]);

        return $customerInfo;
    }
    
    /**
     * Find or create customer using the new BillingPaymentMethod-based logic
     */
    private function findOrCreateCustomer($customerInfo, $orderData) {
        try {
            $this->logger->info('Using new payment method-based customer assignment logic', [
                'order_id' => $orderData['OrderID'],
                'payment_method' => $orderData['BillingPaymentMethod'] ?? 'N/A'
            ]);

            // Use the new payment method-based logic
            $customerId = $this->netsuiteService->findOrCreateCustomerByPaymentMethod($orderData);
            
            $this->logger->info('Customer assignment completed using new logic', [
                'order_id' => $orderData['OrderID'],
                'customer_id' => $customerId,
                'payment_method' => $orderData['BillingPaymentMethod'] ?? 'N/A'
            ]);
            
            return $customerId;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to find or create customer using new logic', [
                'order_id' => $orderData['OrderID'],
                'payment_method' => $orderData['BillingPaymentMethod'] ?? 'N/A',
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Create sales order with enhanced features
     */
    private function createSalesOrder($orderData, $customerId) {
        try {
            // Prepare sales order options
            $options = [
                'is_taxable' => $this->config['netsuite']['sales_order_taxable'] ?? false
            ];

            // Create the sales order
            $salesOrder = $this->netsuiteService->createSalesOrder($orderData, $customerId, $options);
            
            $this->logger->info('Created sales order with enhanced features', [
                'order_id' => $orderData['OrderID'],
                'customer_id' => $customerId,
                'sales_order_id' => $salesOrder['id'] ?? 'unknown',
                'is_taxable' => $options['is_taxable']
            ]);
            
            return $salesOrder;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to create sales order', [
                'order_id' => $orderData['OrderID'],
                'customer_id' => $customerId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Process multiple orders in batch
     */
    public function processBatchOrders($orders) {
        $results = [];
        $successCount = 0;
        $failureCount = 0;
        
        $this->logger->info('Starting batch order processing', [
            'total_orders' => count($orders)
        ]);
        
        foreach ($orders as $index => $orderData) {
            try {
                $result = $this->processOrder($orderData);
                $results[] = $result;
                
                if ($result['success']) {
                    $successCount++;
                } else {
                    $failureCount++;
                }
                
                // Add small delay between orders to avoid rate limiting
                if ($index < count($orders) - 1) {
                    usleep(500000); // 0.5 second delay
                }
                
            } catch (\Exception $e) {
                $failureCount++;
                $results[] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'order_id' => $orderData['OrderID'] ?? 'unknown'
                ];
            }
        }
        
        $this->logger->info('Batch order processing completed', [
            'total_orders' => count($orders),
            'successful' => $successCount,
            'failed' => $failureCount
        ]);
        
        return [
            'total_orders' => count($orders),
            'successful' => $successCount,
            'failed' => $failureCount,
            'results' => $results
        ];
    }
    
    /**
     * Get processing statistics
     */
    public function getProcessingStats() {
        // This could be enhanced to pull from database or log files
        return [
            'orders_processed_today' => 0, // Placeholder
            'customers_created_today' => 0, // Placeholder
            'success_rate' => 0.0, // Placeholder
            'last_processed' => null // Placeholder
        ];
    }
}