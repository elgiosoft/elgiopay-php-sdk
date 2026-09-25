<?php

namespace ElgioPay\SDK\Resources\ConnectedMerchants;

use ElgioPay\SDK\Resources\BaseResourceClient;
use GuzzleHttp\Exception\RequestException;

/**
 * Client for the Connected Merchants API (/api/v1/connected-merchants/*).
 *
 * For **platform** apps: onboard sub-merchants, charge on their behalf, read
 * their balances and pay them out. Every method here needs both the merchant
 * to be a platform AND the calling app to be flagged as a platform app —
 * otherwise the API answers 403 `NOT_A_PLATFORM`.
 *
 * A connected merchant lives inside exactly one app — the app whose key
 * created it — so none of these calls take an app parameter, and a merchant
 * created with app A's key is invisible to app B's key
 * (`NOT_YOUR_MERCHANT` / `SUB_MERCHANT_NOT_YOURS`).
 *
 * Usage:
 *
 *   $client = new ElgioPayClient(environment: 'sandbox');
 *   $connected = $client->connectedMerchants();
 *
 *   $merchant = $connected->create([
 *       'email' => 'shop@example.cm',
 *       'company_name' => 'Boutique Mballa',
 *   ]);
 *
 *   // Charge on its behalf — the net amount credits ITS balance.
 *   $client->initiatePayment([
 *       'amount' => 5000,
 *       'customer_phone' => '677389120',
 *       'sub_merchant_id' => $merchant['merchant_id'],
 *   ]);
 *
 *   // Settle.
 *   $connected->getBalance($merchant['merchant_id']);
 *   $connected->createPayout($merchant['merchant_id'], [
 *       'amount' => 4000,
 *       'payout_method' => 'mtn_mobile_money',
 *       'recipient_name' => 'Jean Mballa',
 *       'recipient_phone' => '677389120',
 *   ]);
 *
 * Handle `connected_merchant.*` webhooks to track onboarding — they are
 * delivered to the *platform's* webhook URL, not the sub-merchant's.
 *
 * Note: there is no hosted KYC link yet, so a sub-merchant cannot verify
 * itself through the API — approval happens operator-side today.
 *
 * @phpstan-import-type ConnectedMerchantInput from ConnectedMerchantShapes
 * @phpstan-import-type ConnectedMerchantPayoutInput from ConnectedMerchantShapes
 * @phpstan-import-type ConnectedMerchant from ConnectedMerchantShapes
 * @phpstan-import-type CreateResponse from ConnectedMerchantShapes
 * @phpstan-import-type ListMeta from ConnectedMerchantShapes
 * @phpstan-import-type BalanceResponse from ConnectedMerchantShapes
 * @phpstan-import-type PayoutResponse from ConnectedMerchantShapes
 * @phpstan-import-type StatusResponse from ConnectedMerchantShapes
 */
class ConnectedMerchantsClient extends BaseResourceClient
{
    private const PREFIX = '/api/v1/connected-merchants';

    /**
     * Onboard a sub-merchant inside the calling app.
     *
     * Throttled to 60 requests/minute. The merchant starts at
     * `kyc_status: 'pending'` with both capabilities false.
     *
     * @param ConnectedMerchantInput $data
     * @return CreateResponse
     *
     * @throws \ElgioPay\SDK\ElgioPayException NOT_A_PLATFORM (403) when the
     *         key's merchant isn't a platform; VALIDATION_ERROR (422).
     */
    public function create(array $data): array
    {
        return $this->post(self::PREFIX, $data);
    }

    /**
     * Page through the platform's connected merchants.
     *
     * @param array{per_page?: int, page?: int} $query
     * @return array{data: list<ConnectedMerchant>, meta: ListMeta}
     */
    public function list(array $query = []): array
    {
        return $this->getJson(self::PREFIX, $query);
    }

    /**
     * Fetch one connected merchant by its `merchant_id` (public uid).
     *
     * @return ConnectedMerchant
     *
     * @throws \ElgioPay\SDK\ElgioPayException NOT_YOUR_MERCHANT (403) when the
     *         uid doesn't belong to this platform.
     */
    public function get(string $merchantId): array
    {
        return $this->getJson(self::PREFIX . '/' . $merchantId);
    }

    /**
     * The sub-merchant's own balance — funds routed to it by charges carrying
     * its `sub_merchant_id`. Separate from your app float.
     *
     * @return BalanceResponse
     */
    public function getBalance(string $merchantId, string $currency = 'XAF'): array
    {
        return $this->getJson(self::PREFIX . '/' . $merchantId . '/balance', [
            'currency' => $currency,
        ]);
    }

    /**
     * Pay the sub-merchant out to its own destination, drawn from its own
     * balance (not your app float).
     *
     * @param ConnectedMerchantPayoutInput $data
     * @return PayoutResponse
     *
     * @throws \ElgioPay\SDK\ElgioPayException MERCHANT_INACTIVE (409) when the
     *         merchant is deactivated; PAYOUT_FAILED (422).
     */
    public function createPayout(string $merchantId, array $data): array
    {
        return $this->post(self::PREFIX . '/' . $merchantId . '/payouts', $data);
    }

    /**
     * Freeze the sub-merchant: new charges are refused with
     * `SUB_MERCHANT_INACTIVE` and payouts with `MERCHANT_INACTIVE`. Its
     * existing balance is untouched. Reversible via {@see reactivate()}.
     *
     * @return StatusResponse
     */
    public function deactivate(string $merchantId): array
    {
        return $this->post(self::PREFIX . '/' . $merchantId . '/deactivate', []);
    }

    /**
     * Unfreeze a previously deactivated sub-merchant.
     *
     * @return StatusResponse
     */
    public function reactivate(string $merchantId): array
    {
        return $this->post(self::PREFIX . '/' . $merchantId . '/reactivate', []);
    }

    // ──────────────────────────────────────────────────────────────
    // HTTP helper. Kept private for the same reason IssuingClient does it:
    // BaseResourceClient::get() returns the raw Guzzle response and takes no
    // query params, so we bypass it.
    // ──────────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     *
     * @throws \ElgioPay\SDK\ElgioPayException
     */
    private function getJson(string $url, array $query = []): array
    {
        try {
            $options = $query ? ['query' => $query] : [];
            $response = $this->baseClient->client->get($url, $options);
            return json_decode($response->getBody()->getContents(), true) ?? [];
        } catch (RequestException $e) {
            $this->catchException($e);
            throw $e; // unreachable; catchException always throws
        }
    }
}
