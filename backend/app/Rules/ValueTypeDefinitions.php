<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class ValueTypeDefinitions implements ValidationRule
{
    private const BASE_TYPES = [
        'array',
        'boolean',
        'date',
        'decimal',
        'double',
        'float',
        'integer',
        'long',
        'short',
        'string',
        'struct',
        'timestamp',
    ];

    private const SIMPLE_TYPES = [
        'boolean',
        'date',
        'decimal',
        'double',
        'float',
        'integer',
        'long',
        'short',
        'string',
        'timestamp',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) || ! array_is_list($value)) {
            $fail('The value types must be a list.');

            return;
        }

        $ids = [];
        $apiNames = [];

        foreach ($value as $index => $definition) {
            if (! is_array($definition)) {
                $fail("Value type at index {$index} must be an object.");

                return;
            }

            $id = $definition['id'] ?? null;
            $apiName = $definition['apiName'] ?? null;
            $displayName = $definition['displayName'] ?? null;
            $version = $definition['version'] ?? null;
            $baseType = $definition['baseType'] ?? null;
            $constraints = $definition['constraints'] ?? [];

            if (! is_string($id) || $id === '' || strlen($id) > 100 || isset($ids[$id])) {
                $fail("Value type at index {$index} has an invalid or duplicate ID.");

                return;
            }
            $ids[$id] = true;

            if (! is_string($apiName)
                || ! preg_match('/^[A-Za-z][A-Za-z0-9_]{0,99}$/', $apiName)
                || isset($apiNames[strtolower($apiName)])) {
                $fail("Value type at index {$index} has an invalid or duplicate API name.");

                return;
            }
            $apiNames[strtolower($apiName)] = true;

            if (! is_string($displayName) || trim($displayName) === '' || strlen($displayName) > 255) {
                $fail("Value type at index {$index} must have a display name.");

                return;
            }

            if (! is_string($version)
                || ! preg_match('/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?(?:\+[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?$/', $version)) {
                $fail("Value type at index {$index} must have a valid semantic version.");

                return;
            }

            if (! $this->validBaseType($baseType)) {
                $fail("Value type at index {$index} has an invalid base type.");

                return;
            }

            if (! is_array($constraints) || ! array_is_list($constraints)) {
                $fail("Value type at index {$index} has invalid constraints.");

                return;
            }

            if (($baseType['type'] ?? null) !== 'string' && $constraints !== []) {
                $fail("Only string value types may define constraints.");

                return;
            }

            if (! $this->validConstraints($constraints)) {
                $fail("Value type at index {$index} has invalid constraints.");

                return;
            }

            if (isset($definition['description'])
                && (! is_string($definition['description']) || strlen($definition['description']) > 2000)) {
                $fail("Value type at index {$index} has an invalid description.");

                return;
            }
        }
    }

    private function validBaseType(mixed $baseType): bool
    {
        if (! is_array($baseType) || ! in_array($baseType['type'] ?? null, self::BASE_TYPES, true)) {
            return false;
        }

        if ($baseType['type'] === 'array') {
            return in_array($baseType['elementType'] ?? null, self::SIMPLE_TYPES, true);
        }

        if ($baseType['type'] !== 'struct') {
            return true;
        }

        $fields = $baseType['fields'] ?? null;
        if (! is_array($fields) || ! array_is_list($fields) || $fields === []) {
            return false;
        }

        $fieldNames = [];
        foreach ($fields as $field) {
            $id = is_array($field) ? ($field['id'] ?? null) : null;
            $apiName = is_array($field) ? ($field['apiName'] ?? null) : null;
            $type = is_array($field) ? ($field['type'] ?? null) : null;
            if (! is_array($field)
                || ! is_string($id)
                || $id === ''
                || ! is_string($apiName)
                || ! preg_match('/^[A-Za-z][A-Za-z0-9_]{0,99}$/', $apiName)
                || ! in_array($type, self::SIMPLE_TYPES, true)
                || isset($fieldNames[strtolower($apiName)])) {
                return false;
            }
            $fieldNames[strtolower($apiName)] = true;
        }

        return true;
    }

    /** @param list<mixed> $constraints */
    private function validConstraints(array $constraints): bool
    {
        $singleUse = [];

        foreach ($constraints as $constraint) {
            $id = is_array($constraint) ? ($constraint['id'] ?? null) : null;
            $constraintType = is_array($constraint) ? ($constraint['type'] ?? null) : null;
            if (! is_array($constraint)
                || ! is_string($id)
                || $id === ''
                || ! in_array($constraintType, ['regex', 'isRid', 'isUuid', 'length'], true)) {
                return false;
            }

            $type = $constraintType;
            if (isset($singleUse[$type])) {
                return false;
            }
            $singleUse[$type] = true;

            if (isset($constraint['failureMessage'])
                && (! is_string($constraint['failureMessage']) || strlen($constraint['failureMessage']) > 500)) {
                return false;
            }

            if ($type === 'regex') {
                $regexPattern = $constraint['regexPattern'] ?? null;
                if (! is_string($regexPattern)
                    || $regexPattern === ''
                    || ! is_bool($constraint['usePartialMatch'] ?? null)
                    || @preg_match('/'.$this->escapeDelimiter($regexPattern).'/u', '') === false) {
                    return false;
                }
            }

            if ($type === 'length') {
                $min = $constraint['minSize'] ?? null;
                $max = $constraint['maxSize'] ?? null;
                if (($min === null && $max === null)
                    || ($min !== null && (! is_int($min) || $min < 0))
                    || ($max !== null && (! is_int($max) || $max < 0))
                    || ($min !== null && $max !== null && $min > $max)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function escapeDelimiter(string $pattern): string
    {
        return str_replace('/', '\/', $pattern);
    }
}
