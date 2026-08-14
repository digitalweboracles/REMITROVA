<?php

namespace App\Services\Payments\Paga;

/**
 * PagaHashFields — the ordered field list PagaHasher::buildHash() uses
 * per endpoint. Keeping these in one place (rather than scattered
 * inline in each API call) means when Paga's docs change or their
 * support team corrects something, there's exactly one place to fix it.
 *
 * Sources, per field list below:
 *   - createPersistentPaymentAccount: Paga docs §6, confirmed by Paga
 *     support that financialIdentificationNumber participates in the
 *     hash only when present (it's optional in the request body).
 *   - depositToBank / validateDepositToBank: Paga docs §9, matches the
 *     Postman collection's pre-request script exactly (no discrepancy
 *     found here).
 */
class PagaHashFields
{
    public const CREATE_PERSISTENT_ACCOUNT = [
        'referenceNumber',
        'accountReference',
        'financialIdentificationNumber', // optional — included only if present, see PagaHasher
        'creditBankId',                  // optional — included only if present
        'creditBankAccountNumber',       // optional — included only if present
        'callbackUrl',
    ];

    public const UPDATE_PERSISTENT_ACCOUNT = [
        'referenceNumber',
        'accountIdentifier',
        'phoneNumber',
        'firstName',
        'lastName',
        'accountName',
        'financialIdentificationNumber',
        'callbackUrl',
    ];

    public const DELETE_PERSISTENT_ACCOUNT = [
        'referenceNumber',
        'accountIdentifier',
        'reason',
    ];

    public const GET_PERSISTENT_ACCOUNT = [
        'referenceNumber',
        'accountIdentifier',
    ];

    public const DEPOSIT_TO_BANK = [
        'referenceNumber',
        'amount',
        'destinationBankUUID',
        'destinationBankAccountNumber',
    ];

    public const VALIDATE_DEPOSIT_TO_BANK = [
        'referenceNumber',
        'amount',
        'destinationBankUUID',
        'destinationBankAccountNumber',
    ];

    /**
     * NOTE: Paga confirmed Transaction Status and Get Operation Status
     * "serve primarily the same purpose" (with Get Operation Status
     * being deprecated), but did not explicitly confirm this hash field
     * list for Transaction Status specifically — this is inferred from
     * the identical referenceNumber-only request body shape shown for
     * both endpoints in their Postman collection. Worth a quick
     * confirmation with Paga before this goes live, even though it's
     * very unlikely to be anything other than referenceNumber alone.
     */
    public const TRANSACTION_STATUS = [
        'referenceNumber',
    ];
}
