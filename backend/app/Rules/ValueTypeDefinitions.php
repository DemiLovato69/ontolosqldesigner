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
                $fail("Value type \"{$displayName}\" (index {$index}) has an invalid base type.");

                return;
            }

            if (! is_array($constraints) || ! array_is_list($constraints)) {
                $fail("Value type \"{$displayName}\" (index {$index}) constraints must be a list.");

                return;
            }

            if (($baseType['type'] ?? null) !== 'string' && $constraints !== []) {
                $fail("Value type \"{$displayName}\" (index {$index}) may only define constraints on a string base type.");

                return;
            }

            $constraintError = $this->constraintError($constraints);
            if ($constraintError !== null) {
                $fail("Value type \"{$displayName}\" (index {$index}) has an invalid constraint: {$constraintError}");

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

    /**
     * @param list<mixed> $constraints
     * @return string|null Human-readable reason the constraints are invalid, or null when valid.
     */
    private function constraintError(array $constraints): ?string
    {
        $allowed = ['oneOf', 'regex', 'isRid', 'isUuid', 'length'];
        $seen = [];

        foreach ($constraints as $position => $constraint) {
            $number = (int) $position + 1;

            if (! is_array($constraint)) {
                return "constraint #{$number} must be an object.";
            }

            $id = $constraint['id'] ?? null;
            if (! is_string($id) || $id === '') {
                return "constraint #{$number} is missing an id.";
            }

            $type = $constraint['type'] ?? null;
            if (! in_array($type, $allowed, true)) {
                $shown = is_string($type) && $type !== '' ? "\"{$type}\"" : 'an empty type';
                return "constraint #{$number} has an unsupported type {$shown} (allowed: ".implode(', ', $allowed).').';
            }

            if (isset($seen[$type])) {
                return "the {$type} constraint is defined more than once.";
            }
            $seen[$type] = true;

            if (isset($constraint['failureMessage'])
                && (! is_string($constraint['failureMessage']) || strlen($constraint['failureMessage']) > 500)) {
                return "the {$type} constraint failure message must be text of at most 500 characters.";
            }

            $error = match ($type) {
                'oneOf' => $this->oneOfError($constraint),
                'regex' => $this->regexError($constraint),
                'length' => $this->lengthError($constraint),
                default => null,
            };
            if ($error !== null) {
                return $error;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $constraint */
    private function oneOfError(array $constraint): ?string
    {
        $values = $constraint['values'] ?? null;
        if (! is_array($values) || ! array_is_list($values) || $values === []) {
            return 'the allowed values (enum) constraint needs at least one value.';
        }
        if (array_key_exists('useIgnoreCase', $constraint) && ! is_bool($constraint['useIgnoreCase'])) {
            return 'the allowed values (enum) constraint "useIgnoreCase" must be true or false.';
        }

        $ignoreCase = (bool) ($constraint['useIgnoreCase'] ?? false);
        $seen = [];
        foreach ($values as $value) {
            if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
                return 'the allowed values (enum) constraint values must be text.';
            }
            $stringValue = (string) $value;
            if (trim($stringValue) === '') {
                return 'the allowed values (enum) constraint cannot contain empty values.';
            }
            if (strlen($stringValue) > 255) {
                return 'the allowed values (enum) constraint values must be 255 characters or fewer.';
            }
            $key = $ignoreCase ? strtolower($stringValue) : $stringValue;
            if (isset($seen[$key])) {
                return 'the allowed values (enum) constraint has duplicate values.';
            }
            $seen[$key] = true;
        }

        return null;
    }

    /** @param array<string, mixed> $constraint */
    private function regexError(array $constraint): ?string
    {
        $regexPattern = $constraint['regexPattern'] ?? null;
        if (! is_string($regexPattern) || $regexPattern === '') {
            return 'the regex constraint needs a pattern.';
        }
        if (! is_bool($constraint['usePartialMatch'] ?? null)) {
            return 'the regex constraint "usePartialMatch" must be true or false.';
        }
        if (@preg_match('/'.$this->escapeDelimiter($regexPattern).'/u', '') === false) {
            return 'the regex constraint pattern is not a valid expression.';
        }

        return null;
    }

    /** @param array<string, mixed> $constraint */
    private function lengthError(array $constraint): ?string
    {
        $min = $constraint['minSize'] ?? null;
        $max = $constraint['maxSize'] ?? null;
        if ($min === null && $max === null) {
            return 'the length constraint needs a minimum or maximum size.';
        }
        if ($min !== null && (! is_int($min) || $min < 0)) {
            return 'the length constraint minimum must be a whole number of at least 0.';
        }
        if ($max !== null && (! is_int($max) || $max < 0)) {
            return 'the length constraint maximum must be a whole number of at least 0.';
        }
        if ($min !== null && $max !== null && $min > $max) {
            return 'the length constraint minimum cannot be greater than the maximum.';
        }

        return null;
    }

    private function escapeDelimiter(string $pattern): string
    {
        return str_replace('/', '\/', $pattern);
    }
}
