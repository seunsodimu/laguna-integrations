<?php

namespace Laguna\Integration\Models;

use Laguna\Integration\Utils\Validator;

/**
 * Customer Model
 * 
 * Represents a customer from 3DCart and provides methods for data transformation
 * and validation for NetSuite integration.
 */
class Customer {
    private $data;
    
    public function __construct($customerData) {
        $this->data = $customerData;
    }
    
    /**
     * Get customer ID
     */
    public function getId() {
        return $this->data['id'] ?? $this->data['CustomerID'] ?? null;
    }
    
    /**
     * Get customer email
     */
    public function getEmail() {
        return $this->data['email'] ?? $this->data['Email'] ?? '';
    }
    
    /**
     * Get first name
     */
    public function getFirstName() {
        return $this->data['firstname'] ?? $this->data['FirstName'] ?? '';
    }
    
    /**
     * Get last name
     */
    public function getLastName() {
        return $this->data['lastname'] ?? $this->data['LastName'] ?? '';
    }
    
    /**
     * Get full name
     */
    public function getFullName() {
        return trim($this->getFirstName() . ' ' . $this->getLastName());
    }
    
    /**
     * Get company name
     */
    public function getCompany() {
        return $this->data['company'] ?? $this->data['Company'] ?? '';
    }
    
    /**
     * Get phone number
     */
    public function getPhone() {
        return $this->data['phone'] ?? $this->data['Phone'] ?? '';
    }
    
    /**
     * Get billing address
     */
    public function getBillingAddress() {
        return $this->data['billing_address'] ?? [];
    }
    
    /**
     * Get shipping address
     */
    public function getShippingAddress() {
        return $this->data['shipping_address'] ?? $this->getBillingAddress();
    }
    
    /**
     * Get customer type (individual or company)
     */
    public function getCustomerType() {
        return !empty($this->getCompany()) ? 'company' : 'individual';
    }
    
    /**
     * Get shipping company name (from raw data if available)
     */
    public function getShippingCompany() {
        return $this->data['shipping_company'] ?? '';
    }
    
    /**
     * Get raw customer data
     */
    public function getRawData() {
        return $this->data;
    }
    
    /**
     * Validate customer data
     */
    public function validate() {
        return Validator::validateCustomerData($this->data);
    }
    
    /**
     * Convert to NetSuite customer format
     */
    public function toNetSuiteFormat() {
        $netsuiteCustomer = [
            'firstName' => $this->getFirstName(), // From ShipmentList per requirements
            'lastName' => $this->getLastName(),   // From ShipmentList per requirements
            'email' => $this->getEmail(),         // From QuestionList per requirements
            'isPerson' => true, // Always treat as person for NetSuite
            'subsidiary' => ['id' => 1], // Default subsidiary - adjust as needed
        ];
        
        // Add company name if it's a company (for parent customer creation)
        if ($this->getCustomerType() === 'company') {
            $netsuiteCustomer['companyName'] = $this->getCompany();
        }
        
        // Add phone if available
        if (!empty($this->getPhone())) {
            $netsuiteCustomer['phone'] = $this->getPhone();
        }
        
        // Add raw order data for NetSuiteService to access billing details for parent customer creation
        $rawOrderData = $this->data['raw_order_data'] ?? [];
        if (!empty($rawOrderData)) {
            // Pass through all billing fields for parent customer creation
            $billingFields = [
                'BillingFirstName', 'BillingLastName', 'BillingCompany', 'BillingAddress', 
                'BillingAddress2', 'BillingCity', 'BillingState', 'BillingZipCode', 
                'BillingCountry', 'BillingPhoneNumber', 'BillingEmail'
            ];
            
            foreach ($billingFields as $field) {
                if (isset($rawOrderData[$field])) {
                    $netsuiteCustomer[$field] = $rawOrderData[$field];
                }
            }
            
            // Pass through ShipmentList for shipping address
            if (isset($rawOrderData['ShipmentList'])) {
                $netsuiteCustomer['ShipmentList'] = $rawOrderData['ShipmentList'];
            }
        }
        
        // Add defaultAddress and addressbook using the same logic as NetSuiteService
        $this->addCustomerAddresses($netsuiteCustomer);
        
        return $netsuiteCustomer;
    }
    
