<?php

namespace Laguna\Integration\Controllers;

use Laguna\Integration\Services\ThreeDCartService;
use Laguna\Integration\Services\NetSuiteService;
use Laguna\Integration\Services\UnifiedEmailService;
use Laguna\Integration\Models\Order;
use Laguna\Integration\Models\Customer;
use Laguna\Integration\Utils\Logger;
use Laguna\Integration\Utils\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Order Controller
 * 
 * Handles manual order upload and processing functionality.
 */
class OrderController {
    private $threeDCartService;
    private $netSuiteService;
    private $emailService;
    private $logger;
    private $config;
    
    public function __construct() {
        $this->threeDCartService = new ThreeDCartService();
        $this->netSuiteService = new NetSuiteService();
        $this->emailService = new UnifiedEmailService();
        $this->logger = Logger::getInstance();
        $this->config = require __DIR__ . '/../../config/config.php';
    }
    
    /**
     * Handle manual file upload
     */
    public function handleFileUpload() {
        try {
            // Validate file upload
            if (!isset($_FILES['order_file'])) {
                throw new \Exception('No file uploaded');
            }
            
            $file = $_FILES['order_file'];
            $validationErrors = Validator::validateFileUpload($file);
            
            if (!empty($validationErrors)) {
                throw new \Exception('File validation failed: ' . implode(', ', $validationErrors));
            }
            
            // Move uploaded file
            $uploadPath = $this->config['upload']['upload_path'];
            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }
            
            $fileName = 'orders_' . date('Y-m-d_H-i-s') . '_' . basename($file['name']);
            $filePath = $uploadPath . $fileName;
            
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                throw new \Exception('Failed to save uploaded file');
            }
            
            $this->logger->info('File uploaded successfully', [
                'file_name' => $fileName,
                'file_size' => $file['size'],
                'file_path' => $filePath
            ]);
            
            // Process the uploaded file
            $result = $this->processUploadedFile($filePath);
            
