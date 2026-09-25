# ElgioPay PHP SDK

[![Latest Stable Version](https://poser.pugx.org/elgiosoft/elgiopay-php-sdk/v)](https://packagist.org/packages/elgiosoft/elgiopay-php-sdk)
[![Total Downloads](https://poser.pugx.org/elgiosoft/elgiopay-php-sdk/downloads)](https://packagist.org/packages/elgiosoft/elgiopay-php-sdk)
[![License](https://poser.pugx.org/elgiosoft/elgiopay-php-sdk/license)](https://packagist.org/packages/elgiosoft/elgiopay-php-sdk)
[![PHP Version Require](https://poser.pugx.org/elgiosoft/elgiopay-php-sdk/require/php)](https://packagist.org/packages/elgiosoft/elgiopay-php-sdk)

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
  - [Connected Merchants](#connected-merchants-platform-apps)
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

Constructor signature: `new ElgioPayClient(?string $environment = null, ?string $apiKey = null)`. Both fall back to `ELGIOPAY_ENV` / `ELGIOPAY_API_KEY` env vars when omitted.

```php
use ElgioPay\SDK\ElgioPayClient;

// For testing (sandbox)
$client = new ElgioPayClient('sandbox', 'pk_test_your_api_key');

// For production (live)
$client = new ElgioPayClient('prod', 'pk_live_your_api_key');
```

### Creating a payment

Every payment — MTN, Orange, current markets and any we add later —
goes through a single method: `initiatePayment()`. Pick the
`payment_method` you need and pass the payload.

```php
// Signature
$client->initiatePayment(array $paymentData): array

// Payload shape
// [
//     'amount'          => float,
//     'currency'        => string,   // 'XAF' | 'XOF' | 'EUR' | 'USD'
//     'payment_method'  => string,   // 'mtn_mobile_money' | 'orange_money'
//     'channel_code'    => string,   // e.g. 'ORANGE_CMR' — alternative to payment_method
//     'customer_phone'  => string,   // E.164, e.g. '+237677123456'
//     'customer_name'   => string,   // optional
//     'customer_email'  => string,   // optional
//     'reference'       => string,   // optional
//     'metadata'        => array,    // optional
//     'surcharge'       => float,    // optional — SURCHARGE wallet add-on
// ]
```

Provide **either** `payment_method` **or** `channel_code` (a code from
`listChannels()`). With `channel_code`, the currency comes from the channel:

```php
$result = $client->initiatePayment([
    'amount'         => 1000,
    'channel_code'   => 'ORANGE_CMR',
    'customer_phone' => '+237699000000',
]);
```

#### MTN Mobile Money (Cameroon)

```php
try {
    $result = $client->initiatePayment([
        'amount'         => 1000.00,
        'currency'       => 'XAF',
        'payment_method' => 'mtn_mobile_money',
        'customer_phone' => '+237677123456',
        'customer_name'  => 'John Doe',
        'customer_email' => 'john@example.com',
        'reference'      => 'ORDER-123',
        'metadata'       => [
            'order_id' => 123,
            'product'  => 'Premium Plan',
        ],
    ]);

    echo "Transaction ID: " . $result['transaction_id'];
    echo "Status: " . $result['status'];
} catch (\ElgioPay\SDK\ElgioPayException $e) {
    echo "Payment failed: " . $e->getMessage();
}
```

#### Orange Money (Cameroon)

```php
try {
    $result = $client->initiatePayment([
        'amount'         => 5000.00,
        'currency'       => 'XAF',
        'payment_method' => 'orange_money',
        'customer_phone' => '+237677123456',
        'customer_name'  => 'Jane Doe',
        'reference'      => 'INV-456',
    ]);

    echo "Payment URL: " . $result['payment_url'];
} catch (\ElgioPay\SDK\ElgioPayException $e) {
    echo "Payment failed: " . $e->getMessage();
}
```

#### Normalising Cameroon phone numbers

If your customer input arrives in mixed formats (`677…`, `237…`,
`+237…`), pipe it through `normalizeCameroonPhone()` before calling
`initiatePayment()`.

```php
$phone = $client->normalizeCameroonPhone('677123456'); // → +237677123456

$result = $client->initiatePayment([
    'amount'         => 1000.00,
    'currency'       => 'XAF',
    'payment_method' => 'mtn_mobile_money',
    'customer_phone' => $phone,
]);
```

### Channels

List the payment channels available to your app (mobile money, bills,
cash-in). Pass a channel's `code` as `channel_code` on `initiatePayment()` /
`createPayout()`. The provider that fulfils a channel is internal and never
returned.

```php
// All channels
$channels = $client->listChannels();

// Filtered: payout-capable mobile-money channels in Cameroon
$payoutChannels = $client->listChannels([
    'type'      => 'mobile_money',
    'direction' => 'payout',
    'country'   => 'CMR',
]);
// $payoutChannels['data'] => [ ['code' => 'ORANGE_CMR', 'currency' => 'XAF', ...], ... ]

// Filters: type, direction, country (ISO-3), category, name
```

### Connected Merchants (platform apps)

If your merchant is a **platform**, you can onboard sub-merchants, charge on
their behalf and settle them. Two things must both be true or the API answers
`403 NOT_A_PLATFORM`: the merchant is a platform, *and* the specific app whose
key you're using is flagged as a platform app. A platform's own storefront app
is deliberately not one.

A connected merchant lives inside exactly one app: the app whose key created
it. There's no app parameter, and a merchant created with app A's key is
invisible to app B's key — listing, lookup, balance, payouts and
`sub_merchant_id` charges are all scoped to the calling app.

```php
$connected = $client->connectedMerchants();

// 1. Onboard. Provide `email` plus company_name OR first_name.
$merchant = $connected->create([
    'email' => 'shop@example.cm',
    'company_name' => 'Boutique Mballa',
    'metadata' => ['external_id' => 'shop_42'],
]);
// => ['merchant_id' => 'mch_…', 'kyc_status' => 'pending', 'status' => 'created', ...]

// 2. Charge on its behalf — the net amount credits ITS balance, not your float.
$client->initiatePayment([
    'amount' => 5000,
    'customer_phone' => '+237677389120',
    'sub_merchant_id' => $merchant['merchant_id'],
]);

// 3. Read and settle.
$balance = $connected->getBalance($merchant['merchant_id']);
$connected->createPayout($merchant['merchant_id'], [
    'amount' => 4000,
    'payout_method' => 'mtn_mobile_money',
    'recipient_name' => 'Jean Mballa',
    'recipient_phone' => '677389120',
]);

// Listing + lifecycle
$page = $connected->list(['per_page' => 25]);   // ['data' => [...], 'meta' => [...]]
$connected->get($merchant['merchant_id']);
$connected->deactivate($merchant['merchant_id']); // freeze
$connected->reactivate($merchant['merchant_id']);
```

Branch on the machine-readable code rather than the message:

```php
try {
    $connected->create(['email' => 'shop@example.cm']);
} catch (\ElgioPay\SDK\ElgioPayException $e) {
    if (($e->getResponse()['error'] ?? null) === 'NOT_A_PLATFORM') {
        // This app's merchant isn't a platform.
    }
}
```

Codes: `NOT_A_PLATFORM`, `NOT_YOUR_MERCHANT`, `MERCHANT_INACTIVE`,
`PAYOUT_FAILED`, `VALIDATION_ERROR`, and on a charge —
`SUB_MERCHANT_NOT_FOUND` / `_NOT_CONNECTED` / `_NOT_YOURS` / `_INACTIVE`.

Webhooks (`connected_merchant.created`, `.kyc.submitted`, `.kyc.approved`,
`.kyc.rejected`, `.payout.paid`, `.payout.failed`, `.deactivated`,
`.reactivated`) are delivered to the **platform's** webhook URL, never the
sub-merchant's.

> **Two current limitations.** There's no hosted KYC link yet, so a
> sub-merchant can't verify itself through the API — approval happens
> operator-side. And `capabilities` is advisory: a merchant reporting
> `charges_enabled: false` can still take payments, because the charge path
> doesn't check KYC status. Don't treat it as a block.

### Check Payment Status

```php
try {
    $status = $client->getPaymentStatus('txn_abc123');
    
    echo "Status: " . $status['status'];
    echo "Amount: " . $status['amount'];
} catch (\ElgioPay\SDK\ElgioPayException $e) {
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
} catch (\ElgioPay\SDK\ElgioPayException $e) {
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
} catch (\ElgioPay\SDK\ElgioPayException $e) {
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
use ElgioPay\SDK\ElgioPayClient;

$client = new ElgioPayClient('sandbox', 'pk_test_your_api_key');

// Simple MTN payment in XAF
$result = $client->initiatePayment([
    'amount'         => 5000.00, // 5000 XAF
    'currency'       => 'XAF',
    'payment_method' => 'mtn_mobile_money',
    'customer_phone' => $client->normalizeCameroonPhone('677123456'),
    'customer_name'  => 'Jean Dupont',
    'reference'      => 'FACTURE-001',
]);

// Simple Orange payment in XAF
$result = $client->initiatePayment([
    'amount'         => 2500.00, // 2500 XAF
    'currency'       => 'XAF',
    'payment_method' => 'orange_money',
    'customer_phone' => '+237677123456',
    'reference'      => 'CMD-002',
]);
```

## Configuration

### API Keys

You only need two things to get started:

1. **API Key**: Get your API key from the ElgioPay dashboard
2. **Environment**: Choose between sandbox (testing) or live (production)

```php
// Sandbox environment (for testing)
$client = new ElgioPayClient('sandbox', 'pk_test_your_sandbox_api_key');

// Live environment (for production) 
$client = new ElgioPayClient('prod', 'pk_live_your_live_api_key');
```

### Environment Variables (Recommended)

```env
# For testing
ELGIOPAY_API_KEY=pk_test_your_sandbox_api_key
ELGIOPAY_ENV=sandbox

# For production
ELGIOPAY_API_KEY=pk_live_your_live_api_key
ELGIOPAY_ENV=prod
```

Then in your code — both are picked up automatically:

```php
$client = new ElgioPayClient();
```

## Error Handling

The SDK throws `ElgioPayException` for all API-related errors:

```php
try {
    $result = $client->initiatePayment($paymentData);
} catch (\ElgioPay\SDK\ElgioPayException $e) {
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
$client = new ElgioPayClient('sandbox', 'pk_test_your_sandbox_key');

// Test payment
$result = $client->initiatePayment([
    'amount'         => 1000.00,
    'currency'       => 'XAF',
    'payment_method' => 'mtn_mobile_money',
    'customer_phone' => '+237677123456',
]);
```

### Getting API Keys

1. Sign up at [sandbox.elgiopay.com](https://sandbox.elgiopay.com)
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

- 📖 **Documentation**: [https://docs.elgiopay.com](https://docs.elgiopay.com)
- 🐛 **Issues**: [GitHub Issues](https://github.com/elgiosoft/elgiopay-php-sdk/issues)
- 💬 **Support**: [developers@elgiosoft.com](mailto:developers@elgiosoft.com)
- 🌐 **Website**: [https://elgiopay.com](https://elgiopay.com)

## Contributing

We welcome contributions! Please feel free to submit a Pull Request.

## License

MIT License - see the [LICENSE](LICENSE) file for details.

## About Elgiosoft

ElgioPay PHP SDK is developed by [Elgiosoft Ltd](https://elgiosoft.com), a leading fintech company specializing in mobile money solutions for Africa.