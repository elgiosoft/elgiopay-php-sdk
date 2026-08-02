<?php

namespace ElgioPay\SDK\Webhooks;

/**
 * Canonical list of webhook event names elgiopay emits.
 *
 * Kept as class constants (not an enum) so it stays compatible with
 * PHP 8.0+ — the SDK targets 8.0 to widen the install base.
 *
 * Route incoming envelopes with:
 *
 *   match ($envelope['event']) {
 *       WebhookEvents::ISSUING_CARD_CREATED     => onCardCreated($envelope['data']),
 *       WebhookEvents::ISSUING_CARD_TERMINATED  => onCardTerminated($envelope['data']),
 *       WebhookEvents::ISSUING_TRANSACTION_CREATED => onTransaction($envelope['data']),
 *       default => null,
 *   };
 *
 * Merchants opt into events on a per-app basis in the dashboard. An
 * empty subscription list = all events. Set a subset to reduce noise.
 */
final class WebhookEvents
{
    // Payments
    public const PAYMENT_COMPLETED = 'payment.completed';
    public const PAYMENT_FAILED    = 'payment.failed';
    public const PAYMENT_PENDING   = 'payment.pending';

    // Payouts
    public const PAYOUT_COMPLETED = 'payout.completed';
    public const PAYOUT_FAILED    = 'payout.failed';

    // Refunds
    public const REFUND_COMPLETED = 'refund.completed';
    public const REFUND_FAILED    = 'refund.failed';

    // Card issuing — one contract regardless of provider. Data shape
    // for each is documented in IssuingShapes (Card, Authorization,
    // Transaction).
    public const ISSUING_CARD_CREATED    = 'issuing_card.created';
    public const ISSUING_CARD_UPDATED    = 'issuing_card.updated';
    public const ISSUING_CARD_TERMINATED = 'issuing_card.terminated';
    public const ISSUING_CARD_EXPIRATION = 'issuing_card.expiration';
    public const ISSUING_AUTHORIZATION_CREATED = 'issuing_authorization.created';
    public const ISSUING_TRANSACTION_CREATED   = 'issuing_transaction.created';

    /**
     * Full list — useful for building UI (event picker) or asserting
     * that a webhook handler covers every case.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::PAYMENT_COMPLETED,
            self::PAYMENT_FAILED,
            self::PAYMENT_PENDING,
            self::PAYOUT_COMPLETED,
            self::PAYOUT_FAILED,
            self::REFUND_COMPLETED,
            self::REFUND_FAILED,
            self::ISSUING_CARD_CREATED,
            self::ISSUING_CARD_UPDATED,
            self::ISSUING_CARD_TERMINATED,
            self::ISSUING_CARD_EXPIRATION,
            self::ISSUING_AUTHORIZATION_CREATED,
            self::ISSUING_TRANSACTION_CREATED,
        ];
    }
}
