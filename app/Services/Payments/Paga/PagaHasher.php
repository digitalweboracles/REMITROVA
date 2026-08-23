<?php

namespace App\Services\Payments\Paga;

/**
 * PagaHasher — builds and verifies Paga's SHA-512 request/callback hashes.
 *
 * Paga's own confirmation:
 *   "financialIdentificationNumber is optional in the request body. If a
 *    field is optional in request body, it can be omitted in the hash,
 *    only fields that are required in request body would automatically
 *    be required in the hash if part of the hash parameters."
 *
 * A field is only concatenated into the hash string if it is actually
 * present (non-null) in the outgoing request body. The ordered field
 * list per endpoint lives in PagaHashFields, not here.
 */
class PagaHasher
{
    public static function buildHash(array $orderedFields, array $payload, string $hashKey): string
    {
        $parts = [];
        foreach ($orderedFields as $field) {
            if (self::fieldPresent($payload, $field)) {
                $parts[] = self::stringifyValue(self::fieldValue($payload, $field));
            }
        }
        $parts[] = $hashKey;

        return hash('sha512', implode('', $parts));
    }

    /**
     * Verifies an INBOUND callback using Paga's dynamic x-paga-hash /
     * x-paga-hash-parameters header mechanism (confirmed by Paga support
     * as the current, correct approach for Persistent Payment Account
     * callbacks).
     */
    public static function verifyCallback(array $headers, array $payload, string $hashKey): bool
    {
        $providedHash = $headers['x-paga-hash'] ?? null;
        $paramList = $headers['x-paga-hash-parameters'] ?? null;

        if (!$providedHash || !$paramList) {
            return false;
        }

        $fields = array_map('trim', explode(',', $paramList));

        $parts = [];
        foreach ($fields as $field) {
            if (!self::fieldPresent($payload, $field)) {
                return false;
            }
            $parts[] = self::stringifyValue(self::fieldValue($payload, $field));
        }
        $parts[] = $hashKey;

        $computed = hash('sha512', implode('', $parts));

        return hash_equals($computed, $providedHash);
    }

    private static function fieldPresent(array $payload, string $field): bool
    {
        return self::fieldValue($payload, $field) !== null;
    }

    private static function fieldValue(array $payload, string $field)
    {
        if (array_key_exists($field, $payload)) {
            return $payload[$field];
        }
        if (str_contains($field, '.')) {
            $value = $payload;
            foreach (explode('.', $field) as $segment) {
                if (!is_array($value) || !array_key_exists($segment, $value)) {
                    return null;
                }
                $value = $value[$segment];
            }
            return $value;
        }
        return null;
    }

    private static function stringifyValue($value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        return (string) $value;
    }
}
