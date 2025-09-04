<?php
/**
 * AWS-specific Configuration
 * 
 * This configuration file is optimized for AWS deployment and includes
 * AWS-specific settings for database, logging, and file storage.
 */

// Load environment variables from AWS Systems Manager Parameter Store
function getSSMParameter($parameterName, $decrypt = false) {
    static $ssmClient = null;
    
    if ($ssmClient === null) {
        $ssmClient = new Aws\Ssm\SsmClient([
            'version' => 'latest',
            'region' => $_ENV['AWS_REGION'] ?? 'us-east-1'
        ]);
    }
    
    try {
        $result = $ssmClient->getParameter([
            'Name' => $parameterName,
            'WithDecryption' => $decrypt
        ]);
        
        return $result['Parameter']['Value'];
    } catch (Exception $e) {
        error_log("Failed to get SSM parameter {$parameterName}: " . $e->getMessage());
        return null;
    }
}

// AWS-specific environment detection
$isAWS = !empty($_ENV['AWS_REGION']) || !empty($_SERVER['AWS_REGION']);
$projectName = 'laguna-integrations';
$environment = $_ENV['ENVIRONMENT'] ?? 'production';

if ($isAWS) {
    // Load AWS SDK
    require_once __DIR__ . '/../../vendor/autoload.php';
    
    // Database configuration from SSM Parameter Store
    $dbConfig = [
        'host' => getSSMParameter("/{$projectName}/{$environment}/database/host"),
        'port' => getSSMParameter("/{$projectName}/{$environment}/database/port"),
        'database' => getSSMParameter("/{$projectName}/{$environment}/database/name"),
        'username' => getSSMParameter("/{$projectName}/{$environment}/database/username"),
        'password' => getSSMParameter("/{$projectName}/{$environment}/database/password", true),
    ];
} else {
    // Fallback to environment variables or defaults
    $dbConfig = [
        'host' => $_ENV['DB_HOST'] ?? 'localhost',
        'port' => $_ENV['DB_PORT'] ?? 3306,
        'database' => $_ENV['DB_NAME'] ?? 'laguna_integration',
        'username' => $_ENV['DB_USER'] ?? 'root',
        'password' => $_ENV['DB_PASS'] ?? '',
    ];
}

