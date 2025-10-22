<?php

namespace ElgioPay\SDK;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class ElgioPayClient
{
    private Client $client;
    private string $apiKey;
    private string $baseUrl;
    private string $environment;

    public function __construct(?string $environment = null, ?string $apiKey = null)
    {
        // Get from environment variables if not provided
        $this->apiKey = $apiKey ?? $_ENV['ELGIOPAY_API_KEY'] ?? getenv('ELGIOPAY_API_KEY');
        $this->environment = $environment ?? $_ENV['ELGIOPAY_ENV'] ?? getenv('ELGIOPAY_ENV') ?? 'prod';
        
        // Validate API key
        if (empty($this->apiKey)) {
            throw new ElgioPayException('ELGIOPAY_API_KEY is required. Set it as environment variable or pass it to constructor.');
        }

        if(!in_array($this->environment, ['sandbox', 'prod'])) {
            throw new ElgioPayException('Invalid environment. Allowed values are "sandbox" or "prod".');
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
            // Validate required fields
            $this->validatePaymentData($paymentData);

            $response = $this->client->post('/api/v1/payments', ['json' => $paymentData]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            throw new ElgioPayException('Payment initiation failed: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Validate payment data
     */
    private function validatePaymentData(array $paymentData): void
    {
        $requiredFields = ['amount', 'payment_method', 'customer_phone'];

        foreach ($requiredFields as $field) {
            if (!isset($paymentData[$field]) || empty($paymentData[$field])) {
                throw new ElgioPayException("Required field '{$field}' is missing or empty");
            }
        }

        if (!is_numeric($paymentData['amount']) || $paymentData['amount'] <= 0) {
            throw new ElgioPayException('Amount must be a positive number');
        }

        if (!in_array($paymentData['payment_method'], [PaymentMethod::MTN_MOBILE_MONEY->value, PaymentMethod::ORANGE_MONEY->value])) {
            throw new ElgioPayException('Invalid payment method');
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
    public function createMTNPayment(array $paymentData): array
    {
        $paymentData['payment_method'] = PaymentMethod::MTN_MOBILE_MONEY->value;
        $paymentData['currency'] = $paymentData['currency'] ?? 'XAF';

        return $this->initiatePayment($paymentData);
    }

    /**
     * Create Orange Money payment
     */
    public function createOrangePayment(array $paymentData): array
    {
        $paymentData['payment_method'] = PaymentMethod::ORANGE_MONEY->value;
        $paymentData['currency'] = $paymentData['currency'] ?? 'XAF';

        return $this->initiatePayment($paymentData);
    }

    /**
     * Create payment for Cameroon (XAF currency, Cameroon phone format)
     */
    public function createCameroonPayment(array $paymentData): array
    {
        // Normalize Cameroon phone number
        if (isset($paymentData['customer_phone'])) {
            $paymentData['customer_phone'] = $this->normalizeCameroonPhone($paymentData['customer_phone']);
        }

        $paymentData['currency'] = 'XAF';

        return $this->initiatePayment($paymentData);
    }

    /**
     * Create MTN Mobile Money payment for Cameroon
     */
    public function createMTNCameroonPayment(array $paymentData): array
    {
        $paymentData['payment_method'] = PaymentMethod::MTN_MOBILE_MONEY->value;
        return $this->createCameroonPayment($paymentData);
    }

    /**
     * Create Orange Money payment for Cameroon
     */
    public function createOrangeCameroonPayment(array $paymentData): array
    {
        $paymentData['payment_method'] = PaymentMethod::ORANGE_MONEY->value;
        return $this->createCameroonPayment($paymentData);
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
     * Detect payment method based on phone number (Cameroon)
     * MTN: 67, 650, 651, 652, 653, 654
     * Orange: 69, 655, 656, 657, 658, 659
     */
    public static function detectPaymentMethod(string $phone): string
    {
        // Remove all non-digit characters except +
        $phone = preg_replace('/[^\d+]/', '', $phone);

        // Extract the significant digits (after country code)
        if (preg_match('/^(\+?237)?([67]\d{8})$/', $phone, $matches)) {
            $number = $matches[2];

            // Check first 2-3 digits to determine carrier
            // MTN patterns: 67x, 650, 651, 652, 653, 654
            if (preg_match('/^(67|650|651|652|653|654)/', $number)) {
                return PaymentMethod::MTN_MOBILE_MONEY->value;
            }

            // Orange patterns: 69x, 655, 656, 657, 658, 659
            if (preg_match('/^(69|655|656|657|658|659)/', $number)) {
                return PaymentMethod::ORANGE_MONEY->value;
            }
        }

        // Default to MTN if unable to detect
        return PaymentMethod::MTN_MOBILE_MONEY->value;
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
                $sandboxUrl = $_ENV['ELGIOPAY_SANDBOX_URL'] ?? getenv('ELGIOPAY_SANDBOX_URL');
                return $sandboxUrl ?: 'https://sandbox-api.elgiopay.com';
            case 'prod':
            default:
                return 'http://api.elgiopay.test';
        }
    }
}