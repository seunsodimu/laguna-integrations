<?php

namespace Laguna\Integration\Services;

use Laguna\Integration\Utils\Logger;

/**
 * Email Service Factory
 * 
 * Creates the appropriate email service based on configuration
 */
class EmailServiceFactory {
    
    /**
     * Create email service instance based on configuration
     */
    public static function create() {
        $credentials = require __DIR__ . '/../../config/credentials.php';
        $logger = Logger::getInstance();
        
        // Get the configured email provider
        $provider = $credentials['email']['provider'] ?? 'sendgrid';
        
        switch (strtolower($provider)) {
            case 'brevo':
                $logger->info('Creating Brevo email service instance');
                return new BrevoEmailService();
                
            case 'sendgrid':
            default:
                $logger->info('Creating SendGrid email service instance');
                return new EmailService(); // The existing SendGrid service
        }
    }
    
    /**
     * Get available email providers
     */
    public static function getAvailableProviders() {
        return [
            'sendgrid' => [
                'name' => 'SendGrid',
                'description' => 'SendGrid email delivery service',
                'class' => 'EmailService'
            ],
            'brevo' => [
                'name' => 'Brevo',
                'description' => 'Brevo (formerly SendinBlue) email delivery service',
                'class' => 'BrevoEmailService'
            ]
        ];
    }
    
    /**
     * Get current provider information
     */
    public static function getCurrentProvider() {
        $credentials = require __DIR__ . '/../../config/credentials.php';
        $provider = $credentials['email']['provider'] ?? 'sendgrid';
        
        $providers = self::getAvailableProviders();
        return $providers[$provider] ?? $providers['sendgrid'];
    }
    
    /**
     * Test connection for all available providers
     */
    public static function testAllProviders() {
        $results = [];
        $credentials = require __DIR__ . '/../../config/credentials.php';
        
        // Test SendGrid if configured
        if (!empty($credentials['email']['sendgrid']['api_key']) && 
            $credentials['email']['sendgrid']['api_key'] !== 'your-sendgrid-api-key') {
            try {
                $sendgridService = new EmailService();
                $results['sendgrid'] = $sendgridService->testConnection();
            } catch (\Exception $e) {
                $results['sendgrid'] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'service' => 'SendGrid'
                ];
            }
        }
        
        // Test Brevo if configured
        if (!empty($credentials['email']['brevo']['api_key']) && 
            $credentials['email']['brevo']['api_key'] !== 'your-brevo-api-key-here') {
            try {
                $brevoService = new BrevoEmailService();
                $results['brevo'] = $brevoService->testConnection();
            } catch (\Exception $e) {
                $results['brevo'] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'service' => 'Brevo'
                ];
            }
        }
        
        return $results;
    }
}