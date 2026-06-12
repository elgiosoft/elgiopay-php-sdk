<?php

namespace ElgioPay\SDK\Tests\Resources\Issuing;

use ElgioPay\SDK\ElgioPayException;
use ElgioPay\SDK\Resources\Issuing\IssuingClient;
use ElgioPay\SDK\Tests\MockClientTestCase;

class IssuingClientTest extends MockClientTestCase
{
    // ─── Accessor ──────────────────────────────────────────────────────────

    public function test_issuing_accessor_returns_issuing_client(): void
    {
        $this->assertInstanceOf(IssuingClient::class, $this->client->issuing());
    }

    public function test_issuing_accessor_is_memoized(): void
    {
        $a = $this->client->issuing();
        $b = $this->client->issuing();
        $this->assertSame($a, $b);
    }

    // ─── Cardholders ───────────────────────────────────────────────────────

    public function test_create_cardholder_sends_post_with_body(): void
    {
        $this->queue(201, ['data' => ['id' => 7, 'processor' => 'swychr', 'external_id' => 'u-1']]);

        $result = $this->client->issuing()->createCardholder([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'billing_address_line1' => '12 Lagos Rd',
            'billing_address_city' => 'Lagos',
            'billing_address_postal_code' => '100001',
            'billing_address_country' => 'NG',
        ]);

        $this->assertLastRequest('POST', '/api/v1/issuing/cardholders');
        $body = $this->lastRequestBody();
        $this->assertSame('Jane', $body['first_name']);
        $this->assertSame('jane@example.com', $body['email']);
        $this->assertSame(7, $result['data']['id']);
    }

    public function test_list_cardholders_supports_query_string(): void
    {
        $this->queue(200, ['data' => []]);

        $this->client->issuing()->listCardholders(['status' => 'active', 'type' => 'individual']);

        $req = $this->lastRequest();
        $this->assertSame('GET', $req->getMethod());
        $this->assertSame('/api/v1/issuing/cardholders', $req->getUri()->getPath());
        parse_str($req->getUri()->getQuery(), $query);
        $this->assertSame('active', $query['status']);
        $this->assertSame('individual', $query['type']);
    }

    public function test_get_cardholder_targets_id_path(): void
    {
        $this->queue(200, ['data' => ['id' => 7]]);
        $this->client->issuing()->getCardholder(7);
        $this->assertLastRequest('GET', '/api/v1/issuing/cardholders/7');
    }

    public function test_update_cardholder_uses_put(): void
    {
        $this->queue(200, ['data' => ['id' => 7]]);
        $this->client->issuing()->updateCardholder(7, ['email' => 'new@example.com']);

        $this->assertLastRequest('PUT', '/api/v1/issuing/cardholders/7');
        $this->assertSame(['email' => 'new@example.com'], $this->lastRequestBody());
    }

    // ─── Cards lifecycle ───────────────────────────────────────────────────

    public function test_create_card_sends_processor_specific_body(): void
    {
        $this->queue(201, ['data' => ['id' => 42, 'last4' => '4242']]);

        $this->client->issuing()->createCard([
            'cardholder_id' => 7,
            'product' => 'prepaid_debit',
            'amount' => 50,
            'card_type' => 'VISA',
        ]);

        $this->assertLastRequest('POST', '/api/v1/issuing/cards');
        $body = $this->lastRequestBody();
        $this->assertSame(7, $body['cardholder_id']);
        $this->assertSame('prepaid_debit', $body['product']);
        $this->assertSame(50, $body['amount']);
        $this->assertSame('VISA', $body['card_type']);
    }

    public function test_list_cards_does_a_get(): void
    {
        $this->queue(200, ['data' => []]);
        $this->client->issuing()->listCards(['status' => 'active']);
        $this->assertLastRequest('GET', '/api/v1/issuing/cards');
    }

    public function test_get_card_targets_id_path(): void
    {
        $this->queue(200, ['data' => ['id' => 42]]);
        $this->client->issuing()->getCard(42);
        $this->assertLastRequest('GET', '/api/v1/issuing/cards/42');
    }

    public function test_freeze_card_posts_empty_body(): void
    {
        $this->queue(200, ['data' => []]);
        $this->client->issuing()->freezeCard(42);
        $this->assertLastRequest('POST', '/api/v1/issuing/cards/42/freeze');
        $this->assertSame([], $this->lastRequestBody());
    }

    public function test_unfreeze_card(): void
    {
        $this->queue(200, ['data' => []]);
        $this->client->issuing()->unfreezeCard(42);
        $this->assertLastRequest('POST', '/api/v1/issuing/cards/42/unfreeze');
    }

    public function test_cancel_card_with_reason_includes_reason(): void
    {
        $this->queue(200, ['data' => []]);
        $this->client->issuing()->cancelCard(42, 'lost');
        $this->assertLastRequest('POST', '/api/v1/issuing/cards/42/cancel');
        $this->assertSame(['reason' => 'lost'], $this->lastRequestBody());
    }

    public function test_cancel_card_without_reason_sends_empty_body(): void
    {
        $this->queue(200, ['data' => []]);
        $this->client->issuing()->cancelCard(42);
        $this->assertLastRequest('POST', '/api/v1/issuing/cards/42/cancel');
        $this->assertSame([], $this->lastRequestBody());
    }