    /**
     * Format address for NetSuite (legacy method - returns array)
     */
    private function formatAddressForNetSuite($address) {
        return [
            'addr1' => $address['address1'] ?? '',
            'addr2' => $address['address2'] ?? '',
            'city' => $address['city'] ?? '',
            'state' => $address['state'] ?? '',
            'zip' => $address['postal_code'] ?? '',
            'country' => $address['country'] ?? 'US',
        ];
    }
    
    /**
     * Format address as string for NetSuite customer defaultAddress field
     */
    private function formatAddressAsString($address) {
        // Handle different address data formats
        $addr1 = $address['address1'] ?? $address['Address1'] ?? $address['addr1'] ?? '';
        $addr2 = $address['address2'] ?? $address['Address2'] ?? $address['addr2'] ?? '';
        $city = $address['city'] ?? $address['City'] ?? '';
        $state = $address['state'] ?? $address['State'] ?? '';
        $zip = $address['postal_code'] ?? $address['PostalCode'] ?? $address['zip'] ?? '';
        $country = $address['country'] ?? $address['Country'] ?? 'US';
        
        // Build address string
        $addressParts = [];
        
        if (!empty($addr1)) {
            $addressParts[] = $addr1;
        }
        
        if (!empty($addr2)) {
            $addressParts[] = $addr2;
        }
        
        if (!empty($city)) {
            $addressParts[] = $city;
        }
        
        if (!empty($state)) {
            $addressParts[] = $state;
        }
        
        if (!empty($zip)) {
            $addressParts[] = $zip;
        }
        
        if (!empty($country) && $country !== 'US') {
            $addressParts[] = $country;
        }
        
        return implode(', ', $addressParts);
    }
    
    /**
     * Add defaultAddress and addressbook to customer data from billing and shipping information
     */
    private function addCustomerAddresses(&$netsuiteCustomer) {
        // Build defaultAddress string from billing information
        $defaultAddress = $this->buildDefaultAddressString();
        if (!empty($defaultAddress)) {
            $netsuiteCustomer['defaultAddress'] = $defaultAddress;
        }
        
        // Build addressbook with billing and shipping addresses
        $addressbook = $this->buildAddressbook();
        if (!empty($addressbook)) {
            $netsuiteCustomer['addressbook'] = $addressbook;
        }
    }
    
    /**
     * Build defaultAddress string from billing data
     * Format: "{BillingFirstName} {BillingLastName}\n {BillingCompany}\n{BillingAddress},{BillingAddress2}\n{BillingCity}, {BillingState} {BillingZipCode}\n{BillingCountry}\n{BillingPhoneNumber}"
     */
    private function buildDefaultAddressString() {
        $billingAddress = $this->getBillingAddress();
        if (empty($billingAddress)) {
            return '';
        }
        
        $addressLines = [];
        
        // Line 1: {BillingFirstName} {BillingLastName}
        $fullName = trim($this->getFirstName() . ' ' . $this->getLastName());
        if (!empty($fullName)) {
            $addressLines[] = $fullName;
        }
        
        // Line 2: {BillingCompany} (with leading space)
        $company = $this->getCompany();
        if (!empty($company)) {
            $addressLines[] = ' ' . $company;
        }
        
        // Line 3: {BillingAddress},{BillingAddress2}
        $addressLine = '';
        if (!empty($billingAddress['address1'])) {
            $addressLine = $billingAddress['address1'];
        }
        if (!empty($billingAddress['address2'])) {
            $addressLine .= ',' . $billingAddress['address2'];
        }
        if (!empty($addressLine)) {
            $addressLines[] = $addressLine;
        }
        
        // Line 4: {BillingCity}, {BillingState} {BillingZipCode}
        $cityStateLine = '';
        if (!empty($billingAddress['city'])) {
            $cityStateLine = $billingAddress['city'];
        }
        if (!empty($billingAddress['state'])) {
            $cityStateLine .= (!empty($cityStateLine) ? ', ' : '') . $billingAddress['state'];
        }
        if (!empty($billingAddress['postal_code'])) {
            $cityStateLine .= ' ' . $billingAddress['postal_code'];
        }
        if (!empty($cityStateLine)) {
            $addressLines[] = $cityStateLine;
        }
        
        // Line 5: {BillingCountry}
        $country = $billingAddress['country'] ?? 'US';
        if (!empty($country)) {
            $addressLines[] = $country;
        }
        
        // Line 6: {BillingPhoneNumber}
        $phone = $this->getPhone();
        if (!empty($phone)) {
            $addressLines[] = $phone;
        }
        
        return implode("\n", $addressLines);
    }
    
