<?php

namespace App\Services\Payments\Paga;

class PagaHashFields
{
    public const CREATE_PERSISTENT_ACCOUNT = [
        'referenceNumber', 'accountReference', 'financialIdentificationNumber',
        'creditBankId', 'creditBankAccountNumber', 'callbackUrl',
    ];

    // UPDATED 2026-09-08 — found via direct search of Paga's current
    // published docs (developer-docs.paga.com/docs/persistent-wallet-account),
    // not from support correspondence this time. That page is internally
    // inconsistent: the prose describes one field list, the actual JS
    // code sample on the same page describes a different one. This uses
    // the CODE SAMPLE's order (firstName/lastName omitted entirely;
    // phoneNumber and accountName come BEFORE accountReference) since
    // code samples get copy-pasted/run far more than prose gets
    // proofread. If this doesn't resolve the 401, the prose version
    // (referenceNumber, accountReference, creditBankId,
    // creditBankAccountNumber, callbackUrl — no phone/name fields at
    // all) is the next thing to try.
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
