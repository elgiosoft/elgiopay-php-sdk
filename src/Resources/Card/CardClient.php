<?php
namespace ElgioPay\SDK\Resources\Card;

use ElgioPay\SDK\ElgioPayClient;
use ElgioPay\SDK\Resources\BaseResourceClient;
use GuzzleHttp\Exception\RequestException;

/**
 * Card-payment sub-client. Accessed via {@see ElgioPayClient::cards()}.
 *
 * Typical flow:
 *   1. {@see initialize()} to fetch the client-side publishable key
 *      used to bootstrap the card-collection widget.
 *   2. Collect the card client-side and get back a `payment_method_id`.
 *   3. {@see processPayment()} with that id.
 *   4. If the response has `requires_action: true`, complete the 3D
 *      Secure challenge client-side and then call {@see confirmPayment()}
 *      to reconcile.
 */
class CardClient extends BaseResourceClient {

    /**
     * Fetch the publishable key used to initialise the client-side card
     * widget in your frontend.
     */
    public function initialize(): array
    {
        return $this->post('/api/v1/card-payment/init', []);
    }

    /**
     * Attempt a server-side charge. `payment_method_id` is the tokenised
     * card your frontend produced with the card-collection widget.
     *
     * Response scenarios:
     *   - `success: true` — charge cleared, transaction is completed.
     *   - `success: true` + `requires_action: true` — 3D Secure needed;
     *     hand `payment_intent_client_secret` back to the frontend and
     *     complete SCA there before calling {@see confirmPayment()}.
     *
     * Platform apps can set `sub_merchant_id` (a `merchant_id` from
     * {@see \ElgioPay\SDK\ElgioPayClient::connectedMerchants()}) to route the
     * charge to a connected merchant: the net amount credits that merchant's
     * own balance instead of the app float, and the card settlement hold lands
     * on their ledger. Validated before the transaction is created, so a bad id
     * is a clean 422 with no charge attempted.
     *
     * @param array{
     *   amount: int|float,
     *   currency: string,
     *   customer_name: string,
     *   payment_method_id?: string,
     *   customer_email?: string,
     *   reference?: string,
     *   description?: string,
     *   sub_merchant_id?: string
     * } $paymentData
     */
    public function processPayment(array $paymentData): array
    {
        return $this->post('/api/v1/card-payment/process', $paymentData);
    }

    /**
     * Reconcile a transaction after the client finished the 3D Secure
     * flow. Fetches the latest state from the card processor and marks
     * the transaction completed or failed accordingly.
     *
     * @param array{
     *   transaction_id: string,
     *   payment_intent_id: string
     * } $paymentData
     */
    public function confirmPayment(array $paymentData): array
    {
       return  $this->post('/api/v1/card-payment/confirm', $paymentData);

    }
}
