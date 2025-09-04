<?php

namespace Laguna\Integration\Services;

use Laguna\Integration\Utils\Logger;

/**
 * Unified Email Service
 * 
 * Provides a unified interface for email services regardless of provider
 */
class UnifiedEmailService {
    private $emailService;
    private $provider;
    private $logger;
    
    public function __construct() {
        $this->logger = Logger::getInstance();
        $this->emailService = EmailServiceFactory::create();
        $this->provider = EmailServiceFactory::getCurrentProvider();
        
        $this->logger->info('Unified email service initialized', [
            'provider' => $this->provider['name'],
            'class' => $this->provider['class']
        ]);
    }
    
    /**
     * Send email using the configured provider
     */
    public function sendEmail($subject, $htmlContent, $recipients, $isTest = false) {
        return $this->emailService->sendEmail($subject, $htmlContent, $recipients, $isTest);
    }
    
    /**
     * Send test email using the configured provider
     */
    public function sendTestEmail($toEmail, $type = 'basic') {
        return $this->emailService->sendTestEmail($toEmail, $type);
    }
    
    /**
     * Test connection using the configured provider
     */
    public function testConnection() {
        $result = $this->emailService->testConnection();
        $result['provider'] = $this->provider['name'];
        return $result;
    }
    
    /**
     * Check account status using the configured provider
     */
    public function checkAccountStatus() {
        if (method_exists($this->emailService, 'checkAccountStatus')) {
            $result = $this->emailService->checkAccountStatus();
            $result['provider'] = $this->provider['name'];
            return $result;
        }
        
        return [
            'success' => false,
            'error' => 'Account status check not supported by ' . $this->provider['name'],
            'provider' => $this->provider['name']
        ];
    }
    
    /**
     * Get current provider information
     */
    public function getProviderInfo() {
        return $this->provider;
    }
    
    /**
     * Get all available providers and their status
     */
    public function getAllProvidersStatus() {
        return EmailServiceFactory::testAllProviders();
    }
    
    /**
     * Send order notification email
     */
    public function sendOrderNotification($orderId, $status, $details = []) {
        // Get recipients from config
        $config = require __DIR__ . '/../../config/config.php';
        $recipients = $config['notifications']['to_emails'] ?? [];
        
        if (!$config['notifications']['enabled']) {
            $this->logger->info('Email notifications disabled, skipping order notification');
            return ['success' => true, 'message' => 'Email notifications disabled'];
        }
        
        if (empty($recipients)) {
            $this->logger->warning('No email recipients configured for notifications');
            return ['success' => false, 'error' => 'No recipients configured'];
        }
        
        $subject = '[3DCart Integration] Order #' . $orderId . ' - ' . $status;
        
        $orderData = [
            'order_id' => $orderId,
            'status' => $status,
            'details' => $details,
            'order_date' => date('Y-m-d H:i:s')
        ];
        
        $htmlContent = $this->generateOrderNotificationHtml($orderData);
        
        return $this->sendEmail($subject, $htmlContent, $recipients);
    }
    
    /**
     * Send error notification email
     */
    public function sendErrorNotification($error, $context = []) {
        // Get recipients from config
        $config = require __DIR__ . '/../../config/config.php';
        $recipients = $config['notifications']['to_emails'] ?? [];
        
        if (!$config['notifications']['enabled']) {
            $this->logger->info('Email notifications disabled, skipping error notification');
            return ['success' => true, 'message' => 'Email notifications disabled'];
        }
        
        if (empty($recipients)) {
            $this->logger->warning('No email recipients configured for notifications');
            return ['success' => false, 'error' => 'No recipients configured'];
        }
        
        $subject = '[3DCart Integration] System Error Alert';
        
        $htmlContent = $this->generateErrorNotificationHtml($error, $context);
        
        return $this->sendEmail($subject, $htmlContent, $recipients);
    }
    
    /**
     * Send connection alert email
     */
    public function sendConnectionAlert($service, $status, $details = []) {
        // Get recipients from config
        $config = require __DIR__ . '/../../config/config.php';
        $recipients = $config['notifications']['to_emails'] ?? [];
        
        if (!$config['notifications']['enabled']) {
            $this->logger->info('Email notifications disabled, skipping connection alert');
            return ['success' => true, 'message' => 'Email notifications disabled'];
        }
        
        if (empty($recipients)) {
            $this->logger->warning('No email recipients configured for notifications');
            return ['success' => false, 'error' => 'No recipients configured'];
        }
        
        $subject = '[3DCart Integration] Connection Alert - ' . $service;
        
        $htmlContent = $this->generateConnectionAlertHtml($service, $status, $details);
        
        return $this->sendEmail($subject, $htmlContent, $recipients);
    }
    