    /**
     * Build addressbook with billing and shipping addresses
     */
    private function buildAddressbook() {
        $addressbook = [
            'items' => []
        ];
        
        // Add billing address
        $billingAddress = $this->buildBillingAddressItem();
        if (!empty($billingAddress)) {
            $addressbook['items'][] = $billingAddress;
        }
        
        // Add shipping address
        $shippingAddress = $this->buildShippingAddressItem();
        if (!empty($shippingAddress)) {
            $addressbook['items'][] = $shippingAddress;
        }
        
        return !empty($addressbook['items']) ? $addressbook : null;
    }
    
    /**
     * Build billing address item for addressbook
     */
    private function buildBillingAddressItem() {
        $billingAddress = $this->getBillingAddress();
        
        // Check if we have billing address data
        if (empty($billingAddress['address1']) && empty($billingAddress['city'])) {
            return null;
        }
        
        $addressItem = [
            'defaultBilling' => true,
            'defaultShipping' => false,
            'addressbookaddress' => []
        ];
        
        // Add address fields
        if (!empty($billingAddress['country'])) {
            $addressItem['addressbookaddress']['country'] = $billingAddress['country'];
        }
        if (!empty($billingAddress['postal_code'])) {
            $addressItem['addressbookaddress']['zip'] = $billingAddress['postal_code'];
        }
        
        // Format addressee as "{BillingFirstName} {BillingLastName}\n{BillingCompany}"
        $addresseeParts = [];
        $fullName = trim($this->getFirstName() . ' ' . $this->getLastName());
        if (!empty($fullName)) {
            $addresseeParts[] = $fullName;
        }
        $company = $this->getCompany();
        if (!empty($company)) {
            $addresseeParts[] = $company;
        }
        if (!empty($addresseeParts)) {
            $addressItem['addressbookaddress']['addressee'] = implode("\n", $addresseeParts);
        }
        
        if (!empty($billingAddress['address1'])) {
            $addressItem['addressbookaddress']['addr1'] = $billingAddress['address1'];
        }
        if (!empty($billingAddress['address2'])) {
            $addressItem['addressbookaddress']['addr2'] = $billingAddress['address2'];
        }
        if (!empty($billingAddress['city'])) {
            $addressItem['addressbookaddress']['city'] = $billingAddress['city'];
        }
        if (!empty($billingAddress['state'])) {
            $addressItem['addressbookaddress']['state'] = $billingAddress['state'];
        }
        
        return !empty($addressItem['addressbookaddress']) ? $addressItem : null;
    }
    
    /**
     * Build shipping address item for addressbook
     */
    private function buildShippingAddressItem() {
        $shippingAddress = $this->getShippingAddress();
        
        // Check if we have shipping address data and it's different from billing
        if (empty($shippingAddress['address1']) && empty($shippingAddress['city'])) {
            return null;
        }
        
        // Check if shipping is same as billing
        $billingAddress = $this->getBillingAddress();
        if ($shippingAddress['address1'] === $billingAddress['address1'] && 
            $shippingAddress['city'] === $billingAddress['city'] &&
            $shippingAddress['postal_code'] === $billingAddress['postal_code']) {
            return null; // Don't add duplicate address
        }
        
        $addressItem = [
            'defaultBilling' => false,
            'defaultShipping' => true,
            'addressbookaddress' => []
        ];
        
        // Add address fields
        if (!empty($shippingAddress['country'])) {
            $addressItem['addressbookaddress']['country'] = $shippingAddress['country'];
        }
        if (!empty($shippingAddress['postal_code'])) {
            $addressItem['addressbookaddress']['zip'] = $shippingAddress['postal_code'];
        }
        // Format addressee as "{ShipmentFirstName} {ShipmentLastName}\n{ShipmentCompany}"
        $addresseeParts = [];
        $fullName = trim($this->getFirstName() . ' ' . $this->getLastName());
        if (!empty($fullName)) {
            $addresseeParts[] = $fullName;
        }
        $shippingCompany = $this->getShippingCompany() ?: $this->getCompany();
        if (!empty($shippingCompany)) {
            $addresseeParts[] = $shippingCompany;
        }
        if (!empty($addresseeParts)) {
            $addressItem['addressbookaddress']['addressee'] = implode("\n", $addresseeParts);
        }
        if (!empty($shippingAddress['address1'])) {
            $addressItem['addressbookaddress']['addr1'] = $shippingAddress['address1'];
        }
        if (!empty($shippingAddress['address2'])) {
            $addressItem['addressbookaddress']['addr2'] = $shippingAddress['address2'];
        }
        if (!empty($shippingAddress['city'])) {
            $addressItem['addressbookaddress']['city'] = $shippingAddress['city'];
        }
        if (!empty($shippingAddress['state'])) {
            $addressItem['addressbookaddress']['state'] = $shippingAddress['state'];
        }
        
        return !empty($addressItem['addressbookaddress']) ? $addressItem : null;
    }
    
