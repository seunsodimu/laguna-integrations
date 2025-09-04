<?php
/**
 * Main Configuration File
 * 
 * This file contains the main configuration settings for the 3DCart to NetSuite integration.
 */

// Load environment variables if .env file exists
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

// Load credentials
require_once __DIR__ . '/credentials.php';

return [
    // Application Settings
    'app' => [
        'name' => 'Laguna Integrations Platform',
        'version' => '2.0.0',
        'timezone' => 'America/New_York',
        'debug' => $_ENV['APP_DEBUG'] ?? false,
    ],

    // Integration Modules Configuration
    'integrations' => [
        '3dcart_netsuite' => [
            'enabled' => true,
            'name' => '3DCart to NetSuite',
            'description' => 'Automated order processing from 3DCart to NetSuite',
            'webhook_endpoint' => 'webhook.php',
            'status_page' => 'status.php'
        ],
        'hubspot_netsuite' => [
            'enabled' => true,
            'name' => 'HubSpot to NetSuite',
            'description' => 'Lead synchronization from HubSpot to NetSuite',
            'webhook_endpoint' => 'hubspot-webhook.php',
            'status_page' => 'hubspot-status.php'
        ]
    ],

    // Logging Configuration
    'logging' => [
        'enabled' => true,
        'level' => 'info', // debug, info, warning, error
        'file' => __DIR__ . '/../logs/app.log',
        'max_files' => 30,
    ],

    // Database Configuration (required for user authentication)
    'database' => [
        'enabled' => true,
        'host' => $_ENV['DB_HOST'] ?? 'localhost',
        'port' => $_ENV['DB_PORT'] ?? 3306,
       'database' => $_ENV['DB_NAME'] ?? 'integration_db',
        'username' => $_ENV['DB_USER'] ?? 'integration_admin',
        'password' => $_ENV['DB_PASS'] ?? 'Lme2,T%pDH1W',
        'charset' => 'utf8mb4',
    ],

    // File Upload Settings
    'upload' => [
        'max_file_size' => 10 * 1024 * 1024, // 10MB
        'allowed_extensions' => ['csv', 'xlsx', 'xls'],
        'upload_path' => __DIR__ . '/../uploads/',
    ],

    // Email Notification Settings
    'notifications' => [
        'enabled' => true,
        'from_email' => $_ENV['NOTIFICATION_FROM_EMAIL'] ?? 'noreply@lagunatools.com',
        'from_name' => $_ENV['NOTIFICATION_FROM_NAME'] ?? '3DCart Integration',
        'to_emails' => explode(',', $_ENV['NOTIFICATION_TO_EMAILS'] ?? 'seun_sodimu@lagunatools.com'),
        'subject_prefix' => '[3DCart Integration] ',
    ],

    // Webhook Settings
    'webhook' => [
        'enabled' => $_ENV['WEBHOOK_ENABLED'] ?? false, // Set to false to disable webhook processing
        'secret_key' => $_ENV['WEBHOOK_SECRET'] ?? 'your-webhook-secret-key',
        'timeout' => 30, // seconds
    ],

    // Order Processing Settings
    'order_processing' => [
        'auto_create_customers' => true,
        'default_customer_type' => 'individual',
        'default_payment_terms' => 'Net 30',
        'retry_attempts' => 3,
        'retry_delay' => 5, // seconds
        
        // 3DCart Order Status Updates
        'update_3dcart_status' => $_ENV['UPDATE_3DCART_STATUS'] ?? true, // Enable/disable status updates
        'success_status_id' => $_ENV['SUCCESS_STATUS_ID'] ?? 2, // Status ID for successfully processed orders (2 = Processing)
        'status_comments' => $_ENV['STATUS_COMMENTS'] ?? true, // Add comments when updating status
    ],

    // NetSuite Settings
    'netsuite' => [
        'default_subsidiary_id' => 1,
        'default_location_id' => 1,
        'default_department_id' => 3, // Required department ID for sales orders
        'default_item_id' => 14238, // Fallback item ID when item creation fails (using valid NetSuite item ID)
        'create_missing_items' => true,
        'item_type' => 'inventoryItem', // Type of items to create (inventoryItem, noninventoryItem, serviceItem)
        'sales_order_taxable' => false, // Global setting for sales order tax calculation (true/false)
        
        // Order Amount Handling
        'tax_item_id' => 2, // NetSuite item ID for tax line items
        'shipping_item_id' => 3, // NetSuite item ID for shipping line items
        'discount_item_id' => 4, // NetSuite item ID for discount line items
        'validate_totals' => true, // Enable order total validation
        'total_tolerance' => 0.01, // Tolerance for total differences (in currency units)
        'include_tax_as_line_item' => false, // Add tax as separate line item (DISABLED - invalid item ID)
        'include_shipping_as_line_item' => false, // Add shipping as separate line item (DISABLED - invalid item ID)
        'include_discount_as_line_item' => false, // Add discount as separate line item (DISABLED - invalid item ID)
    ],

    // API Rate Limiting
    'rate_limiting' => [
        'netsuite_requests_per_minute' => 10,
        'threedcart_requests_per_minute' => 60,
    ],
];