            // Clean up file after processing
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            
            return $result;
            
        } catch (\Exception $e) {
            $this->logger->error('File upload failed', [
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Process uploaded file (CSV or Excel)
     */
    public function processUploadedFile($filePath) {
        try {
            $fileExtension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            
            if ($fileExtension === 'csv') {
                $orders = $this->parseCsvFile($filePath);
            } else {
                $orders = $this->parseExcelFile($filePath);
            }
            
            if (empty($orders)) {
                throw new \Exception('No valid orders found in file');
            }
            
            $this->logger->info('Parsed orders from file', [
                'file_path' => $filePath,
                'order_count' => count($orders)
            ]);
            
            // Process each order
            $results = $this->processParsedOrders($orders);
            
            // Send summary notification
            $this->sendUploadSummaryNotification($results, basename($filePath));
            
            return $results;
            
        } catch (\Exception $e) {
            $this->logger->error('File processing failed', [
                'file_path' => $filePath,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Parse CSV file
     */
    private function parseCsvFile($filePath) {
        $orders = [];
        $handle = fopen($filePath, 'r');
        
        if (!$handle) {
            throw new \Exception('Cannot open CSV file');
        }
        
        // Read header row
        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            throw new \Exception('Invalid CSV file - no headers found');
        }
        
        // Map headers to expected fields
        $mapping = $this->getCsvMapping();
        $headerMapping = [];
        
        foreach ($headers as $index => $header) {
            $normalizedHeader = strtolower(trim($header));
            if (isset($mapping[$normalizedHeader])) {
                $headerMapping[$index] = $mapping[$normalizedHeader];
            }
        }
        
        // Read data rows
        $rowNumber = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            
            try {
                $orderData = [];
                foreach ($row as $index => $value) {
                    if (isset($headerMapping[$index])) {
                        $orderData[$headerMapping[$index]] = trim($value);
                    }
                }
                
                if (!empty($orderData)) {
                    $orderData['_row_number'] = $rowNumber;
                    $orders[] = $this->normalizeOrderData($orderData);
                }
            } catch (\Exception $e) {
                $this->logger->warning('Skipping invalid row in CSV', [
                    'row_number' => $rowNumber,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        fclose($handle);
        return $orders;
    }
    
    /**
     * Parse Excel file
     */
    private function parseExcelFile($filePath) {
        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            
            if (empty($rows)) {
                throw new \Exception('Excel file is empty');
            }
            
            // Get headers from first row
            $headers = array_shift($rows);
            
            // Map headers to expected fields
            $mapping = $this->getCsvMapping();
            $headerMapping = [];
            
            foreach ($headers as $index => $header) {
                $normalizedHeader = strtolower(trim($header));
                if (isset($mapping[$normalizedHeader])) {
                    $headerMapping[$index] = $mapping[$normalizedHeader];
                }
            }
            
            $orders = [];
            $rowNumber = 1;
            
            foreach ($rows as $row) {
                $rowNumber++;
                
                try {
                    $orderData = [];
                    foreach ($row as $index => $value) {
                        if (isset($headerMapping[$index])) {
                            $orderData[$headerMapping[$index]] = trim($value);
                        }
                    }
                    
                    if (!empty($orderData)) {
                        $orderData['_row_number'] = $rowNumber;
                        $orders[] = $this->normalizeOrderData($orderData);
                    }
                } catch (\Exception $e) {
                    $this->logger->warning('Skipping invalid row in Excel', [
                        'row_number' => $rowNumber,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            return $orders;
            
        } catch (\Exception $e) {
            throw new \Exception('Failed to parse Excel file: ' . $e->getMessage());
        }
    }
    
    /**
     * Get CSV field mapping
     */
    private function getCsvMapping() {
        return [
            // Order fields
            'order_id' => 'OrderID',
            'orderid' => 'OrderID',
            'order number' => 'OrderID',
            'customer_id' => 'CustomerID',
            'customerid' => 'CustomerID',
            'customer id' => 'CustomerID',
            'order_date' => 'OrderDate',
            'orderdate' => 'OrderDate',
            'order date' => 'OrderDate',
            'date' => 'OrderDate',
            'order_status' => 'OrderStatusID',
            'status' => 'OrderStatusID',
            'order_total' => 'OrderAmount',
            'total' => 'OrderAmount',
            'order_amount' => 'OrderAmount',
            'discount' => 'OrderDiscount',
            'order_discount' => 'OrderDiscount',
            
            // Customer fields
            'billing_first_name' => 'BillingFirstName',
            'billing_firstname' => 'BillingFirstName',
            'first_name' => 'BillingFirstName',
            'firstname' => 'BillingFirstName',
            'billing_last_name' => 'BillingLastName',
            'billing_lastname' => 'BillingLastName',
            'last_name' => 'BillingLastName',
            'lastname' => 'BillingLastName',
            'billing_email' => 'BillingEmail',
            'email' => 'BillingEmail',
            'billing_company' => 'BillingCompany',
            'company' => 'BillingCompany',
            'billing_phone' => 'BillingPhoneNumber',
            'phone' => 'BillingPhoneNumber',
            
            // Billing address
            'billing_address' => 'BillingAddress',
            'billing_address1' => 'BillingAddress',
            'address' => 'BillingAddress',
            'billing_address2' => 'BillingAddress2',
            'billing_city' => 'BillingCity',
            'city' => 'BillingCity',
            'billing_state' => 'BillingState',
            'state' => 'BillingState',
            'billing_zip' => 'BillingZipCode',
            'billing_zipcode' => 'BillingZipCode',
            'zip' => 'BillingZipCode',
            'postal_code' => 'BillingZipCode',
            'billing_country' => 'BillingCountry',
            'country' => 'BillingCountry',
            
            // Shipping address
            'shipping_first_name' => 'ShippingFirstName',
            'shipping_firstname' => 'ShippingFirstName',
            'shipping_last_name' => 'ShippingLastName',
            'shipping_lastname' => 'ShippingLastName',
            'shipping_company' => 'ShippingCompany',
            'shipping_address' => 'ShippingAddress',
            'shipping_address1' => 'ShippingAddress',
            'shipping_address2' => 'ShippingAddress2',
            'shipping_city' => 'ShippingCity',
            'shipping_state' => 'ShippingState',
            'shipping_zip' => 'ShippingZipCode',
            'shipping_zipcode' => 'ShippingZipCode',
            'shipping_country' => 'ShippingCountry',
            
            // Item fields (for simple single-item orders)
            'item_name' => 'ItemName',
            'product_name' => 'ItemName',
            'item_sku' => 'CatalogID',
            'sku' => 'CatalogID',
            'catalog_id' => 'CatalogID',
            'quantity' => 'Quantity',
            'qty' => 'Quantity',
            'item_price' => 'ItemPrice',
            'price' => 'ItemPrice',
            'unit_price' => 'ItemPrice',
        ];
    }
    
    /**
     * Normalize order data from CSV/Excel
     */
    private function normalizeOrderData($data) {
        // Set default values
        $normalized = [
            'OrderID' => $data['OrderID'] ?? 'MANUAL_' . uniqid(),
            'CustomerID' => $data['CustomerID'] ?? 0,
            'OrderDate' => $data['OrderDate'] ?? date('Y-m-d H:i:s'),
            'OrderStatusID' => $data['OrderStatusID'] ?? 1,
            'OrderAmount' => (float)($data['OrderAmount'] ?? $data['OrderTotal'] ?? 0),
            'OrderDiscount' => (float)($data['OrderDiscount'] ?? 0),
            'BillingFirstName' => $data['BillingFirstName'] ?? '',
            'BillingLastName' => $data['BillingLastName'] ?? '',
            'BillingEmail' => $data['BillingEmail'] ?? '',
            'BillingCompany' => $data['BillingCompany'] ?? '',
            'BillingPhoneNumber' => $data['BillingPhoneNumber'] ?? '',
            'BillingAddress' => $data['BillingAddress'] ?? '',
            'BillingAddress2' => $data['BillingAddress2'] ?? '',
            'BillingCity' => $data['BillingCity'] ?? '',
            'BillingState' => $data['BillingState'] ?? '',
            'BillingZipCode' => $data['BillingZipCode'] ?? '',
            'BillingCountry' => $data['BillingCountry'] ?? 'US',
        ];
        
        // Copy shipping address from billing if not provided
        $shippingFields = [
            'ShippingFirstName' => 'BillingFirstName',
            'ShippingLastName' => 'BillingLastName',
            'ShippingCompany' => 'BillingCompany',
            'ShippingAddress' => 'BillingAddress',
            'ShippingAddress2' => 'BillingAddress2',
            'ShippingCity' => 'BillingCity',
            'ShippingState' => 'BillingState',
            'ShippingZipCode' => 'BillingZipCode',
            'ShippingCountry' => 'BillingCountry',
        ];
        
        foreach ($shippingFields as $shippingField => $billingField) {
            $normalized[$shippingField] = $data[$shippingField] ?? $normalized[$billingField];
        }
        
        // Create order item if item data is provided
        if (!empty($data['ItemName']) || !empty($data['CatalogID'])) {
            $normalized['OrderItemList'] = [[
                'CatalogID' => $data['CatalogID'] ?? 'UNKNOWN',
                'ItemName' => $data['ItemName'] ?? 'Manual Order Item',
                'Quantity' => (float)($data['Quantity'] ?? 1),
                'ItemPrice' => (float)($data['ItemPrice'] ?? $normalized['OrderAmount']),
                'ItemDescription' => $data['ItemDescription'] ?? $data['ItemName'] ?? '',
            ]];
        }
        
        return $normalized;
    }
    
    /**
     * Process parsed orders
     */
    private function processParsedOrders($orders) {
        $results = [
            'total' => count($orders),
            'successful' => 0,
            'failed' => 0,
            'errors' => [],
            'processed_orders' => []
        ];
        
        foreach ($orders as $orderData) {
            try {
                $orderId = $orderData['OrderID'];
                $rowNumber = $orderData['_row_number'] ?? 'unknown';
                
                $this->logger->info('Processing manual order', [
                    'order_id' => $orderId,
                    'row_number' => $rowNumber
                ]);
                
                // Create order object and validate
                $order = new Order($orderData);
                $validationErrors = $order->validate();
                
                if (!empty($validationErrors)) {
                    throw new \Exception('Validation failed: ' . implode(', ', $validationErrors));
                }
                
                // Get or create customer using new payment method-based logic
                $netSuiteCustomerId = $this->netSuiteService->findOrCreateCustomerByPaymentMethod($orderData);
                
                // Create sales order in NetSuite
                $netSuiteOrder = $this->netSuiteService->createSalesOrder($orderData, $netSuiteCustomerId);
                
                $results['successful']++;
                $results['processed_orders'][] = [
                    'order_id' => $orderId,
                    'netsuite_order_id' => $netSuiteOrder['id'],
                    'customer_id' => $netSuiteCustomerId,
                    'row_number' => $rowNumber
                ];
                
                // Update 3DCart order status to indicate successful processing
                $this->update3DCartOrderStatus($orderId, 'success', $netSuiteOrder['id']);
                
                $this->logger->info('Manual order processed successfully', [
                    'order_id' => $orderId,
                    'netsuite_order_id' => $netSuiteOrder['id'],
                    'row_number' => $rowNumber
                ]);
                
            } catch (\Exception $e) {
                $orderId = $orderData['OrderID'] ?? 'unknown';
                
                $results['failed']++;
                $results['errors'][] = [
                    'order_id' => $orderId,
                    'row_number' => $orderData['_row_number'] ?? 'unknown',
                    'error' => $e->getMessage()
                ];
                
                $this->logger->error('Manual order processing failed', [
                    'order_id' => $orderId,
                    'row_number' => $orderData['_row_number'] ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $results;
    }
    
    /**
     * Get or create customer (same logic as WebhookController)
     */
    private function getOrCreateCustomer(Customer $customer) {
        $email = $customer->getEmail();
        
        // Try to find existing customer by email if email is available
        if (!empty($email)) {
            $existingCustomer = $this->netSuiteService->findCustomerByEmail($email);
            
            if ($existingCustomer) {
                return $existingCustomer['id'];
            }
        } else {
            $this->logger->info('Customer has no valid email - will create new customer without email', [
                'customer_name' => $customer->getFullName()
            ]);
        }
        
        // Create new customer
        if ($this->config['order_processing']['auto_create_customers']) {
            $validationErrors = $customer->validate();
            if (!empty($validationErrors)) {
                throw new \Exception('Customer validation failed: ' . implode(', ', $validationErrors));
            }
            
            $newCustomer = $this->netSuiteService->createCustomer($customer->toNetSuiteFormat());
            return $newCustomer['id'];
        } else {
            $customerIdentifier = !empty($email) ? $email : $customer->getFullName();
            throw new \Exception("Customer not found and auto-creation is disabled: {$customerIdentifier}");
        }
    }
    
    /**
     * Send upload summary notification
     */
    private function sendUploadSummaryNotification($results, $fileName) {
        $subject = 'Manual Upload Processing Complete';
        $details = [
            'File Name' => $fileName,
            'Total Orders' => $results['total'],
            'Successful' => $results['successful'],
            'Failed' => $results['failed'],
            'Success Rate' => $results['total'] > 0 ? round(($results['successful'] / $results['total']) * 100, 2) . '%' : '0%'
        ];
        
        if (!empty($results['errors'])) {
            $details['First 5 Errors'] = implode('; ', array_slice(array_column($results['errors'], 'error'), 0, 5));
        }
        
        $this->emailService->sendOrderNotification('Manual Upload', $subject, $details);
    }
    
    /**
     * Update 3DCart order status after successful processing
     */
    private function update3DCartOrderStatus($orderId, $type, $netSuiteOrderId = null, $errorMessage = null) {
        try {
            // Only update status for successful processing
            if ($type !== 'success') {
                $this->logger->info('Skipping 3DCart status update - only success status updates are enabled (manual upload)', [
                    'order_id' => $orderId,
                    'type' => $type
                ]);
                return;
            }
            
            // Check if status updates are enabled
            if (!$this->config['order_processing']['update_3dcart_status']) {
                $this->logger->info('3DCart status updates disabled, skipping status update (manual upload)', [
                    'order_id' => $orderId,
                    'type' => $type
                ]);
                return;
            }
            
            // Set status to Processing (2) for successful orders
            $statusId = $this->config['order_processing']['success_status_id'];
            $comments = $this->config['order_processing']['status_comments'] 
                ? "Order successfully synced to NetSuite via manual upload. NetSuite Order ID: {$netSuiteOrderId}"
                : '';
            
            $this->logger->info('Updating 3DCart order status to Processing (manual upload)', [
                'order_id' => $orderId,
                'status_id' => $statusId,
                'netsuite_order_id' => $netSuiteOrderId
            ]);
            
            // Update the order status in 3DCart
            $result = $this->threeDCartService->updateOrderStatus($orderId, $statusId, $comments);
            
            $this->logger->info('3DCart order status updated successfully to Processing (manual upload)', [
                'order_id' => $orderId,
                'status_id' => $statusId,
                'netsuite_order_id' => $netSuiteOrderId,
                'result' => $result
            ]);
            
        } catch (\Exception $e) {
            // Log error but don't fail the entire process
            $this->logger->error('Failed to update 3DCart order status to Processing (manual upload)', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'netsuite_order_id' => $netSuiteOrderId
            ]);
            
            // Optionally send notification about status update failure
            $this->emailService->sendErrorNotification(
                "Failed to update 3DCart order status to Processing for Order #{$orderId} (manual upload): " . $e->getMessage(),
                [
                    'order_id' => $orderId,
                    'netsuite_order_id' => $netSuiteOrderId
                ]
            );
        }
    }
}