    /**
     * Create customer from 3DCart order data
     */
    public static function fromOrderData($orderData) {
        // Extract customer email from QuestionList (QuestionID=1) as per requirements
        $customerEmail = '';
        if (isset($orderData['QuestionList']) && is_array($orderData['QuestionList'])) {
            foreach ($orderData['QuestionList'] as $question) {
                if (isset($question['QuestionID']) && $question['QuestionID'] == 1) {
                    $questionAnswer = $question['QuestionAnswer'] ?? '';
                    // Validate if the QuestionAnswer is a valid email format
                    if (!empty($questionAnswer) && filter_var($questionAnswer, FILTER_VALIDATE_EMAIL)) {
                        $customerEmail = $questionAnswer;
                    }
                    break;
                }
            }
        }
        
        // Fallback to billing email if QuestionList email not found or invalid
        if (empty($customerEmail)) {
            $billingEmail = $orderData['BillingEmail'] ?? '';
            if (!empty($billingEmail) && filter_var($billingEmail, FILTER_VALIDATE_EMAIL)) {
                $customerEmail = $billingEmail;
            }
            // If no valid email found, leave empty - customer will be created without email
        }
        
        // Extract first name and last name from ShipmentList as per requirements
        $firstName = '';
        $lastName = '';
        if (!empty($orderData['ShipmentList']) && is_array($orderData['ShipmentList'])) {
            $firstShipment = $orderData['ShipmentList'][0] ?? [];
            $firstName = $firstShipment['ShipmentFirstName'] ?? '';
            $lastName = $firstShipment['ShipmentLastName'] ?? '';
        }
        
        // Fallback to billing names if ShipmentList names not found
        if (empty($firstName) && empty($lastName)) {
            $firstName = $orderData['BillingFirstName'] ?? '';
            $lastName = $orderData['BillingLastName'] ?? '';
        }
        
        $customerData = [
            'id' => $orderData['CustomerID'] ?? null,
            'email' => $customerEmail,
            'firstname' => $firstName,
            'lastname' => $lastName,
            'company' => $orderData['BillingCompany'] ?? '', // Company from billing for parent customer creation
            'phone' => $orderData['BillingPhoneNumber'] ?? '',
            'billing_address' => [
                'address1' => $orderData['BillingAddress'] ?? '',
                'address2' => $orderData['BillingAddress2'] ?? '',
                'city' => $orderData['BillingCity'] ?? '',
                'state' => $orderData['BillingState'] ?? '',
                'postal_code' => $orderData['BillingZipCode'] ?? '',
                'country' => $orderData['BillingCountry'] ?? 'US',
            ],
            'shipping_address' => self::extractShippingAddress($orderData),
            'shipping_company' => self::extractShippingCompany($orderData),
            
            // Store raw order data for parent customer creation with billing details
            'raw_order_data' => $orderData
        ];
        
        return new self($customerData);
    }
    
    /**
     * Extract shipping address from order data (handles both direct fields and ShipmentList)
     */
    private static function extractShippingAddress($orderData) {
        // First try direct shipping fields
        $shippingAddress = [
            'address1' => $orderData['ShippingAddress'] ?? '',
            'address2' => $orderData['ShippingAddress2'] ?? '',
            'city' => $orderData['ShippingCity'] ?? '',
            'state' => $orderData['ShippingState'] ?? '',
            'postal_code' => $orderData['ShippingZipCode'] ?? '',
            'country' => $orderData['ShippingCountry'] ?? 'US',
        ];
        
        // If no direct shipping fields, try ShipmentList
        if (empty($shippingAddress['address1']) && empty($shippingAddress['city']) && 
            !empty($orderData['ShipmentList']) && is_array($orderData['ShipmentList'])) {
            $firstShipment = $orderData['ShipmentList'][0] ?? [];
            $shippingAddress = [
                'address1' => $firstShipment['ShipmentAddress'] ?? '',
                'address2' => $firstShipment['ShipmentAddress2'] ?? '',
                'city' => $firstShipment['ShipmentCity'] ?? '',
                'state' => $firstShipment['ShipmentState'] ?? '',
                'postal_code' => $firstShipment['ShipmentZipCode'] ?? '',
                'country' => $firstShipment['ShipmentCountry'] ?? 'US',
                'company' => $firstShipment['ShipmentCompany'] ?? '',
            ];
        }
        
        // If still no shipping address, fall back to billing
        if (empty($shippingAddress['address1']) && empty($shippingAddress['city'])) {
            $shippingAddress = [
                'address1' => $orderData['BillingAddress'] ?? '',
                'address2' => $orderData['BillingAddress2'] ?? '',
                'city' => $orderData['BillingCity'] ?? '',
                'state' => $orderData['BillingState'] ?? '',
                'postal_code' => $orderData['BillingZipCode'] ?? '',
                'country' => $orderData['BillingCountry'] ?? 'US',
            ];
        }
        
        return $shippingAddress;
    }
    
