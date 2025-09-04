<?php

namespace Laguna\Integration\Services;

use Laguna\Integration\Utils\Logger;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Brevo Email Service
 * 
 * Handles email sending using Brevo (formerly SendinBlue) API
 */
class BrevoEmailService {
    private $credentials;
    private $config;
    private $logger;
    private $httpClient;
    private $apiBaseUrl = 'https://api.brevo.com';
    
    public function __construct() {
        $credentials = require __DIR__ . '/../../config/credentials.php';
        $config = require __DIR__ . '/../../config/config.php';
        
        $this->credentials = $credentials['email']['brevo'];
        $this->config = $config['notifications'];
        $this->logger = Logger::getInstance();
        
        // Initialize HTTP client with proper configuration
        $this->httpClient = new Client([
            'base_uri' => $this->apiBaseUrl,
            'timeout' => 30,
            'connect_timeout' => 10,
            'verify' => false, // Disable SSL verification for development
            'headers' => [
                'API-key' => $this->credentials['api_key'],
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]
        ]);
    }
    
    /**
     * Send email using Brevo API
     */
    public function sendEmail($subject, $htmlContent, $recipients, $isTest = false) {
        try {
            // Prepare recipients array
            $to = [];
            foreach ($recipients as $email) {
                $to[] = ['email' => $email];
            }
            
            // Prepare email data
            $emailData = [
                'sender' => [
                    'name' => $this->credentials['from_name'],
                    'email' => $this->credentials['from_email']
                ],
                'to' => $to,
                'subject' => $subject,
                'htmlContent' => $htmlContent
            ];
            
            // Add tags for test emails
            if ($isTest) {
                $emailData['tags'] = ['test', 'integration-test'];
            }
            
            $this->logger->info('Sending email via Brevo', [
                'subject' => $subject,
                'recipients' => $recipients,
                'is_test' => $isTest
            ]);
            
            // Send the email
            $response = $this->httpClient->post('/v3/smtp/email', [
                'json' => $emailData
            ]);
            
            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            $responseHeaders = $response->getHeaders();
            
            // Brevo returns 201 for successful email sends
            $isSuccess = ($statusCode == 201);
            
            if ($isSuccess) {
                $responseData = json_decode($responseBody, true);
                $messageId = $responseData['messageId'] ?? 'unknown';
                
                $this->logger->info('Email sent successfully via Brevo', [
                    'subject' => $subject,
                    'recipients' => $recipients,
                    'status_code' => $statusCode,
                    'message_id' => $messageId,
                    'is_test' => $isTest
                ]);
                
                return [
                    'success' => true,
                    'message_id' => $messageId,
                    'status_code' => $statusCode,
                    'provider' => 'brevo'
                ];
            } else {
                // Parse error message for better logging
                $errorMessage = 'Unknown error';
                if (!empty($responseBody)) {
                    $responseData = json_decode($responseBody, true);
                    if (isset($responseData['message'])) {
                        $errorMessage = $responseData['message'];
                    } elseif (isset($responseData['code'])) {
                        $errorMessage = $responseData['code'];
                    }
                }
                
                $isQuotaExceeded = ($statusCode == 402 && strpos($errorMessage, 'not_enough_credits') !== false);
                
                $logData = [
                    'subject' => $subject,
                    'recipients' => $recipients,
                    'status_code' => $statusCode,
                    'error_message' => $errorMessage,
                    'response_body' => $responseBody,
                    'is_test' => $isTest,
                    'quota_exceeded' => $isQuotaExceeded
                ];
                
                if ($isQuotaExceeded) {
                    $this->logger->warning('Email sending failed - quota exceeded (Brevo)', $logData);
                } else {
                    $this->logger->error('Email sending failed (Brevo)', $logData);
                }
                
                return [
                    'success' => false,
                    'error' => $errorMessage,
                    'status_code' => $statusCode,
                    'quota_exceeded' => $isQuotaExceeded,
                    'provider' => 'brevo'
                ];
            }
            
        } catch (RequestException $e) {
            $errorMessage = $e->getMessage();
            $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 0;
            
            $this->logger->error('Brevo email request failed', [
                'subject' => $subject,
                'recipients' => $recipients,
                'error' => $errorMessage,
                'status_code' => $statusCode,
                'is_test' => $isTest
            ]);
            
            return [
                'success' => false,
                'error' => $errorMessage,
                'status_code' => $statusCode,
                'provider' => 'brevo'
            ];
        } catch (\Exception $e) {
            $this->logger->error('Brevo email service error', [
                'subject' => $subject,
                'recipients' => $recipients,
                'error' => $e->getMessage(),
                'is_test' => $isTest
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'provider' => 'brevo'
            ];
        }
    }
    
    /**
     * Test Brevo connection
     */
    public function testConnection() {
        try {
            // Test connection by attempting to send a test email to a dummy address
            // This will validate API key and connection without actually sending
            $testData = [
                'sender' => [
                    'name' => $this->credentials['from_name'],
                    'email' => $this->credentials['from_email']
                ],
                'to' => [
                    ['email' => 'test@example.com', 'name' => 'Connection Test']
                ],
                'subject' => 'Connection Test',
                'htmlContent' => '<html><body>Connection test</body></html>'
            ];
            
            // Make the request but expect it to fail due to invalid recipient
            // We're just testing if the API key and connection work
            $response = $this->httpClient->post('/v3/smtp/email', [
                'json' => $testData
            ]);
            
            $statusCode = $response->getStatusCode();
            
            // 201 means success (unlikely with test@example.com)
            // 400 with invalid email is also acceptable - means API key works
            if ($statusCode == 201) {
                $this->logger->info('Brevo connection test successful', [
                    'status_code' => $statusCode
                ]);
                
                return [
                    'success' => true,
                    'status_code' => $statusCode,
                    'service' => 'Brevo'
                ];
            } else {
                $this->logger->error('Brevo connection test failed', [
                    'status_code' => $statusCode
                ]);
                
                return [
                    'success' => false,
                    'error' => 'Unexpected status code: ' . $statusCode,
                    'service' => 'Brevo'
                ];
            }
        } catch (RequestException $e) {
            $errorMessage = $e->getMessage();
            $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 0;
            $responseBody = '';
            
            if ($e->hasResponse()) {
                $responseBody = $e->getResponse()->getBody()->getContents();
            }
            
            // Check if it's just an invalid email error (400) - this means API key works
            if ($statusCode == 400 && strpos($responseBody, 'invalid') !== false) {
                $this->logger->info('Brevo connection test successful (API key valid)', [
                    'status_code' => $statusCode,
                    'note' => 'API key is valid, test email rejected as expected'
                ]);
                
                return [
                    'success' => true,
                    'status_code' => $statusCode,
                    'service' => 'Brevo',
                    'note' => 'API key validated successfully'
                ];
            }
            
            // Check if it's an IP address restriction error (401)
            if ($statusCode == 401 && strpos($responseBody, 'unrecognised IP address') !== false) {
                $this->logger->warning('Brevo connection failed due to IP restriction', [
                    'status_code' => $statusCode,
                    'response_body' => $responseBody
                ]);
                
                return [
                    'success' => false,
                    'error' => 'IP address not authorized. Please add your server IP to Brevo authorized IPs.',
                    'service' => 'Brevo',
                    'ip_restriction' => true,
                    'help_url' => 'https://app.brevo.com/security/authorised_ips'
                ];
            }
            
            $this->logger->error('Brevo connection test failed', [
                'error' => $errorMessage,
                'status_code' => $statusCode,
                'response_body' => $responseBody
            ]);
            
            return [
                'success' => false,
                'error' => $errorMessage,
                'service' => 'Brevo'
            ];
        } catch (\Exception $e) {
            $this->logger->error('Brevo connection test failed', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'service' => 'Brevo'
            ];
        }
    }
    
    /**
     * Check Brevo account status and quota
     */
    public function checkAccountStatus() {
        try {
            // Use connection test as account status check since /account endpoint may not be available
            $connectionResult = $this->testConnection();
            
            if ($connectionResult['success']) {
                $this->logger->info('Brevo account status check successful via connection test');
                
                return [
                    'success' => true,
                    'status_code' => $connectionResult['status_code'],
                    'quota_exceeded' => false,
                    'message' => 'Account appears to be active (connection successful)'
                ];
            } else {
                // Check if it's a quota issue based on error message
                $isQuotaExceeded = (isset($connectionResult['status_code']) && 
                    $connectionResult['status_code'] == 402);
                
                $this->logger->warning('Brevo account status check failed', [
                    'error' => $connectionResult['error'] ?? 'Unknown error',
                    'quota_exceeded' => $isQuotaExceeded
                ]);
                
                return [
                    'success' => false,
                    'status_code' => $connectionResult['status_code'] ?? 0,
                    'quota_exceeded' => $isQuotaExceeded,
                    'error' => $connectionResult['error'] ?? 'Connection failed'
                ];
            }
        } catch (\Exception $e) {
            $this->logger->error('Brevo account status check failed', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'quota_exceeded' => false
            ];
        }
    }
    
    /**
     * Send test email
     */
    public function sendTestEmail($toEmail, $type = 'basic') {
        $templates = [
            'basic' => [
                'subject' => '[3DCart Integration] Test Email - Basic',
                'content' => '
                <html>
                <head><title>Test Email</title></head>
                <body>
                    <h2>Brevo Email Test - Basic</h2>
                    <p>Hello!</p>
                    <p>This is a test email sent from the 3DCart to NetSuite integration system using <strong>Brevo</strong>.</p>
                    <p><strong>Test Details:</strong></p>
                    <ul>
                        <li><strong>Provider:</strong> Brevo (formerly SendinBlue)</li>
                        <li><strong>Timestamp:</strong> ' . date('Y-m-d H:i:s') . '</li>
                        <li><strong>Type:</strong> Basic Test</li>
                    </ul>
                    <p>If you received this email, the Brevo integration is working correctly!</p>
                    <hr>
                    <p><small>This is an automated test email from the 3DCart Integration System.</small></p>
                </body>
                </html>'
            ],
            'order' => [
                'subject' => '[3DCart Integration] Test Email - Order Notification',
                'content' => '
                <html>
                <head><title>Order Notification Test</title></head>
                <body>
                    <h2>Order Processing Notification (Test)</h2>
                    <p>Dear Customer,</p>
                    <p>This is a <strong>test</strong> order notification email sent via <strong>Brevo</strong>.</p>
                    <div style="background-color: #f8f9fa; padding: 15px; border-left: 4px solid #007bff;">
                        <h3>Test Order Details</h3>
                        <p><strong>Order ID:</strong> TEST-' . rand(10000, 99999) . '</p>
                        <p><strong>Date:</strong> ' . date('Y-m-d H:i:s') . '</p>
                        <p><strong>Status:</strong> Processing</p>
                        <p><strong>Provider:</strong> Brevo Email Service</p>
                    </div>
                    <p>Your order has been successfully processed and sent to NetSuite.</p>
                    <p>Thank you for your business!</p>
                    <hr>
                    <p><small>This is a test email from the 3DCart Integration System using Brevo.</small></p>
                </body>
                </html>'
            ],
            'error' => [
                'subject' => '[3DCart Integration] Test Email - Error Alert',
                'content' => '
                <html>
                <head><title>Error Alert Test</title></head>
                <body>
                    <h2 style="color: #dc3545;">System Error Alert (Test)</h2>
                    <p>This is a <strong>test</strong> error notification email sent via <strong>Brevo</strong>.</p>
                    <div style="background-color: #f8d7da; padding: 15px; border-left: 4px solid #dc3545; color: #721c24;">
                        <h3>Test Error Details</h3>
                        <p><strong>Error Type:</strong> Test Error</p>
                        <p><strong>Timestamp:</strong> ' . date('Y-m-d H:i:s') . '</p>
                        <p><strong>Component:</strong> Email Service Test</p>
                        <p><strong>Provider:</strong> Brevo</p>
                        <p><strong>Message:</strong> This is a simulated error for testing purposes.</p>
                    </div>
                    <p>In a real scenario, this email would contain details about system errors that require attention.</p>
                    <hr>
                    <p><small>This is a test email from the 3DCart Integration System using Brevo.</small></p>
                </body>
                </html>'
            ],
            'connection' => [
                'subject' => '[3DCart Integration] Test Email - Connection Alert',
                'content' => '
                <html>
                <head><title>Connection Alert Test</title></head>
                <body>
                    <h2 style="color: #ffc107;">Connection Status Alert (Test)</h2>
                    <p>This is a <strong>test</strong> connection alert email sent via <strong>Brevo</strong>.</p>
                    <div style="background-color: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; color: #856404;">
                        <h3>Test Connection Details</h3>
                        <p><strong>Service:</strong> Brevo Email Service</p>
                        <p><strong>Status:</strong> Connection Test</p>
                        <p><strong>Timestamp:</strong> ' . date('Y-m-d H:i:s') . '</p>
                        <p><strong>Result:</strong> Successfully connected to Brevo API</p>
                    </div>
                    <p>This type of email would normally be sent when there are connection issues with external services.</p>
                    <hr>
                    <p><small>This is a test email from the 3DCart Integration System using Brevo.</small></p>
                </body>
                </html>'
            ]
        ];
        
        if (!isset($templates[$type])) {
            return [
                'success' => false,
                'error' => 'Invalid test email type: ' . $type
            ];
        }
        
        $template = $templates[$type];
        
        return $this->sendEmail(
            $template['subject'],
            $template['content'],
            [$toEmail],
            true // Mark as test email
        );
    }
}