    /**
     * Generate order notification HTML
     */
    private function generateOrderNotificationHtml($orderData) {
        $orderId = $orderData['order_id'] ?? 'Unknown';
        $status = $orderData['status'] ?? 'Processing';
        $details = $orderData['details'] ?? [];
        $orderDate = $orderData['order_date'] ?? date('Y-m-d H:i:s');
        $provider = $this->provider['name'];
        
        // Build details HTML
        $detailsHtml = '';
        if (!empty($details)) {
            $detailsHtml = '<h4>Additional Details:</h4><ul>';
            foreach ($details as $key => $value) {
                $detailsHtml .= '<li><strong>' . htmlspecialchars($key) . ':</strong> ' . htmlspecialchars($value) . '</li>';
            }
            $detailsHtml .= '</ul>';
        }
        
        $statusColor = (strpos(strtolower($status), 'fail') !== false || strpos(strtolower($status), 'error') !== false) ? '#dc3545' : '#28a745';
        
        return "
        <html>
        <head><title>Order Notification</title></head>
        <body>
            <h2>Order Processing Notification</h2>
            <p>This is an automated notification from the 3DCart Integration System.</p>
            <div style='background-color: #f8f9fa; padding: 15px; border-left: 4px solid {$statusColor};'>
                <h3>Order Details</h3>
                <p><strong>Order ID:</strong> {$orderId}</p>
                <p><strong>Status:</strong> {$status}</p>
                <p><strong>Date:</strong> {$orderDate}</p>
                {$detailsHtml}
            </div>
            <p>Please check the integration dashboard for more details if needed.</p>
            <hr>
            <p><small>This email was sent via {$provider} from the 3DCart Integration System.</small></p>
        </body>
        </html>";
    }
    
    /**
     * Generate error notification HTML
     */
    private function generateErrorNotificationHtml($error, $context) {
        $timestamp = date('Y-m-d H:i:s');
        $provider = $this->provider['name'];
        $contextHtml = '';
        
        if (!empty($context)) {
            $contextHtml = '<h4>Context:</h4><pre>' . htmlspecialchars(json_encode($context, JSON_PRETTY_PRINT)) . '</pre>';
        }
        
        return "
        <html>
        <head><title>System Error Alert</title></head>
        <body>
            <h2 style='color: #dc3545;'>System Error Alert</h2>
            <p>An error has occurred in the 3DCart to NetSuite integration system.</p>
            <div style='background-color: #f8d7da; padding: 15px; border-left: 4px solid #dc3545; color: #721c24;'>
                <h3>Error Details</h3>
                <p><strong>Timestamp:</strong> {$timestamp}</p>
                <p><strong>Error:</strong> {$error}</p>
                {$contextHtml}
            </div>
            <p>Please investigate this issue as soon as possible.</p>
            <hr>
            <p><small>This alert was sent via {$provider} from the 3DCart Integration System.</small></p>
        </body>
        </html>";
    }
    
    /**
     * Generate connection alert HTML
     */
    private function generateConnectionAlertHtml($service, $status, $details) {
        $timestamp = date('Y-m-d H:i:s');
        $provider = $this->provider['name'];
        $statusColor = $status === 'connected' ? '#28a745' : '#dc3545';
        $detailsHtml = '';
        
        if (!empty($details)) {
            $detailsHtml = '<h4>Details:</h4><pre>' . htmlspecialchars(json_encode($details, JSON_PRETTY_PRINT)) . '</pre>';
        }
        
        return "
        <html>
        <head><title>Connection Alert</title></head>
        <body>
            <h2 style='color: {$statusColor};'>Connection Status Alert</h2>
            <p>Connection status change detected for {$service}.</p>
            <div style='background-color: #f8f9fa; padding: 15px; border-left: 4px solid {$statusColor};'>
                <h3>Connection Details</h3>
                <p><strong>Service:</strong> {$service}</p>
                <p><strong>Status:</strong> {$status}</p>
                <p><strong>Timestamp:</strong> {$timestamp}</p>
                {$detailsHtml}
            </div>
            <p>Please check the system status dashboard for more information.</p>
            <hr>
            <p><small>This alert was sent via {$provider} from the 3DCart Integration System.</small></p>
        </body>
        </html>";
    }
}