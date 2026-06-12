<?php

namespace ElgioPay\SDK\Resources\Issuing;

use ElgioPay\SDK\Resources\BaseResourceClient;
use GuzzleHttp\Exception\RequestException;

/**
 * Client for the Card Issuing API (/api/v1/issuing/*).
 *
 * This covers virtual-card *issuance* — cardholder creation, card
 * lifecycle, spending controls, balance, and history. It is distinct
 * from the existing CardClient (Resources/Card), which is for card
 * *acceptance* (Stripe payment widget).
 *
 * Routing to the upstream provider (Stripe Issuing or SwyChr) is
 * decided server-side per app via App::card_issuing_processor; clients
 * always talk to Elgiopay's normalised surface.
 *
 * Usage:
 *
 *   $client = new ElgioPayClient(environment: 'sandbox');
 *   $issuing = $client->issuing();
 *
 *   $cardholder = $issuing->createCardholder([
 *       'first_name' => 'Jane', 'last_name' => 'Doe',
 *       'email' => 'jane@example.com',
 *       'billing_address_line1' => '12 Lagos Rd',
 *       'billing_address_city' => 'Lagos',
 *       'billing_address_postal_code' => '100001',
 *       'billing_address_country' => 'NG',
 *   ]);
 *
 *   $card = $issuing->createCard([
 *       'cardholder_id' => $cardholder['data']['id'],
 *       'product' => 'prepaid_debit',   // SwyChr-specific
 *       'amount' => 50,
 *   ]);
 *
 *   $issuing->freezeCard($card['data']['id']);
 *
 * Type contracts
 * --------------
 * Inputs are declared via {@see CardholderInput}, {@see CardholderUpdateInput},
 * {@see CardInput}, {@see SpendingControlsInput} array shapes — IDEs
 * (PHPStorm, vscode + Intelephense) read these and provide field-level
 * autocomplete. Static analysers (PHPStan / Psalm) verify call sites.
 *
 * Responses are typed against {@see Cardholder}, {@see Card},
 * {@see Authorization}, {@see Transaction}, {@see CardBalance},
 * {@see CardStatistics}, {@see CardSensitiveDetails} shapes, all wrapped
 * in a {@see SingleResponse} or {@see ListResponse} envelope.
 *
 * @phpstan-import-type CardholderInput from IssuingShapes
 * @phpstan-import-type CardholderUpdateInput from IssuingShapes
 * @phpstan-import-type CardInput from IssuingShapes
 * @phpstan-import-type SpendingControlsInput from IssuingShapes
 * @phpstan-import-type Cardholder from IssuingShapes
 * @phpstan-import-type Card from IssuingShapes
 * @phpstan-import-type CardBalance from IssuingShapes
 * @phpstan-import-type CardStatistics from IssuingShapes
 * @phpstan-import-type CardSensitiveDetails from IssuingShapes
 * @phpstan-import-type Authorization from IssuingShapes
 * @phpstan-import-type Transaction from IssuingShapes
 * @phpstan-import-type EphemeralKey from IssuingShapes
 * @phpstan-import-type ListMeta from IssuingShapes
 */
class IssuingClient extends BaseResourceClient
{
    private const PREFIX = '/api/v1/issuing';

    // ────────────────────────────────────────────────────────────
    // Cardholders
    // ────────────────────────────────────────────────────────────

    /**
     * Create a cardholder.
     *
     * The caller's app must have card issuing enabled
     * (admin toggle: card_issuing_enabled + card_issuing_processor),
     * otherwise the API returns HTTP 403.
     *
     * @param CardholderInput $data
     * @return array{success: bool, message: string, data: Cardholder}
     *
     * @throws \ElgioPay\SDK\ElgioPayException on validation (422),
     *         disabled (403), or provider errors (500).
     */
    public function createCardholder(array $data): array
    {
        return $this->post(self::PREFIX . '/cardholders', $data);
    }

    /**
     * List cardholders for the calling app.
     *
     * @param array{
     *     status?: 'active'|'inactive'|'blocked',
     *     type?: 'individual'|'company',
     * } $query
     * @return array{data: list<Cardholder>, meta: ListMeta}
     */
    public function listCardholders(array $query = []): array
    {
        return $this->getJson(self::PREFIX . '/cardholders', $query);
    }

    /**
     * Get a single cardholder by its Elgiopay numeric ID.
     *
     * @param int|string $cardholderId
     * @return array{data: Cardholder}
     *
     * @throws \ElgioPay\SDK\ElgioPayException 404 when the cardholder does
     *         not belong to the calling app.
     */
    public function getCardholder(string|int $cardholderId): array
    {
        return $this->getJson(self::PREFIX . '/cardholders/' . $cardholderId);
    }

