<?php

namespace App\Services\Payments\Paga;

class PagaHashFields
{
    public const CREATE_PERSISTENT_ACCOUNT = [
        'referenceNumber', 'accountReference', 'financialIdentificationNumber',
        'creditBankId', 'creditBankAccountNumber', 'callbackUrl',
    ];

    // Confirmed by Paga support the concatenation/formula is correct for
    // this field list; still debugging a 401 on their side as of the
    // last exchange (possible key-scoping issue, under investigation).
    public const REGISTER_PERSISTENT_ACCOUNT = [
        'referenceNumber', 'accountReference', 'phoneNumber', 'firstName',
        'lastName', 'accountName', 'financialIdentificationNumber',
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
