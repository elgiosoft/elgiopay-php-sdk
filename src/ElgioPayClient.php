<?php

namespace ElgioPay\SDK;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class ElgioPayClient
{
    private $client;
    private $apiKey;
    private $baseUrl;
    private $environment;

    public function __construct(?string $apiKey = null, ?string $environment = null)
    {
        // Get from environment variables if not provided
        $this->apiKey = $apiKey ?? $_ENV['ELGIOPAY_API_KEY'] ?? getenv('ELGIOPAY_API_KEY');
        $this->environment = $environment ?? $_ENV['ELGIOPAY_ENV'] ?? getenv('ELGIOPAY_ENV') ?? 'prod';
        
        // Validate API key
        if (empty($this->apiKey)) {
            throw new ElgioPayException('ELGIOPAY_API_KEY is required. Set it as environment variable or pass it to constructor.');
        }
        
        $this->baseUrl = $this->getBaseUrl($this->environment);
        
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'timeout' => 30,
        ]);
    }

    /**
     * Initiate a payment
     */
    public function initiatePayment(array $paymentData): array
    {
        try {
            $response = $this->client->post('/api/v1/payments', [
                'json' => $paymentData
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            throw new ElgioPayException('Payment initiation failed: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Get payment status
     */
    public function getPaymentStatus(string $transactionId): array
    {
        try {
            $response = $this->client->get("/api/v1/payments/{$transactionId}");

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            throw new ElgioPayException('Failed to get payment status: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Verify a payment
     */
    public function verifyPayment(string $transactionId): array
    {
        try {
            $response = $this->client->post("/api/v1/payments/{$transactionId}/verify");

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            throw new ElgioPayException('Payment verification failed: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Create MTN Mobile Money payment
     */
    public function createMTNPayment(float $amount, string $customerPhone, array $options = []): array
    {
        $paymentData = array_merge([
            'amount' => $amount,
            'payment_method' => 'mtn_mobile_money',
            'customer_phone' => $customerPhone,
            'currency' => 'XAF',
        ], $options);

        return $this->initiatePayment($paymentData);
    }

    /**
     * Create Orange Money payment
     */
    public function createOrangePayment(float $amount, string $customerPhone, array $options = []): array
    {
        $paymentData = array_merge([
            'amount' => $amount,
            'payment_method' => 'orange_money',
            'customer_phone' => $customerPhone,
            'currency' => 'XAF',
        ], $options);

        return $this->initiatePayment($paymentData);
    }

    /**
     * Create payment for Cameroon (XAF currency, Cameroon phone format)
     */
    public function createCameroonPayment(float $amount, string $paymentMethod, string $customerPhone, array $options = []): array
    {
        // Normalize Cameroon phone number
        $customerPhone = $this->normalizeCameroonPhone($customerPhone);
        
        $paymentData = array_merge([
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'customer_phone' => $customerPhone,
            'currency' => 'XAF',
        ], $options);

        return $this->initiatePayment($paymentData);
    }

    /**
     * Create MTN Mobile Money payment for Cameroon
     */
    public function createMTNCameroonPayment(float $amount, string $customerPhone, array $options = []): array
    {
        return $this->createCameroonPayment($amount, 'mtn_mobile_money', $customerPhone, $options);
    }

    /**
     * Create Orange Money payment for Cameroon
     */
    public function createOrangeCameroonPayment(float $amount, string $customerPhone, array $options = []): array
    {
        return $this->createCameroonPayment($amount, 'orange_money', $customerPhone, $options);
    }

    /**
     * Normalize Cameroon phone number to +237 format
     */
    private function normalizeCameroonPhone(string $phone): string
    {
        // Remove all non-digit characters except +
        $phone = preg_replace('/[^\d+]/', '', $phone);
        
        // Handle different formats
        if (preg_match('/^(\+237|237)([67]\d{8})$/', $phone, $matches)) {
            return '+237' . $matches[2];
        } elseif (preg_match('/^([67]\d{8})$/', $phone, $matches)) {
            return '+237' . $matches[1];
        }
        
        // Return as is if format doesn't match (will fail validation)
        return $phone;
    }

    /**
     * Helper method to create payment with automatic retry
     */
    public function createPaymentWithRetry(array $paymentData, int $maxRetries = 3): array
    {
        $attempts = 0;
        $lastException = null;

        while ($attempts < $maxRetries) {
            try {
                return $this->initiatePayment($paymentData);
            } catch (ElgioPayException $e) {
                $lastException = $e;
                $attempts++;
                
                if ($attempts < $maxRetries) {
                    sleep(pow(2, $attempts)); // Exponential backoff
                }
            }
        }

        throw $lastException;
    }

    /**
     * Set custom timeout
     */
    public function setTimeout(int $timeout): self
    {
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'timeout' => $timeout,
        ]);

        return $this;
    }

    /**
     * Get base URL based on environment
     */
    private function getBaseUrl(string $environment): string
    {
        switch ($environment) {
            case 'sandbox':
                return 'https://sandbox-api.elgiopay.com';
            case 'prod':
            default:
                return 'https://api.elgiopay.com';
        }
    }
}