    /**
     * Update a cardholder. Send only the fields you want to change.
     *
     * @param int|string $cardholderId
     * @param CardholderUpdateInput $data
     * @return array{success: bool, message: string, data: Cardholder}
     */
    public function updateCardholder(string|int $cardholderId, array $data): array
    {
        return $this->put(self::PREFIX . '/cardholders/' . $cardholderId, $data);
    }

    // ────────────────────────────────────────────────────────────
    // Cards — lifecycle
    // ────────────────────────────────────────────────────────────

    /**
     * Issue a card.
     *
     * Body fields are processor-aware:
     *   - SwyChr requires `product` (lite|prepaid_credit|prepaid_debit|contactless),
     *     `amount`, and optional `card_type` (VISA|MASTERCARD). For `contactless`,
     *     `daily_limit` and `transaction_limit` are also accepted.
     *   - Stripe Issuing uses `currency`, optional `spending_controls`,
     *     and `type` (defaults to 'virtual').
     *
     * @param CardInput $data
     * @return array{success: bool, message: string, data: Card}
     */
    public function createCard(array $data): array
    {
        return $this->post(self::PREFIX . '/cards', $data);
    }

    /**
     * List cards for the calling app.
     *
     * @param array{
     *     status?: 'active'|'inactive'|'canceled',
     *     type?: 'virtual'|'physical',
     * } $query
     * @return array{data: list<Card>, meta: ListMeta}
     */
    public function listCards(array $query = []): array
    {
        return $this->getJson(self::PREFIX . '/cards', $query);
    }

    /**
     * Get a single card by its Elgiopay numeric ID.
     *
     * @param int|string $cardId
     * @return array{data: Card}
     */
    public function getCard(string|int $cardId): array
    {
        return $this->getJson(self::PREFIX . '/cards/' . $cardId);
    }

    /**
     * Freeze (temporarily disable) a card. Status becomes `inactive`.
     *
     * @param int|string $cardId
     * @return array{success: bool, message: string, data: Card}
     */
    public function freezeCard(string|int $cardId): array
    {
        return $this->post(self::PREFIX . '/cards/' . $cardId . '/freeze', []);
    }

    /**
     * Re-enable a previously frozen card. Status becomes `active`.
     *
     * @param int|string $cardId
     * @return array{success: bool, message: string, data: Card}
     */
    public function unfreezeCard(string|int $cardId): array
    {
        return $this->post(self::PREFIX . '/cards/' . $cardId . '/unfreeze', []);
    }

    /**
     * Cancel a card permanently. Any remaining local card balance is
     * automatically refunded to the calling app's `AppBalance`.
     *
     * @param int|string $cardId
     * @param 'lost'|'stolen'|'design_rejected'|'other'|null $reason
     * @return array{
     *     success: bool,
     *     message: string,
     *     data: Card,
     *     refund: array{amount: int, currency: string, credited_to: string}|null,
     * }
     */
    public function cancelCard(string|int $cardId, ?string $reason = null): array
    {
        $body = $reason !== null ? ['reason' => $reason] : [];
        return $this->post(self::PREFIX . '/cards/' . $cardId . '/cancel', $body);
    }

    /**
     * Update card spending controls.
     *
     *   - Stripe Issuing accepts `allowed_categories`, `blocked_categories`,
     *     `spending_limits` (Stripe MCC controls).
     *   - SwyChr-backed cards only honour daily / per-transaction limits;
     *     pass `daily_limit` + `transaction_limit` keys for that path.
     *
     * @param int|string $cardId
     * @param SpendingControlsInput $spendingControls
     * @return array{success: bool, message: string, data: Card}
     */
    public function updateCardSpendingControls(string|int $cardId, array $spendingControls): array
    {
        return $this->put(self::PREFIX . '/cards/' . $cardId . '/spending-controls', [
            'spending_controls' => $spendingControls,
        ]);
    }

    /**
     * Retrieve sensitive card data (PAN + CVV). The server returns plaintext;
     * handle with care. The card must be `active`.
     *
     * @param int|string $cardId
     * @return array{success: bool, data: CardSensitiveDetails}
     */
    public function getCardDetails(string|int $cardId): array
    {
        return $this->getJson(self::PREFIX . '/cards/' . $cardId . '/details');
    }

