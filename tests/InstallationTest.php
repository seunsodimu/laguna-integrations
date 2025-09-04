<?php

use PHPUnit\Framework\TestCase;

/**
 * Installation Test
 * 
 * Basic tests to verify the installation is working correctly.
 */
class InstallationTest extends TestCase {
    
    public function testAutoloaderWorks() {
        // Test that autoloader is working
        $this->assertTrue(class_exists('Laguna\Integration\Controllers\WebhookController'));
        $this->assertTrue(class_exists('Laguna\Integration\Services\ThreeDCartService'));
        $this->assertTrue(class_exists('Laguna\Integration\Models\Order'));
    }
    
    public function testConfigurationFileExists() {
        $configFile = __DIR__ . '/../config/config.php';
        $this->assertFileExists($configFile, 'Main configuration file should exist');
        
        $config = require $configFile;
        $this->assertIsArray($config, 'Configuration should return an array');
        $this->assertArrayHasKey('app', $config, 'Configuration should have app section');
    }
    
    public function testCredentialsFileExists() {
        $credentialsFile = __DIR__ . '/../config/credentials.php';
        $this->assertFileExists($credentialsFile, 'Credentials file should exist');
        
        $credentials = require $credentialsFile;
        $this->assertIsArray($credentials, 'Credentials should return an array');
        $this->assertArrayHasKey('3dcart', $credentials, 'Credentials should have 3dcart section');
        $this->assertArrayHasKey('netsuite', $credentials, 'Credentials should have netsuite section');
        $this->assertArrayHasKey('sendgrid', $credentials, 'Credentials should have sendgrid section');
    }
    
    public function testDirectoriesExist() {
        $logsDir = __DIR__ . '/../logs';
        $uploadsDir = __DIR__ . '/../uploads';
        
        $this->assertDirectoryExists($logsDir, 'Logs directory should exist');
        $this->assertDirectoryExists($uploadsDir, 'Uploads directory should exist');
        
        $this->assertTrue(is_writable($logsDir), 'Logs directory should be writable');
        $this->assertTrue(is_writable($uploadsDir), 'Uploads directory should be writable');
    }
    
    public function testPublicFilesExist() {
        $publicDir = __DIR__ . '/../public';
        
        $this->assertFileExists($publicDir . '/index.php', 'Main dashboard file should exist');
        $this->assertFileExists($publicDir . '/webhook.php', 'Webhook endpoint should exist');
        $this->assertFileExists($publicDir . '/status.php', 'Status page should exist');
        $this->assertFileExists($publicDir . '/upload.php', 'Upload page should exist');
    }
    
    public function testRequiredPHPExtensions() {
        $requiredExtensions = ['curl', 'json', 'openssl', 'mbstring'];
        
        foreach ($requiredExtensions as $extension) {
            $this->assertTrue(
                extension_loaded($extension),
                "Required PHP extension '{$extension}' should be loaded"
            );
        }
    }
    
    public function testComposerDependencies() {
        $vendorDir = __DIR__ . '/../vendor';
        $this->assertDirectoryExists($vendorDir, 'Vendor directory should exist');
        
        $autoloadFile = $vendorDir . '/autoload.php';
        $this->assertFileExists($autoloadFile, 'Composer autoload file should exist');
        
        // Test that key dependencies are available
        $this->assertTrue(class_exists('GuzzleHttp\Client'), 'Guzzle HTTP client should be available');
        $this->assertTrue(class_exists('Monolog\Logger'), 'Monolog logger should be available');
        $this->assertTrue(class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet'), 'PhpSpreadsheet should be available');
    }
    
    public function testLoggerCanBeInstantiated() {
        $logger = \Laguna\Integration\Utils\Logger::getInstance();
        $this->assertInstanceOf(\Laguna\Integration\Utils\Logger::class, $logger);
    }
    
    public function testServicesCanBeInstantiated() {
        // Test that service classes can be instantiated without errors
        $threeDCartService = new \Laguna\Integration\Services\ThreeDCartService();
        $this->assertInstanceOf(\Laguna\Integration\Services\ThreeDCartService::class, $threeDCartService);
        
        $netSuiteService = new \Laguna\Integration\Services\NetSuiteService();
        $this->assertInstanceOf(\Laguna\Integration\Services\NetSuiteService::class, $netSuiteService);
        
        $emailService = new \Laguna\Integration\Services\EmailService();
        $this->assertInstanceOf(\Laguna\Integration\Services\EmailService::class, $emailService);
    }
    
    public function testModelsCanBeInstantiated() {
        // Test basic model instantiation
        $orderData = [
            'OrderID' => 'TEST123',
            'CustomerID' => '1',
            'OrderDate' => date('Y-m-d H:i:s'),
            'OrderTotal' => 99.99,
            'BillingFirstName' => 'Test',
            'BillingLastName' => 'User',
            'BillingEmail' => 'test@example.com'
        ];
        
        $order = new \Laguna\Integration\Models\Order($orderData);
        $this->assertInstanceOf(\Laguna\Integration\Models\Order::class, $order);
        $this->assertEquals('TEST123', $order->getOrderId());
        
        $customer = \Laguna\Integration\Models\Customer::fromOrderData($orderData);
        $this->assertInstanceOf(\Laguna\Integration\Models\Customer::class, $customer);
        $this->assertEquals('test@example.com', $customer->getEmail());
    }
}