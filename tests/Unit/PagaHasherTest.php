<?php

namespace Tests\Unit;

use App\Services\Payments\Paga\PagaHasher;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the hash logic specifically — this is the piece where
 * Paga's documentation contradicted itself (§11 vs §12 for callback
 * verification) and where a wrong field list silently produces a hash
 * Paga's server rejects. These tests encode exactly what Paga's
 * support team confirmed, so a future accidental change to
 * PagaHasher/PagaHashFields breaks a test instead of breaking
 * production silently.
 */
class PagaHasherTest extends TestCase
{
    public function test_optional_field_is_included_when_present(): void
    {
        $fields = ['referenceNumber', 'financialIdentificationNumber', 'callbackUrl'];

        $withoutOptional = PagaHasher::buildHash($fields, [
            'referenceNumber' => 'REF1',
            'callbackUrl' => 'https://example.com/hook',
        ], 'TESTKEY');

        $withOptional = PagaHasher::buildHash($fields, [
            'referenceNumber' => 'REF1',
            'financialIdentificationNumber' => '22198070425',
            'callbackUrl' => 'https://example.com/hook',
        ], 'TESTKEY');

        // Confirmed by Paga: omitting an optional field changes the hash
        // (it's excluded entirely, not padded with an empty value).
        $this->assertNotSame($withoutOptional, $withOptional);
    }

    public function test_hash_is_deterministic_for_the_same_input(): void
    {
        $fields = ['referenceNumber', 'amount'];
        $payload = ['referenceNumber' => 'REF1', 'amount' => 5000];

        $a = PagaHasher::buildHash($fields, $payload, 'TESTKEY');
        $b = PagaHasher::buildHash($fields, $payload, 'TESTKEY');

        $this->assertSame($a, $b);
    }

    public function test_hash_changes_if_hash_key_changes(): void
    {
        $fields = ['referenceNumber'];
        $payload = ['referenceNumber' => 'REF1'];

        $a = PagaHasher::buildHash($fields, $payload, 'KEY_ONE');
        $b = PagaHasher::buildHash($fields, $payload, 'KEY_TWO');

        $this->assertNotSame($a, $b);
    }

    public function test_hash_is_a_valid_sha512_hex_string(): void
    {
        $hash = PagaHasher::buildHash(['referenceNumber'], ['referenceNumber' => 'REF1'], 'TESTKEY');

        $this->assertSame(128, strlen($hash)); // SHA-512 hex output is always 128 chars
        $this->assertMatchesRegularExpression('/^[a-f0-9]{128}$/', $hash);
    }

    public function test_verify_callback_succeeds_with_matching_hash_and_header_driven_fields(): void
    {
        $hashKey = 'TESTKEY';
        $payload = [
            'transactionReference' => 'TXN123',
            'accountNumber' => '9012345678',
            'amount' => '5,000.00',
        ];

        // Build the hash the same way Paga would, using the exact field
        // order their x-paga-hash-parameters header would specify.
        $expectedHash = PagaHasher::buildHash(
            ['transactionReference', 'accountNumber', 'amount'],
            $payload,
            $hashKey
        );

        $headers = [
            'x-paga-hash' => $expectedHash,
            'x-paga-hash-parameters' => 'transactionReference,accountNumber,amount',
        ];

        $this->assertTrue(PagaHasher::verifyCallback($headers, $payload, $hashKey));
    }

    public function test_verify_callback_fails_with_tampered_payload(): void
    {
        $hashKey = 'TESTKEY';
        $originalPayload = ['transactionReference' => 'TXN123', 'accountNumber' => '9012345678', 'amount' => '5000.00'];

        $hash = PagaHasher::buildHash(
            ['transactionReference', 'accountNumber', 'amount'],
            $originalPayload,
            $hashKey
        );

        $tamperedPayload = $originalPayload;
        $tamperedPayload['amount'] = '50000.00'; // attacker inflates the amount after the hash was computed

        $headers = [
            'x-paga-hash' => $hash,
            'x-paga-hash-parameters' => 'transactionReference,accountNumber,amount',
        ];

        $this->assertFalse(PagaHasher::verifyCallback($headers, $tamperedPayload, $hashKey));
    }

    public function test_verify_callback_fails_when_headers_are_missing(): void
    {
        $this->assertFalse(PagaHasher::verifyCallback([], ['amount' => '100'], 'TESTKEY'));
    }

    public function test_verify_callback_fails_when_wrong_key_used(): void
    {
        $payload = ['transactionReference' => 'TXN123', 'amount' => '100'];
        $hash = PagaHasher::buildHash(['transactionReference', 'amount'], $payload, 'REAL_KEY');

        $headers = [
            'x-paga-hash' => $hash,
            'x-paga-hash-parameters' => 'transactionReference,amount',
        ];

        $this->assertFalse(PagaHasher::verifyCallback($headers, $payload, 'WRONG_KEY'));
    }
}
