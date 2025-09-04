<?php
/**
 * Test Email Provider Selection
 * 
 * This script tests that the application correctly uses the selected email provider
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Services\UnifiedEmailService;
use Laguna\Integration\Services\EmailServiceFactory;
use Laguna\Integration\Middleware\AuthMiddleware;

// Start output buffering
ob_start();

// Set JSON content type
header('Content-Type: application/json');

try {
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
    
    $response = [
        'success' => true,
        'steps' => []
    ];
    
    $response['steps'][] = 'Testing email provider selection and configuration...';
    
    // Step 1: Check current configuration
    $response['steps'][] = 'Step 1: Checking email provider configuration...';
    
    try {
        $credentials = require __DIR__ . '/../config/credentials.php';
        $configuredProvider = $credentials['email']['provider'] ?? 'sendgrid';
        
        $response['configured_provider'] = $configuredProvider;
        $response['steps'][] = "✅ Configured email provider: {$configuredProvider}";
        
        // Check if credentials are configured for the selected provider
        $providerCredentials = $credentials['email'][$configuredProvider] ?? [];
        $hasApiKey = !empty($providerCredentials['api_key']) && 
                     $providerCredentials['api_key'] !== 'your-sendgrid-api-key' && 
                     $providerCredentials['api_key'] !== 'your-brevo-api-key' &&
                     $providerCredentials['api_key'] !== 'your-brevo-api-key-here';
        
        $response['provider_credentials'] = [
            'has_api_key' => $hasApiKey,
            'from_email' => $providerCredentials['from_email'] ?? 'Not configured',
            'from_name' => $providerCredentials['from_name'] ?? 'Not configured'
        ];
        
        if ($hasApiKey) {
            $response['steps'][] = "✅ API key configured for {$configuredProvider}";
        } else {
            $response['steps'][] = "❌ API key not configured for {$configuredProvider}";
        }
        
    } catch (Exception $e) {
        $response['steps'][] = "❌ Error reading configuration: " . $e->getMessage();
        throw $e;
    }
    
    // Step 2: Test EmailServiceFactory
    $response['steps'][] = 'Step 2: Testing EmailServiceFactory...';
    
    try {
        $availableProviders = EmailServiceFactory::getAvailableProviders();
        $currentProvider = EmailServiceFactory::getCurrentProvider();
        
        $response['available_providers'] = $availableProviders;
        $response['current_provider'] = $currentProvider;
        
        $response['steps'][] = "✅ Available providers: " . implode(', ', array_keys($availableProviders));
        $response['steps'][] = "✅ Current provider: {$currentProvider['name']} ({$currentProvider['class']})";
        
        // Create email service instance
        $emailService = EmailServiceFactory::create();
        $response['steps'][] = "✅ Email service instance created: " . get_class($emailService);
        
    } catch (Exception $e) {
        $response['steps'][] = "❌ Error with EmailServiceFactory: " . $e->getMessage();
        throw $e;
    }
    
    // Step 3: Test UnifiedEmailService
    $response['steps'][] = 'Step 3: Testing UnifiedEmailService...';
    
    try {
        $unifiedEmailService = new UnifiedEmailService();
        $providerInfo = $unifiedEmailService->getProviderInfo();
        
        $response['unified_service_provider'] = $providerInfo;
        $response['steps'][] = "✅ UnifiedEmailService created successfully";
        $response['steps'][] = "✅ Using provider: {$providerInfo['name']} ({$providerInfo['class']})";
        
        // Test connection
        $connectionTest = $unifiedEmailService->testConnection();
        $response['connection_test'] = $connectionTest;
        
        if ($connectionTest['success']) {
            $response['steps'][] = "✅ Connection test successful for {$providerInfo['name']}";
        } else {
            $response['steps'][] = "❌ Connection test failed for {$providerInfo['name']}: " . ($connectionTest['error'] ?? 'Unknown error');
        }
        
    } catch (Exception $e) {
        $response['steps'][] = "❌ Error with UnifiedEmailService: " . $e->getMessage();
        $response['connection_test'] = [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
    
    // Step 4: Test all available providers
    $response['steps'][] = 'Step 4: Testing all available providers...';
    
    try {
        $allProvidersStatus = EmailServiceFactory::testAllProviders();
        $response['all_providers_status'] = $allProvidersStatus;
        
        foreach ($allProvidersStatus as $provider => $status) {
            if ($status['success']) {
                $response['steps'][] = "✅ {$provider}: Connection successful";
            } else {
                $response['steps'][] = "❌ {$provider}: " . ($status['error'] ?? 'Connection failed');
            }
        }
        
    } catch (Exception $e) {
        $response['steps'][] = "❌ Error testing all providers: " . $e->getMessage();
    }
    
    // Step 5: Test email sending (if connection is successful)
    if (isset($connectionTest) && $connectionTest['success']) {
        $response['steps'][] = 'Step 5: Testing email sending...';
        
        try {
            // Test with a simple notification
            $testResult = $unifiedEmailService->sendOrderNotification(
                'TEST-12345',
                'Test Email Provider Selection',
                [
                    'Provider' => $providerInfo['name'],
                    'Test Time' => date('Y-m-d H:i:s'),
                    'Status' => 'Email provider selection working correctly'
                ]
            );
            
            $response['email_send_test'] = $testResult;
            
            if ($testResult['success']) {
                $response['steps'][] = "✅ Test email sent successfully via {$providerInfo['name']}";
            } else {
                $response['steps'][] = "❌ Test email failed: " . ($testResult['error'] ?? 'Unknown error');
            }
            
        } catch (Exception $e) {
            $response['steps'][] = "❌ Error sending test email: " . $e->getMessage();
            $response['email_send_test'] = [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    } else {
        $response['steps'][] = 'Step 5: Skipping email send test (connection failed)';
    }
    
    // Summary
    $response['steps'][] = '📋 EMAIL PROVIDER TEST SUMMARY:';
    $response['steps'][] = "• Configured Provider: {$configuredProvider}";
    $response['steps'][] = "• Active Provider: " . ($providerInfo['name'] ?? 'Unknown');
    $response['steps'][] = "• Connection Status: " . (($connectionTest['success'] ?? false) ? 'Connected' : 'Failed');
    $response['steps'][] = "• Email Send Test: " . (($response['email_send_test']['success'] ?? false) ? 'Success' : 'Failed');
    
    // Overall status
    $configMatch = $configuredProvider === strtolower($providerInfo['name'] ?? '');
    $connectionOk = $connectionTest['success'] ?? false;
    $emailSendOk = $response['email_send_test']['success'] ?? false;
    
    if ($configMatch && $connectionOk && $emailSendOk) {
        $response['overall_status'] = 'SUCCESS - Email provider selection working perfectly';
        $response['steps'][] = '🎉 SUCCESS: Email provider selection is working correctly!';
    } elseif ($configMatch && $connectionOk) {
        $response['overall_status'] = 'PARTIAL - Provider selected correctly but email send failed';
        $response['steps'][] = '⚠️ PARTIAL: Provider selection works but email sending needs attention';
    } else {
        $response['overall_status'] = 'FAILED - Email provider selection has issues';
        $response['steps'][] = '❌ FAILED: Email provider selection needs fixing';
    }
    
    ob_clean();
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => true
    ]);
}
?>