    /**
     * Extract shipping company from order data
     */
    private static function extractShippingCompany($orderData) {
        // First try direct shipping company field
        if (!empty($orderData['ShippingCompany'])) {
            return $orderData['ShippingCompany'];
        }
        
        // Try ShipmentList
        if (!empty($orderData['ShipmentList']) && is_array($orderData['ShipmentList'])) {
            $firstShipment = $orderData['ShipmentList'][0] ?? [];
            if (!empty($firstShipment['ShipmentCompany'])) {
                return $firstShipment['ShipmentCompany'];
            }
        }
        
        // Fall back to billing company
        return $orderData['BillingCompany'] ?? '';
    }
    
    /**
     * Create customer from CSV row data
     */
    public static function fromCsvData($csvRow, $mapping) {
        $customerData = [];
        
        foreach ($mapping as $csvField => $customerField) {
            if (isset($csvRow[$csvField])) {
                $customerData[$customerField] = $csvRow[$csvField];
            }
        }
        
        return new self($customerData);
    }
    
    /**
     * Get customer summary for logging/notifications
     */
    public function getSummary() {
        return [
            'customer_id' => $this->getId(),
            'email' => $this->getEmail(),
            'name' => $this->getFullName(),
            'company' => $this->getCompany(),
            'type' => $this->getCustomerType(),
            'phone' => $this->getPhone(),
        ];
    }
    
    /**
     * Check if customer has required fields for NetSuite
     */
    public function hasRequiredFields() {
        $errors = $this->validate();
        return empty($errors);
    }
    
    /**
     * Get missing required fields
     */
    public function getMissingFields() {
        $errors = $this->validate();
        $missingFields = [];
        
        foreach ($errors as $error) {
            if (strpos($error, 'Missing required') !== false) {
                preg_match('/Missing required.*field: (.+)/', $error, $matches);
                if (isset($matches[1])) {
                    $missingFields[] = $matches[1];
                }
            }
        }
        
        return $missingFields;
    }
    
    /**
     * Sanitize customer data
     */
    public function sanitize() {
        $this->data['firstname'] = Validator::sanitizeString($this->data['firstname'] ?? '', 50);
        $this->data['lastname'] = Validator::sanitizeString($this->data['lastname'] ?? '', 50);
        $this->data['company'] = Validator::sanitizeString($this->data['company'] ?? '', 100);
        $this->data['email'] = Validator::sanitizeEmail($this->data['email'] ?? '');
        
        // Sanitize phone number
        if (isset($this->data['phone'])) {
            $this->data['phone'] = preg_replace('/[^0-9\-\+\(\)\.\s]/', '', $this->data['phone']);
        }
        
        return $this;
    }
    
    /**
     * Check if this customer matches another customer (for duplicate detection)
     */
    public function matches(Customer $otherCustomer) {
        // Primary match: email
        if (!empty($this->getEmail()) && !empty($otherCustomer->getEmail())) {
            return strtolower($this->getEmail()) === strtolower($otherCustomer->getEmail());
        }
        
        // Secondary match: name and phone
        if (!empty($this->getPhone()) && !empty($otherCustomer->getPhone())) {
            $nameMatch = strtolower($this->getFullName()) === strtolower($otherCustomer->getFullName());
            $phoneMatch = preg_replace('/[^0-9]/', '', $this->getPhone()) === 
                         preg_replace('/[^0-9]/', '', $otherCustomer->getPhone());
            
            return $nameMatch && $phoneMatch;
        }
        
        return false;
    }
}