<?php

namespace App\Services\Payments\Paga;

/**
 * PagaHasher — builds and verifies Paga's SHA-512 request/callback hashes.
 *
 * Paga's own confirmation (support thread, [date of Paga's response]):
 *   "financialIdentificationNumber is optional in the request body. If a
 *    field is optional in request body, it can be omitted in the hash,
 *    only fields that are required in request body would automatically
 *    be required in the hash if part of the hash parameters."
 *
 * In other words: the hash formula for an endpoint is a FIXED, ORDERED
 * list of field names, but a field is only concatenated into the hash
 * string if it is actually present (non-null) in the outgoing request
 * body. This class takes that ordered field list as an explicit
 * parameter per call site (see PagaHashFields) rather than hardcoding
 * it here, so the "which fields, in which order" question always lives
 * in one clearly-documented place per endpoint.
 *
 * Outbound requests: buildHash($orderedFields, $payload, $hashKey)
 * Inbound webhooks:   verifyCallback($headers, $payload, $hashKey)
 *                      — uses the dynamic x-paga-hash-parameters header
 *                      (Section 12 of Paga's docs — confirmed by Paga as
 *                      the current, correct mechanism for Persistent
 *                      Payment Account callbacks) rather than a fixed
 *                      formula, since the header tells us exactly which
 *                      fields THIS specific callback hashed.
 */
class PagaHasher
{
    /**
     * Builds the hash for an OUTBOUND request to Paga.
     *
     * @param string[] $orderedFields Field names in the exact order Paga's
     *                                docs specify for this endpoint.
     * @param array<string, mixed> $payload The request body about to be sent.
     * @param string $hashKey The merchant's Paga HMAC key.
     */
    public static function buildHash(array $orderedFields, array $payload, string $hashKey): string
    {
        $parts = [];
        foreach ($orderedFields as $field) {
            // Confirmed rule: only include a field if it's actually present
            // in the body being sent. Optional fields that were omitted
            // from the request are also omitted from the hash — never
            // padded with an empty string, which would silently produce
            // a hash Paga's server does not expect.
            if (self::fieldPresent($payload, $field)) {
                $parts[] = self::stringifyValue(self::fieldValue($payload, $field));
            }
        }
        $parts[] = $hashKey;

        return hash('sha512', implode('', $parts));
    }

    /**
     * Verifies an INBOUND callback (webhook) using Paga's dynamic
     * x-paga-hash / x-paga-hash-parameters header mechanism.
     *
     * @param array<string, string> $headers Lower-cased header names => values.
     * @param array<string, mixed> $payload The decoded callback body.
     */
    public static function verifyCallback(array $headers, array $payload, string $hashKey): bool
    {
        $providedHash = $headers['x-paga-hash'] ?? null;
        $paramList = $headers['x-paga-hash-parameters'] ?? null;

        if (!$providedHash || !$paramList) {
            return false; // Missing either header — cannot verify, treat as untrusted.
        }

        // x-paga-hash-parameters is a comma-separated ordered list of the
        // field names Paga used to build THIS callback's hash, e.g.
        // "transactionReference,accountNumber,amount".
        $fields = array_map('trim', explode(',', $paramList));

        $parts = [];
        foreach ($fields as $field) {
            if (!self::fieldPresent($payload, $field)) {
                // The header named a field that isn't in the payload —
                // something is inconsistent; fail closed rather than guess.
                return false;
            }
            $parts[] = self::stringifyValue(self::fieldValue($payload, $field));
        }
        $parts[] = $hashKey;

        $computed = hash('sha512', implode('', $parts));

        return hash_equals($computed, $providedHash);
    }

    /**
     * Supports simple dot-notation for nested fields (Paga's docs use
     * this for some hash parameter names), falling back to a flat lookup.
     */
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

    /**
     * Amounts must be stringified WITHOUT reformatting (no added
     * decimals, no thousands separators) — Paga hashes the value
     * exactly as sent. Booleans and other scalars are cast plainly.
     */
    private static function stringifyValue($value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        return (string) $value;
    }
}