    public function test_update_card_spending_controls_wraps_payload(): void
    {
        $this->queue(200, ['data' => []]);
        $this->client->issuing()->updateCardSpendingControls(42, [
            'allowed_categories' => ['gas'],
            'spending_limits' => [['amount' => 5000, 'interval' => 'daily']],
        ]);

        $this->assertLastRequest('PUT', '/api/v1/issuing/cards/42/spending-controls');
        $body = $this->lastRequestBody();
        $this->assertSame(['gas'], $body['spending_controls']['allowed_categories']);
        $this->assertSame(5000, $body['spending_controls']['spending_limits'][0]['amount']);
    }

    public function test_get_card_details(): void
    {
        $this->queue(200, ['data' => ['number' => '4242', 'cvc' => '123']]);
        $result = $this->client->issuing()->getCardDetails(42);
        $this->assertLastRequest('GET', '/api/v1/issuing/cards/42/details');
        $this->assertSame('4242', $result['data']['number']);
    }

    public function test_create_ephemeral_key_forwards_stripe_version_header(): void
    {
        $this->queue(200, ['data' => ['ephemeral_key' => 'ek_...']]);

        $this->client->issuing()->createEphemeralKey(42, '2023-10-16');

        $this->assertLastRequest('POST', '/api/v1/issuing/cards/42/ephemeral-key');
        $this->assertTrue($this->lastRequest()->hasHeader('Stripe-Version'));
        $this->assertSame('2023-10-16', $this->lastRequest()->getHeaderLine('Stripe-Version'));
    }

    public function test_create_ephemeral_key_omits_header_when_version_null(): void
    {
        $this->queue(200, ['data' => []]);
        $this->client->issuing()->createEphemeralKey(42);
        $this->assertSame('', $this->lastRequest()->getHeaderLine('Stripe-Version'));
    }

    // ─── Balance & top-up ──────────────────────────────────────────────────

    public function test_get_card_balance(): void
    {
        $this->queue(200, ['data' => ['balance' => 1000, 'balance_currency' => 'usd']]);
        $result = $this->client->issuing()->getCardBalance(42);
        $this->assertLastRequest('GET', '/api/v1/issuing/cards/42/balance');
        $this->assertSame(1000, $result['data']['balance']);
    }

    public function test_top_up_card_with_description(): void
    {
        $this->queue(200, ['data' => []]);
        $this->client->issuing()->topUpCard(42, 5000, 'Q3 refill');

        $this->assertLastRequest('POST', '/api/v1/issuing/cards/42/topup');
        $this->assertSame(['amount' => 5000, 'description' => 'Q3 refill'], $this->lastRequestBody());
    }

    public function test_top_up_card_without_description_omits_field(): void
    {
        $this->queue(200, ['data' => []]);
        $this->client->issuing()->topUpCard(42, 100);
        $this->assertSame(['amount' => 100], $this->lastRequestBody());
    }

    // ─── History ───────────────────────────────────────────────────────────

    public function test_list_card_transactions_passes_query(): void
    {
        $this->queue(200, ['data' => []]);
        $this->client->issuing()->listCardTransactions(42, ['type' => 'capture']);
        parse_str($this->lastRequest()->getUri()->getQuery(), $q);
        $this->assertSame('capture', $q['type']);
        $this->assertLastRequest('GET', '/api/v1/issuing/cards/42/transactions');
    }

    public function test_list_card_authorizations(): void
    {
        $this->queue(200, ['data' => []]);
        $this->client->issuing()->listCardAuthorizations(42);
        $this->assertLastRequest('GET', '/api/v1/issuing/cards/42/authorizations');
    }

    public function test_get_card_statistics(): void
    {
        $this->queue(200, ['data' => ['transactions_count' => 12]]);
        $this->client->issuing()->getCardStatistics(42);
        $this->assertLastRequest('GET', '/api/v1/issuing/cards/42/statistics');
    }

    // ─── Auth header ───────────────────────────────────────────────────────

    public function test_requests_include_bearer_authorization(): void
    {
        $this->queue(200, ['data' => []]);
        $this->client->issuing()->getCard(42);
        $this->assertStringStartsWith('Bearer ', $this->lastRequest()->getHeaderLine('Authorization'));
    }

    // ─── Error propagation ────────────────────────────────────────────────

    public function test_4xx_response_throws_elgiopay_exception_with_api_message(): void
    {
        $this->queueError(422, ['message' => 'Validation failed', 'errors' => ['email' => ['bad']]]);

        try {
            $this->client->issuing()->createCardholder(['first_name' => 'x']);
            $this->fail('Expected ElgioPayException');
        } catch (ElgioPayException $e) {
            $this->assertStringContainsString('Validation failed', $e->getMessage());
        }
    }

    public function test_5xx_response_throws_elgiopay_exception(): void
    {
        $this->queueError(500, ['error' => 'Internal']);

        try {
            $this->client->issuing()->freezeCard(42);
            $this->fail('Expected ElgioPayException');
        } catch (ElgioPayException $e) {
            $this->assertStringContainsString('Internal', $e->getMessage());
        }
    }

    public function test_string_ids_are_accepted(): void
    {
        $this->queue(200, ['data' => []]);
        $this->client->issuing()->getCard('card_abc123');
        $this->assertLastRequest('GET', '/api/v1/issuing/cards/card_abc123');
    }
}
