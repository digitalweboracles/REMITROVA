<?php

namespace App\Services\Payments\Paga;

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

        return hash_equals(hash('sha512', implode('', $parts)), $providedHash);
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
