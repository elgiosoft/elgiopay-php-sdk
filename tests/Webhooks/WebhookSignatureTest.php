<?php

namespace ElgioPay\SDK\Tests\Webhooks;

use ElgioPay\SDK\ElgioPayException;
use ElgioPay\SDK\Webhooks\WebhookSignature;
use PHPUnit\Framework\TestCase;

/**
 * Security-sensitive: any regression here means merchants can be
 * tricked into processing forged webhook payloads. Cover the full
 * verification matrix (valid, tampered, replayed, mangled).
 */
class WebhookSignatureTest extends TestCase
{
    private const SECRET = 'whsec_test_super_secret_key';

    public function test_verify_accepts_a_valid_signature(): void
    {
        $body = json_encode(['id' => 'evt_1', 'event' => 'issuing_card.created', 'created' => time(), 'data' => ['id' => 1]]);
        $ts = time();
        $sig = hash_hmac('sha256', $ts . '.' . $body, self::SECRET);

        $envelope = WebhookSignature::verify($body, "t={$ts},v1={$sig}", self::SECRET);
        $this->assertSame('issuing_card.created', $envelope['event']);
        $this->assertSame(1, $envelope['data']['id']);
    }

    public function test_verify_rejects_tampered_body(): void
    {
        $body = json_encode(['id' => 'evt_1', 'event' => 'ok', 'created' => time(), 'data' => []]);
        $ts = time();
        $sig = hash_hmac('sha256', $ts . '.' . $body, self::SECRET);

        // Attacker swaps the body after we signed it.
        $tampered = json_encode(['id' => 'evt_1', 'event' => 'ok', 'created' => time(), 'data' => ['stolen' => true]]);

        $this->expectException(ElgioPayException::class);
        $this->expectExceptionMessage('signature mismatch');
        WebhookSignature::verify($tampered, "t={$ts},v1={$sig}", self::SECRET);
    }

    public function test_verify_rejects_wrong_secret(): void
    {
        $body = json_encode(['id' => 'evt_1', 'event' => 'ok', 'created' => time(), 'data' => []]);
        $ts = time();
        $sig = hash_hmac('sha256', $ts . '.' . $body, 'not_the_real_secret');

        $this->expectException(ElgioPayException::class);
        WebhookSignature::verify($body, "t={$ts},v1={$sig}", self::SECRET);
    }

    public function test_verify_rejects_stale_timestamp(): void
    {
        $body = json_encode(['id' => 'evt_1', 'event' => 'ok', 'created' => time(), 'data' => []]);
        $ts = time() - 3600;  // one hour ago, well past 5-min tolerance
        $sig = hash_hmac('sha256', $ts . '.' . $body, self::SECRET);

        $this->expectException(ElgioPayException::class);
        $this->expectExceptionMessage('tolerance window');
        WebhookSignature::verify($body, "t={$ts},v1={$sig}", self::SECRET);
    }

    public function test_verify_rejects_missing_header(): void
    {
        $this->expectException(ElgioPayException::class);
        $this->expectExceptionMessage('Missing');
        WebhookSignature::verify('{}', null, self::SECRET);
    }

    public function test_verify_rejects_malformed_header(): void
    {
        $this->expectException(ElgioPayException::class);
        $this->expectExceptionMessage('Malformed');
        WebhookSignature::verify('{}', 'not-a-signature', self::SECRET);
    }

    public function test_verify_rejects_non_envelope_body(): void
    {
        $body = json_encode(['not' => 'an envelope']);
        $ts = time();
        $sig = hash_hmac('sha256', $ts . '.' . $body, self::SECRET);

        $this->expectException(ElgioPayException::class);
        $this->expectExceptionMessage('envelope');
        WebhookSignature::verify($body, "t={$ts},v1={$sig}", self::SECRET);
    }
}