return [
    // Application Settings
    'app' => [
        'name' => 'Laguna Integrations',
        'version' => '1.0.0',
        'timezone' => 'America/New_York',
        'debug' => $_ENV['APP_DEBUG'] ?? false,
        'environment' => $environment,
        'is_aws' => $isAWS,
    ],

    // AWS-specific settings
    'aws' => [
        'region' => $_ENV['AWS_REGION'] ?? 'us-east-1',
        's3_bucket' => $_ENV['S3_BUCKET'] ?? null,
        'cloudwatch_log_group' => "/aws/ec2/{$projectName}-{$environment}",
        'parameter_store_prefix' => "/{$projectName}/{$environment}",
    ],

    // Logging Configuration (AWS CloudWatch optimized)
    'logging' => [
        'enabled' => true,
        'level' => $environment === 'production' ? 'info' : 'debug',
        'file' => __DIR__ . '/../../logs/app.log',
        'max_files' => 7, // Reduced for AWS (CloudWatch handles long-term storage)
        'cloudwatch' => [
            'enabled' => $isAWS,
            'log_group' => "/aws/ec2/{$projectName}-{$environment}",
            'log_stream' => gethostname() . '-' . date('Y-m-d'),
        ],
    ],

    // Database Configuration
    'database' => array_merge([
        'enabled' => true,
        'charset' => 'utf8mb4',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false, // For RDS SSL
        ],
    ], $dbConfig),

    // File Upload Settings (S3 optimized)
    'upload' => [
        'max_file_size' => 10 * 1024 * 1024, // 10MB
        'allowed_extensions' => ['csv', 'xlsx', 'xls'],
        'upload_path' => __DIR__ . '/../../uploads/',
        's3' => [
            'enabled' => $isAWS && !empty($_ENV['S3_BUCKET']),
            'bucket' => $_ENV['S3_BUCKET'] ?? null,
            'prefix' => 'uploads/',
        ],
    ],

    // Email Notification Settings
    'notifications' => [
        'enabled' => true,
        'from_email' => $_ENV['NOTIFICATION_FROM_EMAIL'] ?? 'noreply@lagunatools.com',
        'from_name' => $_ENV['NOTIFICATION_FROM_NAME'] ?? 'Laguna Integrations',
        'to_emails' => explode(',', $_ENV['NOTIFICATION_TO_EMAILS'] ?? 'seun_sodimu@lagunatools.com'),
        'subject_prefix' => '[Laguna Integrations] ',
        'ses' => [
            'enabled' => $isAWS,
            'region' => $_ENV['AWS_REGION'] ?? 'us-east-1',
        ],
    ],

    // Webhook Settings
    'webhook' => [
        'enabled' => $_ENV['WEBHOOK_ENABLED'] ?? true,
        'secret_key' => $_ENV['WEBHOOK_SECRET'] ?? 'your-webhook-secret-key',
        'timeout' => 30,
        'rate_limiting' => [
            'enabled' => true,
            'max_requests_per_minute' => 60,
        ],
    ],

    // Order Processing Settings
    'order_processing' => [
        'auto_create_customers' => true,
        'default_customer_type' => 'individual',
        'default_payment_terms' => 'Net 30',
        'retry_attempts' => 3,
        'retry_delay' => 5,
        'batch_size' => 10, // Process orders in batches for better performance
    ],

    // NetSuite Settings
    'netsuite' => [
        'default_subsidiary_id' => 1,
        'default_location_id' => 1,
        'default_department_id' => 3,
        'default_item_id' => 14238,
        'create_missing_items' => true,
        'item_type' => 'inventoryItem',
        'sales_order_taxable' => false,
        'tax_item_id' => 2,
        'shipping_item_id' => 3,
        'discount_item_id' => 4,
        'validate_totals' => true,
        'total_tolerance' => 0.01,
        'include_tax_as_line_item' => false,
        'include_shipping_as_line_item' => false,
        'include_discount_as_line_item' => false,
        'connection_timeout' => 30,
        'request_timeout' => 60,
    ],

    // HubSpot Settings
    'hubspot' => [
        'default_owner_id' => null, // Set to specific HubSpot user ID if needed
        'default_pipeline_id' => null, // Set to specific pipeline ID if needed
        'default_deal_stage' => 'appointmentscheduled', // Default deal stage
        'create_missing_contacts' => true,
        'update_existing_contacts' => true,
        'sync_custom_properties' => true,
        'connection_timeout' => 30,
        'request_timeout' => 60,
        'webhook_validation' => true,
        'batch_size' => 100, // HubSpot allows up to 100 records per batch
    ],

    // API Rate Limiting (AWS optimized)
    'rate_limiting' => [
        'netsuite_requests_per_minute' => 10,
        'threedcart_requests_per_minute' => 60,
        'hubspot_requests_per_minute' => 100, // HubSpot allows 100 requests per 10 seconds
        'cache_ttl' => 300, // 5 minutes
    ],

    // Security Settings
    'security' => [
        'session_timeout' => 3600, // 1 hour
        'max_login_attempts' => 5,
        'lockout_duration' => 900, // 15 minutes
        'password_min_length' => 8,
        'require_https' => $environment === 'production',
        'csrf_protection' => true,
    ],

    // Performance Settings
    'performance' => [
        'opcache_enabled' => function_exists('opcache_get_status'),
        'memory_limit' => '256M',
        'max_execution_time' => 300, // 5 minutes for webhook processing
        'gzip_compression' => true,
    ],

    // Monitoring and Health Checks
    'monitoring' => [
        'health_check_enabled' => true,
        'health_check_path' => '/status.php',
        'metrics' => [
            'enabled' => $isAWS,
            'namespace' => 'LagunaIntegration',
            'dimensions' => [
                'Environment' => $environment,
                'InstanceId' => $_ENV['AWS_INSTANCE_ID'] ?? gethostname(),
            ],
        ],
    ],

    // Backup and Recovery
    'backup' => [
        's3_backup_enabled' => $isAWS,
        'backup_retention_days' => 30,
        'backup_schedule' => '0 2 * * *', // Daily at 2 AM
    ],
];