    /**
     * Create a Stripe ephemeral key for client-side PAN reveal.
     * Stripe-only — returns HTTP 500 (with a NOT_SUPPORTED error) for
     * SwyChr-backed cards.
     *
     * @param int|string $cardId
     * @param string|null $stripeVersion Forwarded as the Stripe-Version header
     * @return array{success: bool, data: array{ephemeral_key: EphemeralKey}}
     */
    public function createEphemeralKey(string|int $cardId, ?string $stripeVersion = null): array
    {
        $extraHeaders = $stripeVersion !== null ? ['Stripe-Version' => $stripeVersion] : [];
        return $this->postWithHeaders(self::PREFIX . '/cards/' . $cardId . '/ephemeral-key', [], $extraHeaders);
    }

    // ────────────────────────────────────────────────────────────
    // Cards — balance & top-up
    // ────────────────────────────────────────────────────────────

    /**
     * Get the local card balance (Elgiopay-managed ledger, denominated in
     * minor units of `balance_currency`).
     *
     * @param int|string $cardId
     * @return array{data: CardBalance}
     */
    public function getCardBalance(string|int $cardId): array
    {
        return $this->getJson(self::PREFIX . '/cards/' . $cardId . '/balance');
    }

    /**
     * Top up a card from the calling app's `AppBalance` of the matching
     * currency. The app balance is debited synchronously; SwyChr-backed
     * cards also have their provider balance funded.
     *
     * @param int|string $cardId
     * @param int $amount Amount in minor units (e.g. cents).
     * @param string|null $description Optional human-readable note.
     * @return array{
     *     success: bool,
     *     message: string,
     *     data: array{
     *         card_id: int,
     *         balance: int,
     *         balance_currency: string,
     *         formatted_balance: string,
     *         app_balance: array{available_balance: int, currency: string},
     *     },
     * }
     */
    public function topUpCard(string|int $cardId, int $amount, ?string $description = null): array
    {
        $body = ['amount' => $amount];
        if ($description !== null) {
            $body['description'] = $description;
        }
        return $this->post(self::PREFIX . '/cards/' . $cardId . '/topup', $body);
    }

    // ────────────────────────────────────────────────────────────
    // Cards — history
    // ────────────────────────────────────────────────────────────

    /**
     * List transactions (captures, refunds, cash withdrawals, disputes) for
     * a card.
     *
     * @param int|string $cardId
     * @param array{type?: 'capture'|'refund'|'cash_withdrawal'|'dispute'|'dispute_loss'} $query
     * @return array{data: list<Transaction>, meta: ListMeta}
     */
    public function listCardTransactions(string|int $cardId, array $query = []): array
    {
        return $this->getJson(self::PREFIX . '/cards/' . $cardId . '/transactions', $query);
    }

    /**
     * List authorization events (real-time approve/decline attempts) for a card.
     *
     * @param int|string $cardId
     * @param array{
     *     status?: 'pending'|'closed'|'reversed',
     *     approved?: bool,
     * } $query
     * @return array{data: list<Authorization>, meta: ListMeta}
     */
    public function listCardAuthorizations(string|int $cardId, array $query = []): array
    {
        return $this->getJson(self::PREFIX . '/cards/' . $cardId . '/authorizations', $query);
    }

    /**
     * Aggregate statistics for a card (current balance, transaction +
     * authorization counts, current-month spend, declines).
     *
     * @param int|string $cardId
     * @return array{data: array{card_id: int, statistics: CardStatistics}}
     */
    public function getCardStatistics(string|int $cardId): array
    {
        return $this->getJson(self::PREFIX . '/cards/' . $cardId . '/statistics');
    }

    // ────────────────────────────────────────────────────────────
    // HTTP helpers (kept private to avoid leaking onto the BaseResourceClient,
    // which exposes a buggy get() that we deliberately bypass).
    // ────────────────────────────────────────────────────────────

    /**
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

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     *
     * @throws \ElgioPay\SDK\ElgioPayException
     */
    private function put(string $url, array $params): array
    {
        try {
            $response = $this->baseClient->client->put($url, ['json' => $params]);
            return json_decode($response->getBody()->getContents(), true) ?? [];
        } catch (RequestException $e) {
            $this->catchException($e);
            throw $e; // unreachable; catchException always throws
        }
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, string> $headers
     * @return array<string, mixed>
     *
     * @throws \ElgioPay\SDK\ElgioPayException
     */
    private function postWithHeaders(string $url, array $params, array $headers): array
    {
        try {
            $response = $this->baseClient->client->post($url, [
                'json' => $params,
                'headers' => $headers,
            ]);
            return json_decode($response->getBody()->getContents(), true) ?? [];
        } catch (RequestException $e) {
            $this->catchException($e);
            throw $e; // unreachable; catchException always throws
        }
    }
}
