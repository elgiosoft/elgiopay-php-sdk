<?php

namespace ElgioPay\SDK\Resources\ConnectedMerchants;

/**
 * Static analysis type carrier for the Connected Merchants API.
 *
 * This interface exists purely to host the `@phpstan-type` declarations
 * for input shapes (what to pass) and response shapes (what to expect
 * back). It has no runtime behaviour.
 *
 * The shapes mirror App\Http\Controllers\Api\ConnectedMerchantController
 * on the server side — keep them in sync if that changes.
 *
 * ────────────────────────────────────────────────────────────────────
 * INPUT SHAPES (what to pass)
 * ────────────────────────────────────────────────────────────────────
 *
 * Provide `email` plus at least one of `company_name` / `first_name`.
 *
 * @phpstan-type ConnectedMerchantInput array{
 *     email: string,
 *     company_name?: string,
 *     first_name?: string,
 *     last_name?: string,
 *     business_type?: string,
 *     country?: string,
 *     metadata?: array<string, mixed>,
 * }
 *
 * The bank_* fields are only required (and only consumed) when
 * payout_method is `bank_transfer`.
 *
 * @phpstan-type ConnectedMerchantPayoutInput array{
 *     amount: int|float,
 *     currency?: 'XAF'|'XOF'|'EUR',
 *     payout_method: 'mtn_mobile_money'|'orange_money'|'bank_transfer',
 *     recipient_name: string,
 *     recipient_phone?: string,
 *     recipient_email?: string,
 *     bank_account_number?: string,
 *     bank_name?: string,
 *     bank_code?: string,
 *     description?: string,
 *     reference?: string,
 * }
 *
 * ────────────────────────────────────────────────────────────────────
 * RESPONSE SHAPES (what you get back)
 * ────────────────────────────────────────────────────────────────────
 *
 * Both capability flags simply mirror "is KYC approved". Advisory only —
 * the server does NOT currently block charges for a merchant reporting
 * `charges_enabled: false`.
 *
 * @phpstan-type Capabilities array{
 *     charges_enabled: bool,
 *     payouts_enabled: bool,
 * }
 *
 * @phpstan-type ConnectedMerchant array{
 *     merchant_id: string,
 *     type: 'connected',
 *     company_name: string|null,
 *     kyc_status: 'pending'|'submitted'|'approved'|'rejected',
 *     capabilities: Capabilities,
 *     created_at: string|null,
 * }
 *
 * Deliberately identical whether or not the email already existed
 * (enumeration resistance) — don't infer "new vs existing" from it.
 *
 * @phpstan-type CreateResponse array{
 *     merchant_id: string,
 *     type: 'connected',
 *     kyc_status: 'pending'|'submitted'|'approved'|'rejected',
 *     capabilities: Capabilities,
 *     status: 'created',
 * }
 *
 * @phpstan-type ListMeta array{
 *     current_page: int,
 *     last_page: int,
 *     total: int,
 * }
 *
 * Money fields are `int|float`, not `float`: the server casts to float but
 * JSON serialises a whole number without a decimal point, so `9000.0` arrives
 * as `9000` and json_decode hands back an int. Don't `assertSame` a float.
 *
 * @phpstan-type BalanceResponse array{
 *     merchant_id: string,
 *     currency: string,
 *     balance: int|float,
 *     available_balance: int|float,
 *     reserved_balance: int|float,
 * }
 *
 * @phpstan-type PayoutResponse array{
 *     payout_id: string,
 *     merchant_id: string,
 *     amount: int|float,
 *     currency: string,
 *     status: string,
 * }
 *
 * @phpstan-type StatusResponse array{
 *     merchant_id: string,
 *     is_active: bool,
 *     status: 'deactivated'|'reactivated',
 * }
 *
 * ────────────────────────────────────────────────────────────────────
 * ERROR CODES (the `error` field on a failure body)
 * ────────────────────────────────────────────────────────────────────
 *
 * Read these off ElgioPayException::getResponse()['error'] rather than
 * matching the human-facing message.
 *
 * @phpstan-type ErrorCode 'NOT_A_PLATFORM'
 *     |'NOT_YOUR_MERCHANT'
 *     |'MERCHANT_INACTIVE'
 *     |'PAYOUT_FAILED'
 *     |'VALIDATION_ERROR'
 *     |'SUB_MERCHANT_NOT_FOUND'
 *     |'SUB_MERCHANT_NOT_CONNECTED'
 *     |'SUB_MERCHANT_NOT_YOURS'
 *     |'SUB_MERCHANT_INACTIVE'
 */
interface ConnectedMerchantShapes
{
}
