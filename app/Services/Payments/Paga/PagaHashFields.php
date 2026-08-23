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
