<?php

namespace App\Services\Payments\Paga;

/**
 * PagaHashFields — the ordered field list PagaHasher::buildHash() uses
 * per endpoint, confirmed against Paga's docs and their support team's
 * clarifications.
 */
class PagaHashFields
{
    public const CREATE_PERSISTENT_ACCOUNT = [
        'referenceNumber',
        'accountReference',
        'financialIdentificationNumber', // optional — included only if present
        'creditBankId',                  // optional — included only if present
        'creditBankAccountNumber',       // optional — included only if present
        'callbackUrl',
    ];

    /**
     * NOT YET CONFIRMED BY PAGA — this is an educated guess, not a
     * documented or support-confirmed field list.
     *
     * Paga corrected the endpoint itself from /createPersistentPaymentAccount
     * to /registerPersistentPaymentAccount (2026-08-24), and the CREATE
     * hash list above then failed with "Invalid request hash" against
     * the corrected endpoint. Since UPDATE_PERSISTENT_ACCOUNT below
     * (which Paga's docs already confirm) includes phoneNumber,
     * firstName, lastName, and accountName in its hash — not just the
     * reference/callback fields — this list mirrors that same broader
     * pattern rather than the narrower CREATE one, on the theory that
     * "register" and "update" share a hash convention that "create"
     * (the apparently-outdated doc name) didn't reflect.
     *
     * If this doesn't resolve the hash error, don't trust this list —
     * revert to asking Paga directly rather than guessing further.
     */
    public const REGISTER_PERSISTENT_ACCOUNT = [
        'referenceNumber',
        'accountReference',
        'phoneNumber',
        'firstName',
        'lastName',
        'accountName',
        'financialIdentificationNumber', // optional — included only if present
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
     * NOTE: inferred from the identical request-body shape shown for
     * both Transaction Status and Get Operation Status in Paga's
     * Postman collection, not explicitly confirmed by Paga support.
     */
    public const TRANSACTION_STATUS = [
        'referenceNumber',
    ];
}
