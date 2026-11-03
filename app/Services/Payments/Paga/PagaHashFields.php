<?php

namespace App\Services\Payments\Paga;

class PagaHashFields
{
    public const CREATE_PERSISTENT_ACCOUNT = [
        'referenceNumber', 'accountReference', 'financialIdentificationNumber',
        'creditBankId', 'creditBankAccountNumber', 'callbackUrl',
    ];

    // UPDATED 2026-09-09 — this is now sourced directly from Paga's
    // current, dated (updated 14 days prior to this check) official
    // documentation page for this EXACT endpoint:
    // developer-docs.paga.com/docs/operations-1#6-create-persistent-payment-identifier
    // — not a guess, not a secondhand support example, not a different
    // page's code sample. This is the authoritative source, and
    // critically: this exact combination (correct /register endpoint
    // name + THIS formula) had never actually been tested before —
    // every prior attempt paired either the wrong endpoint name with
    // this formula, or the right endpoint name with a guessed one.
    public const REGISTER_PERSISTENT_ACCOUNT = [
        'referenceNumber', 'accountReference', 'financialIdentificationNumber',
        'creditBankId', 'creditBankAccountNumber', 'callbackUrl',
    ];

    public const UPDATE_PERSISTENT_ACCOUNT = [
        'referenceNumber', 'accountIdentifier', 'phoneNumber', 'firstName',
        'lastName', 'accountName', 'financialIdentificationNumber', 'callbackUrl',
    ];

    public const DELETE_PERSISTENT_ACCOUNT = ['referenceNumber', 'accountIdentifier', 'reason'];
    public const GET_PERSISTENT_ACCOUNT = ['referenceNumber', 'accountIdentifier'];
    public const DEPOSIT_TO_BANK = ['referenceNumber', 'amount', 'destinationBankUUID', 'destinationBankAccountNumber'];
    public const VALIDATE_DEPOSIT_TO_BANK = ['referenceNumber', 'amount', 'destinationBankUUID', 'destinationBankAccountNumber'];
    public const TRANSACTION_STATUS = ['referenceNumber'];
}
