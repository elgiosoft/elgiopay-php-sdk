<?php

namespace ElgioPay\SDK\Resources\Issuing;

/**
 * Static analysis type carrier for the Issuing API.
 *
 * This interface exists purely to host the `@phpstan-type` declarations
 * for input shapes (what to pass) and response shapes (what to expect
 * back). It has no runtime behaviour.
 *
 * Tools that read these annotations:
 *   - PHPStan / Psalm verify call sites against the declared shapes.
 *   - PHPStorm + vscode/Intelephense surface field-level autocomplete
 *     when typing array literals.
 *
 * The shapes mirror the Laravel API resources on the server side
 * (App\Http\Resources\Issuing*Resource) — keep them in sync if those
 * change.
 *
 * ────────────────────────────────────────────────────────────────────
 * INPUT SHAPES (what to pass)
 * ────────────────────────────────────────────────────────────────────
 *
 * @phpstan-type BillingAddressInput array{
 *     billing_address_line1: string,
 *     billing_address_line2?: string,
 *     billing_address_city: string,
 *     billing_address_state?: string,
 *     billing_address_postal_code: string,
 *     billing_address_country?: string,
 * }
 *
 * @phpstan-type IndividualDob array{
 *     day: int<1, 31>,
 *     month: int<1, 12>,
 *     year: int<1900, 2010>,
 * }
 *
 * @phpstan-type CardholderInput array{
 *     first_name: string,
 *     last_name: string,
 *     email?: string,
 *     phone_number?: string,
 *     type?: 'individual'|'company',
 *     merchant_user_id?: string,
 *     billing_address_line1: string,
 *     billing_address_line2?: string,
 *     billing_address_city: string,
 *     billing_address_state?: string,
 *     billing_address_postal_code: string,
 *     billing_address_country?: string,
 *     individual_dob?: IndividualDob,
 *     spending_controls?: SpendingControlsInput,
 * }
 *
 * @phpstan-type CardholderUpdateInput array{
 *     email?: string,
 *     phone_number?: string,
 *     status?: 'active'|'inactive',
 *     billing_address_line1?: string,
 *     billing_address_line2?: string,
 *     billing_address_city?: string,
 *     billing_address_state?: string,
 *     billing_address_postal_code?: string,
 *     billing_address_country?: string,
 *     spending_controls?: SpendingControlsInput,
 * }
 *
 * @phpstan-type CardInput array{
 *     cardholder_id: int|string,
 *     product?: 'lite'|'prepaid_credit'|'prepaid_debit'|'contactless',
 *     card_type?: 'VISA'|'MASTERCARD',
 *     amount?: float|int,
 *     currency?: string,
 *     status?: 'active'|'inactive',
 *     type?: 'virtual'|'physical',
 *     daily_limit?: float|int,
 *     transaction_limit?: int,
 *     spending_controls?: SpendingControlsInput,
 * }
 *
 * @phpstan-type SpendingLimit array{
 *     amount: int,
 *     interval: 'per_authorization'|'daily'|'weekly'|'monthly'|'yearly'|'all_time',
 *     categories?: list<string>,
 * }
 *
 * @phpstan-type SpendingControlsInput array{
 *     allowed_categories?: list<string>,
 *     blocked_categories?: list<string>,
 *     spending_limits?: list<SpendingLimit>,
 *     daily_limit?: float|int,
 *     transaction_limit?: int,
 * }
 *
 * ────────────────────────────────────────────────────────────────────
 * RESPONSE SHAPES (what you get back)
 * ────────────────────────────────────────────────────────────────────
 *
 * Mirrors App\Http\Resources\IssuingCardholderResource.
 *
 * @phpstan-type BillingAddress array{
 *     line1: string|null,
 *     line2: string|null,
 *     city: string|null,
 *     state: string|null,
 *     postal_code: string|null,
 *     country: string|null,
 * }
 *
 * Note: `processor` and `external_id` are intentionally NOT exposed on
 * response shapes — the API scrubs them so merchants don't couple to
 * whichever underlying provider (SwyChr / Stripe / …) elgiopay routes
 * through. The `id` and `uuid` are the merchant-facing identifiers.
 *
 * @phpstan-type Cardholder array{
 *     id: int,
 *     uuid: string,
 *     merchant_user_id: string|null,
 *     type: 'individual'|'company',
 *     name: string,
 *     first_name: string,
 *     last_name: string,
 *     email: string|null,
 *     phone_number: string|null,
 *     status: 'active'|'inactive'|'blocked',
 *     billing_address: BillingAddress,
 *     spending_controls: array<string, mixed>|null,
 *     cards_count?: int,
 *     metadata: array<string, mixed>|null,
 *     created_at: string,
 *     updated_at: string,
 * }
 *
 * Mirrors App\Http\Resources\IssuingCardResource.
 *
 * @phpstan-type Card array{
 *     id: int,
 *     uuid: string,
 *     type: 'virtual'|'physical',
 *     brand: string|null,
 *     last4: string|null,
 *     masked_number: string,
 *     exp_month: int|null,
 *     exp_year: int|null,
 *     expiry_date: string,
 *     currency: string|null,
 *     balance: float,
 *     balance_currency: string|null,
 *     formatted_balance: string,
 *     status: 'active'|'inactive'|'canceled'|'pending'|'frozen'|'terminated'|'unknown',
 *     cancellation_reason: string|null,
 *     spending_controls: array<string, mixed>|null,
 *     pin_blocked: bool,
 *     wallets: array<string, mixed>|null,
 *     cardholder_id: int,
 *     cardholder?: Cardholder,
 *     metadata: array<string, mixed>|null,
 *     created_at: string,
 *     activated_at: string|null,
 *     canceled_at: string|null,
 * }
 *
 * @phpstan-type CardBalance array{
 *     card_id: int,
 *     uuid: string,
 *     balance: float,
 *     balance_currency: string|null,
 *     formatted_balance: string,
 * }
 *
 * @phpstan-type CardSensitiveDetails array{
 *     id: int,
 *     number: string|null,
 *     cvc: string|null,
 *     exp_month: int|null,
 *     exp_year: int|null,
 * }
 *
 * Mirrors App\Http\Resources\IssuingAuthorizationResource.
 *
 * @phpstan-type MerchantData array{
 *     name: string|null,
 *     city: string|null,
 *     state: string|null,
 *     country: string|null,
 *     postal_code: string|null,
 *     category: string|null,
 *     category_code: string|null,
 *     network_id: string|null,
 * }
 *
 * @phpstan-type Authorization array{
 *     id: int,
 *     uuid: string,
 *     amount: float,
 *     formatted_amount: string,
 *     merchant_amount: float|null,
 *     currency: string,
 *     merchant_currency: string|null,
 *     status: 'pending'|'closed'|'reversed',
 *     approved: bool,
 *     authorization_method: string|null,
 *     merchant_data: MerchantData,
 *     merchant_display_name: string,
 *     merchant_location: string,
 *     verification_data: array<string, mixed>|null,
 *     wallet: string|null,
 *     card_id: int,
 *     cardholder_id: int,
 *     card?: Card,
 *     metadata: array<string, mixed>|null,
 *     authorized_at: string|null,
 *     created_at: string,
 * }
 *
 * Mirrors App\Http\Resources\IssuingTransactionResource.
 *
 * @phpstan-type Transaction array{
 *     id: int,
 *     uuid: string,
 *     type: 'capture'|'refund'|'refund_reversal'|'cash_withdrawal'|'topup'|'card_issuance'|'dispute'|'dispute_loss',
 *     amount: float,
 *     amount_sign: '+'|'-',
 *     formatted_amount: string,
 *     merchant_amount: float|null,
 *     currency: string,
 *     merchant_currency: string|null,
 *     description: string|null,
 *     status: string|null,
 *     balance_after_transaction: float|null,
 *     merchant_data: MerchantData,
 *     merchant_display_name: string,
 *     merchant_location: string,
 *     purchase_details: array<string, mixed>|null,
 *     wallet: string|null,
 *     dispute_id: string|null,
 *     card_id: int,
 *     cardholder_id: int,
 *     authorization_id: int|null,
 *     card?: Card,
 *     authorization?: Authorization,
 *     metadata: array<string, mixed>|null,
 *     transacted_at: string|null,
 *     created_at: string,
 * }
 *
 * @phpstan-type CardStatistics array{
 *     balance: int,
 *     balance_currency: string|null,
 *     transactions_count: int,
 *     authorizations_count: int,
 *     declined_count: int,
 *     month_spend: int,
 * }
 *
 * Ephemeral key payload (forwarded as-is for use with the client-side
 * card-reveal library).
 *
 * @phpstan-type EphemeralKey array{
 *     id: string,
 *     object: string,
 *     associated_objects: list<array{type: string, id: string}>,
 *     created: int,
 *     expires: int,
 *     livemode: bool,
 *     secret: string,
 * }
 *
 * @phpstan-type ListMeta array{total: int}
 *
 * ────────────────────────────────────────────────────────────────────
 * WEBHOOK EVENTS
 * ────────────────────────────────────────────────────────────────────
 *
 * Envelope every outbound webhook is wrapped in. See
 * ElgioPay\SDK\Webhooks\WebhookSignature::verify to authenticate the
 * request before parsing.
 *
 * @phpstan-type WebhookEnvelope array{
 *     id: string,
 *     event: string,
 *     created: int,
 *     data: array<string, mixed>,
 * }
 *
 * Card issuing event names — use these to route the envelope's `event`
 * to a handler. Kept as a list so IDE autocomplete surfaces them.
 *
 * @phpstan-type IssuingWebhookEvent 'issuing_card.created'
 *     |'issuing_card.updated'
 *     |'issuing_card.terminated'
 *     |'issuing_card.expiration'
 *     |'issuing_authorization.created'
 *     |'issuing_transaction.created'
 */
interface IssuingShapes
{
}
