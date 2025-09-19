# ElgioPay PHP SDK

[![Latest Stable Version](https://poser.pugx.org/elgiosoft/sofipayments-sdk/v)](https://packagist.org/packages/elgiosoft/sofipayments-sdk)
[![Total Downloads](https://poser.pugx.org/elgiosoft/sofipayments-sdk/downloads)](https://packagist.org/packages/elgiosoft/sofipayments-sdk)
[![License](https://poser.pugx.org/elgiosoft/sofipayments-sdk/license)](https://packagist.org/packages/elgiosoft/sofipayments-sdk)
[![PHP Version Require](https://poser.pugx.org/elgiosoft/sofipayments-sdk/require/php)](https://packagist.org/packages/elgiosoft/sofipayments-sdk)

PHP SDK for integrating with the ElgioPay API. Optimized for mobile money payments in Cameroon and West Africa.

## Features

- 🇨🇲 **Cameroon-First**: Optimized for XAF currency and Cameroon phone formats
- 📱 **Mobile Money**: MTN Mobile Money and Orange Money support
- 🔄 **Auto-Retry**: Built-in payment retry mechanism with exponential backoff
- ✅ **Phone Validation**: Automatic phone number normalization for Cameroon
- 🛡️ **Error Handling**: Comprehensive error handling with detailed messages
- 🔗 **Webhooks**: Easy webhook integration for payment status updates
- 💰 **Multi-Currency**: Support for XAF, XOF, and EUR currencies

## Installation

```bash
composer require elgiosoft/elgiopay-php-sdk
```

## Requirements

- PHP 8.0 or higher
- Guzzle HTTP client

## Table of Contents

- [Features](#features)
- [Installation](#installation) 
- [Quick Start for Cameroon](#quick-start-for-cameroon)
- [Usage](#usage)
  - [Basic Setup](#basic-setup)
  - [MTN Mobile Money](#create-mtn-mobile-money-payment-cameroon)
  - [Orange Money](#create-orange-money-payment-cameroon)
  - [Payment Status](#check-payment-status)
  - [Payment Verification](#verify-payment)
- [Supported Payment Methods](#supported-payment-methods)
- [Configuration](#configuration)
- [Error Handling](#error-handling)
- [Webhooks](#webhook-handling)
- [Testing](#testing)
- [Support](#support)

## Usage

### Basic Setup

#### Using Environment Variables (Recommended)

Create a `.env` file in your project root:

```env
ELGIOPAY_API_KEY=pk_test_your_api_key
ELGIOPAY_ENV=sandbox
```

```php
use ElgioPay\SDK\ElgioPayClient;

// Automatically reads from ELGIOPAY_API_KEY and ELGIOPAY_ENV
$client = new ElgioPayClient();
```

#### Manual Configuration

```php
use ElgioPay\SDK\ElgioPayClient;

// For testing (sandbox)
$client = new ElgioPayClient('pk_test_your_api_key', 'sandbox');

// For production (live)
$client = new ElgioPayClient('pk_live_your_api_key', 'prod');
```

### Create MTN Mobile Money Payment (Cameroon)

```php
try {
    // Cameroon-optimized method with XAF currency
    $result = $client->createMTNCameroonPayment(
        amount: 1000.00,
        customerPhone: '+237677123456', // or '677123456'
        options: [
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'reference' => 'ORDER-123',
            'metadata' => [
                'order_id' => 123,
                'product' => 'Premium Plan'
            ]
        ]
    );

    echo "Transaction ID: " . $result['transaction_id'];
    echo "Status: " . $result['status'];
} catch (\Sofi\SDK\SofiException $e) {
    echo "Payment failed: " . $e->getMessage();
}
```

### Create MTN Mobile Money Payment (Legacy)

```php
try {
    $result = $client->createMTNPayment(
        amount: 1000.00,
        customerPhone: '+237677123456',
        options: [
            'currency' => 'XAF', // Now defaults to XAF
            'customer_name' => 'John Doe',
            'reference' => 'ORDER-123'
        ]
    );
} catch (\Sofi\SDK\SofiException $e) {
    echo "Payment failed: " . $e->getMessage();
}
```

### Create Orange Money Payment (Cameroon)

```php
try {
    // Cameroon-optimized method with XAF currency
    $result = $client->createOrangeCameroonPayment(
        amount: 5000.00,
        customerPhone: '+237677123456', // or '677123456'
        options: [
            'customer_name' => 'Jane Doe',
            'reference' => 'INV-456'
        ]
    );

    echo "Payment URL: " . $result['payment_url'];
} catch (\Sofi\SDK\SofiException $e) {
    echo "Payment failed: " . $e->getMessage();
}
```

### Create Orange Money Payment (Legacy)

```php
try {
    $result = $client->createOrangePayment(
        amount: 5000.00,
        customerPhone: '+237677123456',
        options: [
            'currency' => 'XAF', // Now defaults to XAF
            'reference' => 'INV-456'
        ]
    );
} catch (\Sofi\SDK\SofiException $e) {
    echo "Payment failed: " . $e->getMessage();
}
```

### Check Payment Status

```php
try {
    $status = $client->getPaymentStatus('txn_abc123');
    
    echo "Status: " . $status['status'];
    echo "Amount: " . $status['amount'];
} catch (\Sofi\SDK\SofiException $e) {
    echo "Status check failed: " . $e->getMessage();
}
```

### Verify Payment

```php
try {
    $verification = $client->verifyPayment('txn_abc123');
    
    if ($verification['verified']) {
        echo "Payment verified successfully!";
    } else {
        echo "Payment verification failed.";
    }
} catch (\Sofi\SDK\SofiException $e) {
    echo "Verification failed: " . $e->getMessage();
}
```

### Payment with Retry

```php
try {
    $result = $client->createPaymentWithRetry([
        'amount' => 2000.00,
        'payment_method' => 'mtn_mobile_money',
        'customer_phone' => '+237677123456',
        'currency' => 'XAF'
    ], maxRetries: 3);
    
    echo "Payment created: " . $result['transaction_id'];
} catch (\Sofi\SDK\SofiException $e) {
    echo "Payment failed after retries: " . $e->getMessage();
}
```

## Supported Payment Methods

- **MTN Mobile Money**: Available in Cameroon, Côte d'Ivoire, Burkina Faso, Ghana
- **Orange Money**: Available in Cameroon, Côte d'Ivoire, Burkina Faso, Mali, Senegal

### Supported Currencies

- **XAF** (Central African CFA Franc) - Primary currency for Cameroon
- **XOF** (West African CFA Franc) - For other West African countries  
- **EUR** (Euro) - For international transactions

### Phone Number Formats

**Cameroon**: `+237 6XX XXX XXX` or `+237 7XX XXX XXX`
- Examples: `+237677123456`, `237677123456`, `677123456`

## Quick Start for Cameroon

```php
use Sofi\SDK\ElgioPayClient;

// Initialize with your Sofi API key
$client = new ElgioPayClient('pk_test_your_api_key', 'sandbox');

// Simple MTN payment in XAF
$result = $client->createMTNCameroonPayment(
    amount: 5000.00, // 5000 XAF
    customerPhone: '677123456',
    options: [
        'customer_name' => 'Jean Dupont',
        'reference' => 'FACTURE-001'
    ]
);

// Simple Orange payment in XAF  
$result = $client->createOrangeCameroonPayment(
    amount: 2500.00, // 2500 XAF
    customerPhone: '+237677123456',
    options: [
        'reference' => 'CMD-002'
    ]
);
```

## Configuration

### API Keys

You only need two things to get started:

1. **API Key**: Get your API key from the Sofi dashboard
2. **Environment**: Choose between sandbox (testing) or live (production)

```php
// Sandbox environment (for testing)
$client = new ElgioPayClient('pk_test_your_sandbox_api_key', 'sandbox');

// Live environment (for production) 
$client = new ElgioPayClient('pk_live_your_live_api_key', 'prod');
```

### Environment Variables (Recommended)

```env
# For testing
SOFI_API_KEY=pk_test_your_sandbox_api_key
SOFI_BASE_URL=https://sandbox-api.sofipayments.com

# For production
SOFI_API_KEY=pk_live_your_live_api_key  
SOFI_BASE_URL=https://api.sofipayments.com
```

Then in your code:
```php
$client = new ElgioPayClient(
    env('SOFI_API_KEY'),
    env('SOFI_BASE_URL')
);
```

## Error Handling

The SDK throws `SofiException` for all API-related errors:

```php
try {
    $result = $client->initiatePayment($paymentData);
} catch (\Sofi\SDK\SofiException $e) {
    $errorMessage = $e->getMessage();
    $errorCode = $e->getCode();
    $responseData = $e->getResponse(); // API response if available
    
    // Handle error appropriately
}
```

## Webhook Handling

Set up webhooks in your application to receive payment status updates:

```php
// In your webhook endpoint
$data = json_decode(file_get_contents('php://input'), true);

if ($data['status'] === 'completed') {
    // Payment successful
    $transactionId = $data['transaction_id'];
    // Update your order status
} elseif ($data['status'] === 'failed') {
    // Payment failed
    $reason = $data['failure_reason'];
    // Handle failed payment
}
```

## Testing

### Sandbox Environment

Use sandbox API keys (starting with `pk_test_`) for testing. All sandbox transactions are simulated and no real money is processed.

```php
// Testing setup
$client = new ElgioPayClient('pk_test_your_sandbox_key', 'sandbox');

// Test payment
$result = $client->createMTNCameroonPayment(1000.00, '677123456');
```

### Getting API Keys

1. Sign up at [Sofi Dashboard](https://dashboard.sofipayments.com)
2. Create a new application 
3. Copy your API keys:
   - `pk_test_...` for testing
   - `pk_live_...` for production

### Test Phone Numbers

For sandbox testing, use these test phone numbers:
- MTN: `677123456`, `677123457`, `677123458` 
- Orange: `677123456`, `677123457`, `677123458`

All sandbox payments will automatically succeed after a few seconds.

## Support

- 📖 **Documentation**: [https://docs.sofipayments.com](https://docs.sofipayments.com)
- 🐛 **Issues**: [GitHub Issues](https://github.com/elgiosoft/sofipayments-sdk/issues)
- 💬 **Support**: [developers@elgiosoft.com](mailto:developers@elgiosoft.com)
- 🌐 **Website**: [https://sofipayments.com](https://sofipayments.com)

## Contributing

We welcome contributions! Please feel free to submit a Pull Request.

## License

MIT License - see the [LICENSE](LICENSE) file for details.

## About Elgiosoft

Sofi Payment SDK is developed by [Elgiosoft Ltd](https://elgiosoft.com), a leading fintech company specializing in mobile money solutions for Africa.