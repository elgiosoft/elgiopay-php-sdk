<?php

namespace ElgioPay\SDK;

use ElgioPay\SDK\Resources\Card\CardClient;
use ElgioPay\SDK\Resources\Issuing\IssuingClient;
use ElgioPay\SDK\BaseClient;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Main entry point to the ElgioPay API. Handles mobile-money payments,
 * payouts, balance queries, bill payments, SMS, plus sub-clients for
 * card payments and card issuing.
 *
 * @see cards()    — Card payment processing
 * @see issuing()  — Virtual card issuance
 */
class ElgioPayClient extends BaseClient
{
    protected ?CardClient $cardClient = null;
    protected ?IssuingClient $issuingClient = null;

    private string $apiKey;
    private string $baseUrl;
    
    private string $environment;
    public Client $client;

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
     * Initiate a mobile money payment.
     *
     * The customer receives an on-phone prompt (USSD or app notification)
     * to authorize the charge. The returned transaction sits in `pending`
     * until confirmed — poll {@see getPaymentStatus()} or listen on the
     * `payment.completed` / `payment.failed` webhooks.
     *
     * When `payment_method` is omitted, it's derived from `customer_phone`
     * via {@see detectPaymentMethod()}.
     *
     * @param array{
     *   amount: int|float,
     *   currency?: string,
     *   payment_method?: string,
     *   customer_phone: string,
     *   customer_name?: string,
     *   customer_email?: string,
     *   reference?: string,
     *   metadata?: array<string,mixed>,
     *   surcharge?: int|float
     * } $paymentData
     *
     * @return array API response — `{success, transaction_id, status, payment_url?, message}`.
     */
    public function initiatePayment(array $paymentData): array
    {
        try {
            // Validate required fields
            if(empty($paymentData['payment_method'])){
                $paymentData['payment_method'] = $this->detectPaymentMethod($paymentData['customer_phone']);
            }
            $this->validatePaymentData($paymentData);

            $response = $this->client->post('/api/v1/payments', ['json' => $paymentData]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            $this->catchException($e); 
        }
    }

    /**
     * Client-side sanity check on the payment payload before the request
     * leaves the SDK. Server-side validation is still authoritative — this
     * just fails fast so obviously-broken calls don't hit the network.
     *
     * @throws ElgioPayException
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

        if($paymentData['amount'] > 1000000){
            throw new ElgioPayException("Amount cannot be greater than 1,000,000");
        }

        if (!in_array($paymentData['payment_method'], [PaymentMethod::MTN_MOBILE_MONEY->value, PaymentMethod::ORANGE_MONEY->value])) {
            throw new ElgioPayException('Invalid payment method');
        }
    }

    /**
     * Fetch the current status of a transaction. Safe to poll — this is a
     * read-only call and does not trigger provider re-verification. Use
     * {@see verifyPayment()} when you want the SDK to actively re-check
     * with the payment provider.
     */
    public function getPaymentStatus(string $transactionId): array
    {
        try {
            $response = $this->client->get("/api/v1/payments/{$transactionId}");

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            $this->catchException($e);
        }
    }

    /**
     * Force a fresh status check against the payment provider. Use this
     * when {@see getPaymentStatus()} looks stuck or you're wrapping up an
     * order and want to confirm before committing to fulfilment.
     */
    public function verifyPayment(string $transactionId): array
    {
        try {
            $response = $this->client->post("/api/v1/payments/{$transactionId}/verify");

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            $this->catchException($e); 
        }
    }

    /**
     * Return the phone in canonical `+237XXXXXXXX` form. Accepts input
     * with spaces, dashes, `237` prefix, `+237` prefix, or none — anything
     * unrecognised is returned untouched (server-side validation catches it).
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
     * Best-effort carrier detection from a Cameroon phone number.
     *
     * - MTN prefixes: 67, 650–654, 680–684
     * - Orange prefixes: 69, 64, 655–659, 685–689
     *
     * Falls back to MTN when the prefix can't be classified — this is
     * intentional so integrations that always pass a phone still get *a*
     * payment method rather than a validation error.
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
            if (preg_match('/^(67|650|651|652|653|654|680|681|682|683|684|684)/', $number)) {
                return PaymentMethod::MTN_MOBILE_MONEY->value;
            }

            // Orange patterns: 69x, 655, 656, 657, 658, 659
            if (preg_match('/^(69|64|655|656|657|658|659|685|686|687|688|689)/', $number)) {
                return PaymentMethod::ORANGE_MONEY->value;
            }
        }

        // Default to MTN if unable to detect
        return PaymentMethod::MTN_MOBILE_MONEY->value;
    }

    /**
     * Wrap {@see initiatePayment()} with exponential backoff. Useful for
     * transient network failures; the delay doubles between attempts
     * (2s, 4s, 8s…). Rethrows the last exception if every attempt fails.
     *
     * Note: this also retries on 4xx client errors — if you're seeing a
     * validation error, retrying won't help. Consider a validate-first
     * pattern instead.
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
     * List every biller service this app can pay against — electricity,
     * water, TV subscriptions, etc. The response includes the service_code
     * values you'll pass to {@see checkBilling()} and {@see payBilling()}.
     */
    public function getBilling(): array
    {
        try {
            $response = $this->client->get("/api/v1/bills");

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            $this->catchException($e);
        }
    }

    /**
     * Fetch the amount due on a customer's bill without paying it. Use
     * this to display the amount + biller-side account details for
     * confirmation before calling {@see payBilling()}.
     *
     * @param array{
     *   service_code: string,
     *   account_number: string
     * } $billData
     */
    public function checkBilling(array $billData): array
    {
        try {
            $response = $this->client->post("/api/v1/bills/get-bill", [
                'json' => $billData
            ]);
            return json_decode($response->getBody()->getContents(), true);
        }
        catch (RequestException $e) {
            $this->catchException($e);
        }

    }

    /**
     * Pay a customer bill. `item_id` is only required for services that
     * expose multiple line items (e.g. subscription tiers) — omit it
     * otherwise.
     *
     * @param array{
     *   service_code: string,
     *   account_number: string,
     *   amount: int|float,
     *   customer_phone: string,
     *   item_id?: string
     * } $billData
     */
    public function payBilling(array $billData): array
    {
         try {
            $response = $this->client->post('/api/v1/bills/pay', [
                'json' => $billData
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } 
        catch (RequestException $e) {
            $this->catchException($e);
        }
    }


    /**
     * Fetch the app's current balance(s). Returns entries for every
     * currency + wallet type the app holds — DEFAULT (payouts) and
     * SURCHARGE (fee collections) if either is non-zero.
     */
    public function getBalance(): array
    {
        try {
            $response = $this->client->get('/api/v1/balance');

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            $this->catchException($e);
        }
    }

    /**
     * List payouts for the app, newest first. Server pages the response
     * — check the pagination envelope for `next_page_url` if you need
     * more than the first page.
     */
    public function getPayouts(): array
    {
        try {
            $response = $this->client->get('/api/v1/payouts');

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            $this->catchException($e);
        }
    }

    /**
     * Send funds to a recipient. The app is debited `amount + fees` —
     * `amount` reaches the recipient in full, `fees` is the platform cut
     * (see the billing profile associated with your app).
     *
     * `source` picks which wallet to draw from:
     *   - `DEFAULT` (default) — your primary balance
     *   - `SURCHARGE` — the fee-collection wallet
     *
     * `payout_method` is one of `mtn_mobile_money`, `orange_money`, or
     * `bank_transfer`. The bank_* fields are only required (and only
     * consumed) when payout_method is `bank_transfer`.
     *
     * @param array{
     *   amount: int|float,
     *   currency?: string,
     *   source?: string,
     *   payout_method: string,
     *   recipient_phone?: string,
     *   recipient_name: string,
     *   recipient_email?: string,
     *   bank_account_number?: string,
     *   bank_name?: string,
     *   bank_code?: string,
     *   reference?: string,
     *   description?: string,
     *   metadata?: array<string,mixed>
     * } $payoutData
     */
    public function createPayout(array $payoutData): array
    {
        try {
            $response = $this->client->post('/api/v1/payouts', ['json' => $payoutData]);

            return json_decode($response->getBody()->getContents(), true);
        } 
        catch (RequestException $e) {
            $this->catchException($e);
        }
    }

    /**
     * Fetch the current status of a payout. Same lifecycle as payments —
     * `pending` → `processing` → `completed` / `failed` / `cancelled`.
     */
    public function getPayoutStatus(string $payoutId): array
    {
        try {
            $response = $this->client->get("/api/v1/payouts/{$payoutId}");

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            $this->catchException($e);
        }
    }


    /**
     * Look up recipient details before initiating a payout — confirms the
     * number is reachable on a supported network and returns the account
     * holder name so you can double-check the destination.
     *
     * @param string $phoneNumber Number in `+237XXXXXXXX` form (or any
     *                            format {@see normalizeCameroonPhone()} accepts).
     */
    public function validateRecipient(string $phoneNumber): array
    {
        try{
            $response = $this->client->post("/api/v1/validate-recipient", ['json' => [
                'recipient' => $phoneNumber
            ]]);

            return json_decode($response->getBody()->getContents(), true);
        } catch(RequestException $e){
            $this->catchException($e);
        }
    }


    // ─── Services ───────────────────────────────────────────────────

    /**
     * Send a transactional SMS. Requires the SMS service to be enabled on
     * your app — see the Services section of the dashboard.
     *
     * @param string        $phoneNumber Destination in international format.
     * @param string        $message     Body of the SMS.
     * @param array<string> $channels    Delivery channels to try, in order.
     *                                   Defaults to SMS only.
     */
    public function sendSMS(string $phoneNumber, string $message, array $channels = ['sms']): array
    {
        try{
            $response = $this->client->post('/api/v1/services/sms/send', ['json' => ['to' => $phoneNumber, 'message' => $message, 'channels' => $channels]]);
            return json_decode($response->getBody()->getContents(), true);
        } catch(RequestException $e){
            $this->catchException($e);
        }

    }

    /**
     * Card payment sub-client — card processing (init, process,
     * confirm-after-3DS). Lazily instantiated so apps that never touch
     * cards don't pay the setup cost.
     */
    public function cards(): CardClient
    {
        if ($this->cardClient === null) {
            $this->cardClient = new CardClient($this);
        }

        return $this->cardClient;
    }

    /**
     * Card issuing API (virtual card issuance, cardholder management,
     * spending controls, balance, history).
     */
    public function issuing(): IssuingClient
    {
        if ($this->issuingClient === null) {
            $this->issuingClient = new IssuingClient($this);
        }

        return $this->issuingClient;
    }

    /**
     * Replace the Guzzle client with one that has a different timeout
     * (seconds). The auth headers and base URL are preserved. Chainable.
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
}