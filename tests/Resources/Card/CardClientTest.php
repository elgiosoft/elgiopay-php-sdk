<?php

namespace ElgioPay\SDK\Tests\Resources\Card;

use ElgioPay\SDK\ElgioPayException;
use ElgioPay\SDK\Tests\MockClientTestCase;

/**
 * Card-payment sub-client, including `sub_merchant_id` — card charges route to
 * a connected merchant the same way mobile money does.
 */
class CardClientTest extends MockClientTestCase
{
    public function test_initialize_hits_the_init_endpoint(): void
    {
        $this->queue(200, ['app_id' => 'pk_live_abc']);

        $init = $this->client->cards()->initialize();

        $this->assertLastRequest('POST', '/api/v1/card-payment/init');
        $this->assertSame('pk_live_abc', $init['app_id']);
    }

    public function test_process_payment_posts_the_charge_payload(): void
    {
        $this->queue(200, ['success' => true, 'message' => 'Payment successful']);

        $this->client->cards()->processPayment([
            'amount' => 100,
            'currency' => 'USD',
            'customer_name' => 'Marie Tchana',
            'payment_method_id' => 'pm_123',
        ]);

        $this->assertLastRequest('POST', '/api/v1/card-payment/process');
        $body = $this->lastRequestBody();
        $this->assertSame(100, $body['amount']);
        $this->assertSame('pm_123', $body['payment_method_id']);
    }

    public function test_card_charge_can_be_attributed_to_a_sub_merchant(): void
    {
        $this->queue(200, ['success' => true, 'message' => 'Payment successful']);

        $this->client->cards()->processPayment([
            'amount' => 100,
            'currency' => 'USD',
            'customer_name' => 'Marie Tchana',
            'payment_method_id' => 'pm_123',
            'sub_merchant_id' => 'mch_abc',
        ]);

        $this->assertSame('mch_abc', $this->lastRequestBody()['sub_merchant_id']);
    }

    public function test_card_charge_without_sub_merchant_stays_unattributed(): void
    {
        $this->queue(200, ['success' => true, 'message' => 'Payment successful']);

        $this->client->cards()->processPayment([
            'amount' => 100,
            'currency' => 'USD',
            'customer_name' => 'Marie Tchana',
        ]);

        $this->assertArrayNotHasKey('sub_merchant_id', $this->lastRequestBody());
    }

    public function test_a_bad_sub_merchant_surfaces_the_routing_error_code(): void
    {
        // The server resolves sub_merchant_id BEFORE creating the transaction,
        // so this is a clean 422 with no charge attempted.
        $this->queue(422, [
            'success' => false,
            'error' => 'SUB_MERCHANT_NOT_YOURS',
            'message' => 'The connected merchant does not belong to this application.',
        ]);

        try {
            $this->client->cards()->processPayment([
                'amount' => 100,
                'currency' => 'USD',
                'customer_name' => 'Marie Tchana',
                'sub_merchant_id' => 'mch_someone_else',
            ]);
            $this->fail('Expected ElgioPayException');
        } catch (ElgioPayException $e) {
            $this->assertSame('SUB_MERCHANT_NOT_YOURS', $e->getResponse()['error']);
        }
    }

    public function test_confirm_payment_reconciles_after_3ds(): void
    {
        $this->queue(200, ['success' => true, 'message' => 'Payment successful']);

        $this->client->cards()->confirmPayment([
            'transaction_id' => 'CARD_ABC',
            'payment_intent_id' => 'pi_123',
        ]);

        $this->assertLastRequest('POST', '/api/v1/card-payment/confirm');
        $this->assertSame('CARD_ABC', $this->lastRequestBody()['transaction_id']);
    }

    public function test_sub_client_is_memoized(): void
    {
        $this->assertSame($this->client->cards(), $this->client->cards());
    }
}
