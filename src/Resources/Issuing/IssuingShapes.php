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
 * @phpstan-type Cardholder array{
 *     id: int,
 *     processor: 'stripe'|'swychr',
 *     external_id: string,
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
 *     processor: 'stripe'|'swychr',
 *     external_id: string,
 *     type: 'virtual'|'physical',
 *     brand: string|null,
 *     last4: string|null,
 *     masked_number: string,
 *     exp_month: int|null,
 *     exp_year: int|null,
 *     expiry_date: string,
 *     currency: string|null,
 *     balance: int,
 *     balance_currency: string|null,
 *     formatted_balance: string,
 *     status: 'active'|'inactive'|'canceled',
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
 *     external_id: string,
 *     balance: int,
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
 *     processor: 'stripe'|'swychr',
 *     external_id: string,
 *     amount: int,
 *     formatted_amount: string,
 *     merchant_amount: int|null,
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
 *     processor: 'stripe'|'swychr',
 *     external_id: string,
 *     type: 'capture'|'refund'|'cash_withdrawal'|'dispute'|'dispute_loss',
 *     amount: int,
 *     formatted_amount: string,
 *     merchant_amount: int|null,
 *     currency: string,
 *     merchant_currency: string|null,
 *     merchant_data: MerchantData,
 *     merchant_display_name: string,
 *     merchant_location: string,
 *     purchase_details: array<string, mixed>|null,
 *     balance_transaction_id: int|null,
 *     wallet: string|null,
 *     dispute_id: string|null,
 *     card_id: int,
 *     cardholder_id: int,
 *     authorization_id: int|null,
 *     card?: Card,
 *     authorization?: Authorization,
 *     metadata: array<string, mixed>|null,
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
 * Stripe ephemeral key payload (forwarded as-is for use with Stripe.js).
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
 */
interface IssuingShapes
{
}
