<?php

namespace Tests\Unit;

use App\Services\Payments\Paga\PagaHasher;
use PHPUnit\Framework\TestCase;

class PagaHasherTest extends TestCase
{
    public function test_optional_field_is_included_when_present(): void
    {
        $fields = ['referenceNumber', 'financialIdentificationNumber', 'callbackUrl'];
        $a = PagaHasher::buildHash($fields, ['referenceNumber' => 'REF1', 'callbackUrl' => 'https://example.com'], 'KEY');
        $b = PagaHasher::buildHash($fields, ['referenceNumber' => 'REF1', 'financialIdentificationNumber' => '221', 'callbackUrl' => 'https://example.com'], 'KEY');
        $this->assertNotSame($a, $b);
    }

    public function test_hash_is_deterministic(): void
    {
        $fields = ['referenceNumber', 'amount'];
        $payload = ['referenceNumber' => 'REF1', 'amount' => 5000];
        $this->assertSame(
            PagaHasher::buildHash($fields, $payload, 'KEY'),
            PagaHasher::buildHash($fields, $payload, 'KEY')
        );
    }

    public function test_hash_changes_with_key(): void
    {
        $a = PagaHasher::buildHash(['referenceNumber'], ['referenceNumber' => 'REF1'], 'KEY_ONE');
        $b = PagaHasher::buildHash(['referenceNumber'], ['referenceNumber' => 'REF1'], 'KEY_TWO');
        $this->assertNotSame($a, $b);
    }

    public function test_hash_is_valid_sha512(): void
    {
        $hash = PagaHasher::buildHash(['referenceNumber'], ['referenceNumber' => 'REF1'], 'KEY');
        $this->assertSame(128, strlen($hash));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{128}$/', $hash);
    }

    public function test_verify_callback_succeeds(): void
    {
        $payload = ['transactionReference' => 'TXN123', 'accountNumber' => '9012345678', 'amount' => '5,000.00'];
        $hash = PagaHasher::buildHash(['transactionReference', 'accountNumber', 'amount'], $payload, 'KEY');
        $headers = ['x-paga-hash' => $hash, 'x-paga-hash-parameters' => 'transactionReference,accountNumber,amount'];
        $this->assertTrue(PagaHasher::verifyCallback($headers, $payload, 'KEY'));
    }

    public function test_verify_callback_fails_when_tampered(): void
    {
        $orig = ['transactionReference' => 'TXN123', 'amount' => '5000.00'];
        $hash = PagaHasher::buildHash(['transactionReference', 'amount'], $orig, 'KEY');
        $tampered = $orig;
        $tampered['amount'] = '50000.00';
        $headers = ['x-paga-hash' => $hash, 'x-paga-hash-parameters' => 'transactionReference,amount'];
        $this->assertFalse(PagaHasher::verifyCallback($headers, $tampered, 'KEY'));
    }
}
