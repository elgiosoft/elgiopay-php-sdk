<?php

namespace ElgioPay\SDK\Webhooks;

use ElgioPay\SDK\ElgioPayException;

/**
 * Verify the authenticity of an inbound elgiopay webhook.
 *
 * elgiopay signs every outbound delivery with an HMAC-SHA256 of the
 * raw request body plus a timestamp:
 *
 *   X-Elgiopay-Signature: t=<unix_ts>,v1=<hex>
 *   X-Elgiopay-Event:     <event.name>
 *   X-Elgiopay-Event-Id:  <event_id>
 *
 * where the hex is `hash_hmac('sha256', "<t>.<body>", <app.webhook_secret>)`.
 *
 * Usage:
 *
 *   $envelope = WebhookSignature::verify(
 *       $request->getContent(),                             // raw body, unparsed
 *       $request->header('X-Elgiopay-Signature'),
 *       $webhookSecret,                                     // from app dashboard
 *   );
 *   // $envelope is an array shaped like { id, event, created, data }.
 *
 * A stale signature (older than `$tolerance` seconds — default 5 min)
 * is rejected even if the HMAC checks out, to blunt replay attacks.
 *
 * @phpstan-import-type WebhookEnvelope from \ElgioPay\SDK\Resources\Issuing\IssuingShapes
 */
class WebhookSignature
{
    public const DEFAULT_TOLERANCE_SECONDS = 300;

    /**
     * Verify signature + freshness and return the decoded envelope.
     *
     * @return array{id: string, event: string, created: int, data: array<string, mixed>}
     * @throws ElgioPayException on any verification failure — signature
     *                          mismatch, missing header, malformed body,
     *                          stale timestamp. Callers should return
     *                          401 to elgiopay in that case; the delivery
     *                          will be retried with backoff.
     */
    public static function verify(
        string $rawBody,
        ?string $signatureHeader,
        string $webhookSecret,
        int $toleranceSeconds = self::DEFAULT_TOLERANCE_SECONDS
    ): array {
        if ($signatureHeader === null || $signatureHeader === '') {
            throw new ElgioPayException('Missing X-Elgiopay-Signature header', 401);
        }

        [$timestamp, $expectedSig] = self::parseHeader($signatureHeader);

        // Freshness check first — cheap and eliminates a whole class of
        // replay attacks before we spend cycles on hash_equals.
        $age = time() - $timestamp;
        if ($age > $toleranceSeconds || $age < -$toleranceSeconds) {
            throw new ElgioPayException(
                "Webhook timestamp outside tolerance window ({$toleranceSeconds}s)",
                401
            );
        }

        $computed = hash_hmac('sha256', $timestamp . '.' . $rawBody, $webhookSecret);
        // hash_equals is timing-safe — the naive === comparison leaks
        // signature bytes over network timing.
        if (!hash_equals($computed, $expectedSig)) {
            throw new ElgioPayException('Webhook signature mismatch', 401);
        }

        $envelope = json_decode($rawBody, true);
        if (!is_array($envelope) || !isset($envelope['event'], $envelope['data'])) {
            throw new ElgioPayException('Webhook body is not a valid envelope', 400);
        }

        return $envelope;
    }

    /**
     * Parse `t=<ts>,v1=<hex>` into [timestamp, signature]. Additional
     * scheme versions (v2, v3, …) would land here as new branches;
     * unknown schemes fall through so we don't silently accept them.
     *
     * @return array{0: int, 1: string}
     */
    private static function parseHeader(string $header): array
    {
        $timestamp = null;
        $signature = null;

        foreach (explode(',', $header) as $part) {
            $part = trim($part);
            if (str_starts_with($part, 't=')) {
                $timestamp = (int) substr($part, 2);
            } elseif (str_starts_with($part, 'v1=')) {
                $signature = substr($part, 3);
            }
        }

        if (!$timestamp || !$signature) {
            throw new ElgioPayException('Malformed X-Elgiopay-Signature header', 401);
        }

        return [$timestamp, $signature];
    }
}
