<?php

namespace ElgioPay\SDK\Tests;

/**
 * channel_code support on payment + payout, and the listChannels() endpoint.
 */
class ClientChannelTest extends MockClientTestCase
{
    public function test_payment_accepts_channel_code_instead_of_method(): void
    {
        $this->queue(201, ['success' => true, 'transaction_id' => 'txn_1', 'status' => 'pending']);

        $this->client->initiatePayment([
            'amount' => 1000,
            'channel_code' => 'ORANGE_CMR',
            'customer_phone' => '+237699000000',
        ]);

        $this->assertLastRequest('POST', '/api/v1/payments');
        $body = $this->lastRequestBody();
        $this->assertSame('ORANGE_CMR', $body['channel_code']);
        // Auto-detect must NOT fill a payment_method when a channel is given.
        $this->assertArrayNotHasKey('payment_method', $body);
    }

    public function test_payment_still_accepts_legacy_method(): void
    {
        $this->queue(201, ['success' => true, 'transaction_id' => 'txn_2', 'status' => 'pending']);

        $this->client->initiatePayment([
            'amount' => 1000,
            'payment_method' => 'mtn_mobile_money',
            'customer_phone' => '+237677000000',
        ]);

        $this->assertSame('mtn_mobile_money', $this->lastRequestBody()['payment_method']);
    }

    public function test_payment_without_method_or_channel_auto_detects_from_phone(): void
    {
        // With neither selector given, the SDK auto-detects a legacy method
        // from the phone (back-compat) — so the request still carries one.
        $this->queue(201, ['success' => true, 'transaction_id' => 'txn_3', 'status' => 'pending']);

        $this->client->initiatePayment([
            'amount' => 1000,
            'customer_phone' => '+237699000000', // Orange range
        ]);

        $this->assertSame('orange_money', $this->lastRequestBody()['payment_method']);
    }

    public function test_payout_accepts_channel_code(): void
    {
        $this->queue(200, ['success' => true, 'data' => ['payout_id' => 'po_1', 'status' => 'processing']]);

        $this->client->createPayout([
            'amount' => 5000,
            'channel_code' => 'AIRTEL_GAB',
            'recipient_name' => 'Jean',
            'recipient_phone' => '074000000',
        ]);

        $this->assertLastRequest('POST', '/api/v1/payouts');
        $this->assertSame('AIRTEL_GAB', $this->lastRequestBody()['channel_code']);
    }

    public function test_list_channels_hits_endpoint_with_filters(): void
    {
        $this->queue(200, ['success' => true, 'data' => [
            ['code' => 'ORANGE_CMR', 'name' => 'Orange Money (Cameroon)', 'type' => 'mobile_money'],
        ]]);

        $result = $this->client->listChannels(['type' => 'mobile_money', 'country' => 'CMR']);

        $req = $this->lastRequest();
        $this->assertSame('GET', $req->getMethod());
        $this->assertSame('/api/v1/channels', $req->getUri()->getPath());
        parse_str($req->getUri()->getQuery(), $query);
        $this->assertSame('mobile_money', $query['type']);
        $this->assertSame('CMR', $query['country']);

        $this->assertSame('ORANGE_CMR', $result['data'][0]['code']);
    }
}
