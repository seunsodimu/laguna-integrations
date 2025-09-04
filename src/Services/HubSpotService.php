<?php

namespace Laguna\Integration\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Laguna\Integration\Utils\Logger;

/**
 * HubSpot Service
 * 
 * Handles all HubSpot API interactions including contact retrieval and webhook processing.
 */
class HubSpotService {
    private $client;
    private $config;
    private $credentials;
    private $logger;
    
    public function __construct() {
        $this->config = require __DIR__ . '/../../config/config.php';
        $this->credentials = require __DIR__ . '/../../config/credentials.php';
        $this->logger = Logger::getInstance();
        
        $this->client = new Client([
            'base_uri' => $this->credentials['hubspot']['base_url'],
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->credentials['hubspot']['access_token'],
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ]
        ]);
    }
    
    /**
     * Test HubSpot API connection
     */
    public function testConnection() {
        try {
            $startTime = microtime(true);
            
            // Test with account details endpoint
            $response = $this->client->get('/account-info/v3/details');
            
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            
            if ($response->getStatusCode() === 200) {
                $this->logger->info('HubSpot connection test successful');
                
                return [
                    'success' => true,
                    'status_code' => $response->getStatusCode(),
                    'response_time' => $responseTime . 'ms',
                    'message' => 'Connected successfully'
                ];
            } else {
                throw new \Exception('Unexpected status code: ' . $response->getStatusCode());
            }
            
        } catch (RequestException $e) {
            $error = 'HubSpot API connection failed: ' . $e->getMessage();
            $this->logger->error($error);
            
            return [
                'success' => false,
                'error' => $error,
                'status_code' => $e->getResponse() ? $e->getResponse()->getStatusCode() : null
            ];
        } catch (\Exception $e) {
            $error = 'HubSpot connection error: ' . $e->getMessage();
            $this->logger->error($error);
            
            return [
                'success' => false,
                'error' => $error
            ];
        }
    }
    
    /**
     * Get contact information by ID
     */
    public function getContact($contactId) {
        try {
            $properties = [
                'avenue',
                'company',
                'hs_analytics_first_referrer',
                'hs_analytics_source',
                'hs_analytics_source_data_1',
                'hs_analytics_source_data_2',
                'hubspot_owner_id',
                'lead_source_netsuite',
                'lifecyclestage',
                'message',
                'ns_customer_id',
                'ns_entity_id',
                'phone',
                'promo_code',
                'email',
                'firstname',
                'lastname'
            ];
            
            $queryParams = http_build_query([
                'properties' => implode('&properties=', $properties),
                'archived' => 'false'
            ]);
            
            $response = $this->client->get("/crm/v3/objects/contacts/{$contactId}?{$queryParams}");
            
            if ($response->getStatusCode() === 200) {
                $contactData = json_decode($response->getBody()->getContents(), true);
                
                $this->logger->info('HubSpot contact retrieved successfully', [
                    'contact_id' => $contactId,
                    'email' => $contactData['properties']['email'] ?? 'N/A'
                ]);
                
                return [
                    'success' => true,
                    'data' => $contactData
                ];
            } else {
                throw new \Exception('Unexpected status code: ' . $response->getStatusCode());
            }
            
        } catch (RequestException $e) {
            $error = 'Failed to retrieve HubSpot contact: ' . $e->getMessage();
            $this->logger->error($error, ['contact_id' => $contactId]);
            
            return [
                'success' => false,
                'error' => $error,
                'status_code' => $e->getResponse() ? $e->getResponse()->getStatusCode() : null
            ];
        } catch (\Exception $e) {
            $error = 'HubSpot contact retrieval error: ' . $e->getMessage();
            $this->logger->error($error, ['contact_id' => $contactId]);
            
            return [
                'success' => false,
                'error' => $error
            ];
        }
    }
    
    /**
     * Update HubSpot contact with NetSuite customer ID
     */
    public function updateContactNetSuiteId($contactId, $netsuiteCustomerId) {
        try {
            $url = "/crm/v3/objects/contacts/{$contactId}";
            
            $data = [
                'properties' => [
                    'ns_customer_id' => (string)$netsuiteCustomerId
                ]
            ];
            
            $response = $this->client->patch($url, [
                'json' => $data,
                'headers' => [
                    'Content-Type' => 'application/json'
                ]
            ]);
            
            if ($response->getStatusCode() === 200) {
                $responseData = json_decode($response->getBody()->getContents(), true);
                $this->logger->info('Successfully updated HubSpot contact with NetSuite ID', [
                    'contact_id' => $contactId,
                    'netsuite_id' => $netsuiteCustomerId
                ]);
                
                return [
                    'success' => true,
                    'data' => $responseData,
                    'message' => 'Contact updated with NetSuite customer ID'
                ];
            } else {
                throw new \Exception('Unexpected status code: ' . $response->getStatusCode());
            }
            
        } catch (RequestException $e) {
            $error = 'Failed to update HubSpot contact: ' . $e->getMessage();
            $this->logger->error($error, [
                'contact_id' => $contactId,
                'netsuite_id' => $netsuiteCustomerId
            ]);
            
            return [
                'success' => false,
                'error' => $error,
                'status_code' => $e->getResponse() ? $e->getResponse()->getStatusCode() : null
            ];
        } catch (\Exception $e) {
            $error = 'Error updating HubSpot contact: ' . $e->getMessage();
            $this->logger->error($error, [
                'contact_id' => $contactId,
                'netsuite_id' => $netsuiteCustomerId
            ]);
            
            return [
                'success' => false,
                'error' => $error
            ];
        }
    }
    
    /**
     * Process webhook payload
     */
    public function processWebhook($payload) {
        try {
            $this->logger->info('Processing HubSpot webhook', ['payload' => $payload]);
            
            // Validate required fields
            if (!isset($payload['objectId']) || !isset($payload['subscriptionType'])) {
                throw new \Exception('Invalid webhook payload: missing required fields');
            }
            
            // Only process contact property changes
            if ($payload['subscriptionType'] !== 'contact.propertyChange') {
                $this->logger->info('Ignoring non-contact webhook', [
                    'subscription_type' => $payload['subscriptionType']
                ]);
                return [
                    'success' => true,
                    'message' => 'Webhook ignored - not a contact property change'
                ];
            }
            
            // Get contact information
            $contactResult = $this->getContact($payload['objectId']);
            if (!$contactResult['success']) {
                throw new \Exception('Failed to retrieve contact: ' . $contactResult['error']);
            }
            
            $contact = $contactResult['data'];
            
            // Check if lifecycle stage is 'lead'
            $lifecycleStage = $contact['properties']['lifecyclestage'] ?? '';
            if ($lifecycleStage !== 'lead') {
                $this->logger->info('Contact is not a lead, skipping processing', [
                    'contact_id' => $payload['objectId'],
                    'lifecycle_stage' => $lifecycleStage
                ]);
                return [
                    'success' => true,
                    'message' => 'Contact is not a lead - processing skipped'
                ];
            }
            
            // Process the lead
            $netSuiteService = new NetSuiteService();
            $leadResult = $this->processLead($contact, $payload, $netSuiteService);
            
            return $leadResult;
            
        } catch (\Exception $e) {
            $error = 'HubSpot webhook processing failed: ' . $e->getMessage();
            $this->logger->error($error, ['payload' => $payload]);
            
            return [
                'success' => false,
                'error' => $error
            ];
        }
    }
    
    /**
     * Process lead and create in NetSuite
     */
    private function processLead($contact, $webhookPayload, $netSuiteService) {
        try {
            // Format campaign ID
            $formattedCampaignId = $this->formatCampaignId(
                $contact['properties']['hs_analytics_source_data_1'] ?? null
            );
            
            // Check if campaign exists in NetSuite
            $campaignResult = $this->findOrCreateCampaign(
                $formattedCampaignId,
                $contact['properties']['hs_analytics_source_data_1'] ?? null,
                $netSuiteService
            );
            
            if (!$campaignResult['success']) {
                throw new \Exception('Failed to handle campaign: ' . $campaignResult['error']);
            }
            
            $contactCampaignId = $campaignResult['campaign_id'];
            
            // Create lead payload for NetSuite
            $leadPayload = $this->buildLeadPayload($contact, $webhookPayload, $contactCampaignId, $formattedCampaignId);
            
            // Create lead in NetSuite
            $leadResult = $netSuiteService->createLead($leadPayload);
            
            if ($leadResult['success']) {
                $this->logger->info('Lead created successfully in NetSuite', [
                    'hubspot_contact_id' => $contact['id'],
                    'netsuite_lead_id' => $leadResult['lead_id'] ?? 'N/A',
                    'email' => $contact['properties']['email']
                ]);
                
                // Update HubSpot contact with NetSuite customer ID
                if (!empty($leadResult['lead_id'])) {
                    $updateResult = $this->updateContactNetSuiteId($contact['id'], $leadResult['lead_id']);
                    
                    if ($updateResult['success']) {
                        $this->logger->info('HubSpot contact updated with NetSuite ID', [
                            'hubspot_contact_id' => $contact['id'],
                            'netsuite_lead_id' => $leadResult['lead_id']
                        ]);
                        
                        // Add update info to the result
                        $leadResult['hubspot_updated'] = true;
                        $leadResult['hubspot_update_result'] = $updateResult;
                    } else {
                        $this->logger->warning('Failed to update HubSpot contact with NetSuite ID', [
                            'hubspot_contact_id' => $contact['id'],
                            'netsuite_lead_id' => $leadResult['lead_id'],
                            'error' => $updateResult['error'] ?? 'Unknown error'
                        ]);
                        
                        // Add update failure info to the result
                        $leadResult['hubspot_updated'] = false;
                        $leadResult['hubspot_update_error'] = $updateResult['error'] ?? 'Unknown error';
                    }
                }
            }
            
            return $leadResult;
            
        } catch (\Exception $e) {
            $error = 'Lead processing failed: ' . $e->getMessage();
            $this->logger->error($error, [
                'contact_id' => $contact['id'] ?? 'N/A',
                'email' => $contact['properties']['email'] ?? 'N/A'
            ]);
            
            return [
                'success' => false,
                'error' => $error
            ];
        }
    }
    
    /**
     * Format campaign ID according to requirements
     */
    private function formatCampaignId($sourceData) {
        if (empty($sourceData)) {
            return 'None';
        }
        
        // Replace all characters that are not letters, numbers, or underscores with underscore
        $formatted = preg_replace('/[^a-zA-Z0-9_]/', '_', $sourceData);
        
        // Truncate to maximum of 60 characters
        $formatted = substr($formatted, 0, 60);
        
        return $formatted;
    }
    
    /**
     * Find existing campaign or create new one in NetSuite
     */
    private function findOrCreateCampaign($formattedCampaignId, $originalTitle, $netSuiteService) {
        try {
            // Search for existing campaign
            $searchResult = $netSuiteService->searchCampaign($formattedCampaignId);
            
            if ($searchResult['success'] && !empty($searchResult['campaigns'])) {
                // Campaign exists
                $campaign = $searchResult['campaigns'][0];
                return [
                    'success' => true,
                    'campaign_id' => $campaign['id'],
                    'existing' => true
                ];
            }
            
            // Campaign doesn't exist, create new one
            $campaignPayload = [
                'title' => $originalTitle ?: $formattedCampaignId,
                'campaignid' => $formattedCampaignId,
                'category' => ['id' => '-5'],
                'owner' => ['id' => '124']
            ];
            
            $createResult = $netSuiteService->createCampaign($campaignPayload);
            
            if ($createResult['success']) {
                return [
                    'success' => true,
                    'campaign_id' => $createResult['campaign_id'],
                    'existing' => false
                ];
            } else {
                throw new \Exception('Failed to create campaign: ' . $createResult['error']);
            }
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Build lead payload for NetSuite
     */
    private function buildLeadPayload($contact, $webhookPayload, $contactCampaignId, $formattedCampaignId) {
        $properties = $contact['properties'];
        
        return [
            'customform' => 259,
            'entityStatus' => ['id' => 19],
            'email' => $properties['email'] ?? '',
            'custentity_hs_vid' => $contact['id'],
            'phone' => $properties['phone'] ?? '',
            'custentity_comments' => $properties['message'] ?? '',
            'subsidiary' => 1,
            'entityid' => ($properties['firstname'] ?? '') . ' ' . ($properties['lastname'] ?? '') . ' ' . ($properties['email'] ?? ''),
            'autoname' => false,
            'isperson' => true,
            'custentity_hsoriginalsource' => $contactCampaignId,
            'custentity_hsorigsourdrilldown2' => $formattedCampaignId,
            'custentity_firstreferringsite' => $properties['hs_analytics_first_referrer'] ?? '',
            'custentity_add_promo_code' => $properties['promo_code'] ?? '',
            'companyname' => $properties['company'] ?? '',
            'lastname' => $properties['lastname'] ?? '',
            'firstname' => $properties['firstname'] ?? '',
            'leadsource' => ['id' => $contactCampaignId],
            'salesTeam' => [
                'items' => [
                    [
                        'employee' => ['id' => $webhookPayload['propertyValue'] ?? ''],
                        'isprimary' => true,
                        'contribution' => 100,
                        'salesrole' => ['id' => -2]
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Verify webhook signature (if needed)
     */
    public function verifyWebhookSignature($payload, $signature) {
        $expectedSignature = hash_hmac('sha256', $payload, $this->credentials['hubspot']['webhook_secret']);
        return hash_equals($expectedSignature, $signature);
    }
}