<?php

namespace ElgioPay\SDK\Tests\Resources\ConnectedMerchants;

use ElgioPay\SDK\ElgioPayException;
use ElgioPay\SDK\Tests\MockClientTestCase;

/**
 * Connected-merchant (platform) surface: onboarding, lifecycle, balance,
 * payouts, plus `sub_merchant_id` routing on a charge.
 */
class ConnectedMerchantsClientTest extends MockClientTestCase
{
    public function test_create_posts_onboarding_payload(): void
    {
        $this->queue(201, [
            'merchant_id' => 'mch_abc',
            'type' => 'connected',
            'kyc_status' => 'pending',
            'capabilities' => ['charges_enabled' => false, 'payouts_enabled' => false],
            'status' => 'created',
        ]);

        $created = $this->client->connectedMerchants()->create([
            'email' => 'shop@example.cm',
            'company_name' => 'Boutique Mballa',
            'country' => 'CM',
        ]);

        $this->assertLastRequest('POST', '/api/v1/connected-merchants');
        $body = $this->lastRequestBody();
        $this->assertSame('shop@example.cm', $body['email']);
        $this->assertSame('Boutique Mballa', $body['company_name']);

        $this->assertSame('mch_abc', $created['merchant_id']);
        $this->assertSame('pending', $created['kyc_status']);
        $this->assertFalse($created['capabilities']['charges_enabled']);
    }

    public function test_list_sends_pagination_query(): void
    {
        $this->queue(200, [
            'data' => [['merchant_id' => 'mch_abc', 'type' => 'connected']],
            'meta' => ['current_page' => 1, 'last_page' => 3, 'total' => 55],
        ]);

        $page = $this->client->connectedMerchants()->list(['per_page' => 20]);

        $req = $this->lastRequest();
        $this->assertSame('GET', $req->getMethod());
        $this->assertSame('/api/v1/connected-merchants', $req->getUri()->getPath());
        parse_str($req->getUri()->getQuery(), $query);
        $this->assertSame('20', $query['per_page']);

        $this->assertSame('mch_abc', $page['data'][0]['merchant_id']);
        $this->assertSame(55, $page['meta']['total']);
    }

    public function test_get_fetches_one_merchant(): void
    {
        $this->queue(200, ['merchant_id' => 'mch_abc', 'kyc_status' => 'approved']);

        $merchant = $this->client->connectedMerchants()->get('mch_abc');

        $this->assertLastRequest('GET', '/api/v1/connected-merchants/mch_abc');
        $this->assertSame('approved', $merchant['kyc_status']);
    }

    public function test_get_balance_defaults_to_xaf(): void
    {
        $this->queue(200, [
            'merchant_id' => 'mch_abc',
            'currency' => 'XAF',
            'balance' => 12000.0,
            'available_balance' => 9000.0,
            'reserved_balance' => 3000.0,
        ]);

        $balance = $this->client->connectedMerchants()->getBalance('mch_abc');

        $req = $this->lastRequest();
        $this->assertSame('/api/v1/connected-merchants/mch_abc/balance', $req->getUri()->getPath());
        parse_str($req->getUri()->getQuery(), $query);
        $this->assertSame('XAF', $query['currency']);

        // Loose compare: JSON serialises 9000.0 as `9000`, which decodes to int.
        $this->assertEquals(9000, $balance['available_balance']);
    }

    public function test_get_balance_honours_explicit_currency(): void
    {
        $this->queue(200, ['merchant_id' => 'mch_abc', 'currency' => 'EUR']);

        $this->client->connectedMerchants()->getBalance('mch_abc', 'EUR');

        parse_str($this->lastRequest()->getUri()->getQuery(), $query);
        $this->assertSame('EUR', $query['currency']);
    }

    public function test_create_payout_posts_to_merchant_scoped_endpoint(): void
    {
        $this->queue(201, [
            'payout_id' => 'po_1',
            'merchant_id' => 'mch_abc',
            'amount' => 5000.0,
            'currency' => 'XAF',
            'status' => 'processing',
        ]);

        $payout = $this->client->connectedMerchants()->createPayout('mch_abc', [
            'amount' => 5000,
            'payout_method' => 'mtn_mobile_money',
            'recipient_name' => 'Jean Mballa',
            'recipient_phone' => '677389120',
        ]);

        $this->assertLastRequest('POST', '/api/v1/connected-merchants/mch_abc/payouts');
        $this->assertSame('mtn_mobile_money', $this->lastRequestBody()['payout_method']);
        $this->assertSame('po_1', $payout['payout_id']);
    }

    public function test_deactivate_and_reactivate(): void
    {
        $this->queue(200, ['merchant_id' => 'mch_abc', 'is_active' => false, 'status' => 'deactivated']);
        $off = $this->client->connectedMerchants()->deactivate('mch_abc');
        $this->assertLastRequest('POST', '/api/v1/connected-merchants/mch_abc/deactivate');
        $this->assertFalse($off['is_active']);

        $this->queue(200, ['merchant_id' => 'mch_abc', 'is_active' => true, 'status' => 'reactivated']);
        $on = $this->client->connectedMerchants()->reactivate('mch_abc');
        $this->assertLastRequest('POST', '/api/v1/connected-merchants/mch_abc/reactivate');
        $this->assertTrue($on['is_active']);
    }

    public function test_payment_accepts_sub_merchant_id(): void
    {
        $this->queue(201, ['success' => true, 'transaction_id' => 'txn_1', 'status' => 'pending']);

        $this->client->initiatePayment([
            'amount' => 5000,
            'customer_phone' => '+237677389120',
            'sub_merchant_id' => 'mch_abc',
        ]);

        $this->assertLastRequest('POST', '/api/v1/payments');
        $this->assertSame('mch_abc', $this->lastRequestBody()['sub_merchant_id']);
    }

    public function test_not_a_platform_error_surfaces_the_code(): void
    {
        $this->queue(403, [
            'error' => 'NOT_A_PLATFORM',
            'message' => 'This endpoint requires a platform merchant’s API key.',
        ]);

        try {
            $this->client->connectedMerchants()->create(['email' => 'shop@example.cm']);
            $this->fail('Expected ElgioPayException');
        } catch (ElgioPayException $e) {
            // BaseClient::catchException prefers `message`, then `error`.
            $this->assertStringContainsString('platform merchant', $e->getMessage());
            $this->assertSame('NOT_A_PLATFORM', $e->getResponse()['error']);
        }
    }

    public function test_sub_client_is_memoized(): void
    {
        $this->assertSame(
            $this->client->connectedMerchants(),
            $this->client->connectedMerchants()
        );
    }
}
