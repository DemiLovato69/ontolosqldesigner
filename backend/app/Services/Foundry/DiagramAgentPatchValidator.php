<?php

declare(strict_types=1);

namespace App\Services\Foundry;

use App\Exceptions\FoundryException;

/**
 * Parses and validates the assistant's JSON reply into an allowlisted patch.
 * Unknown or malformed operations are dropped and surfaced as warnings rather
 * than failing the whole turn; only an unparseable reply is a hard error.
 */
class DiagramAgentPatchValidator
{
    /** Additive operations, always permitted. */
    private const ADDITIVE_OPS = [
        'add_table',
        'update_table',
        'add_column',
        'update_column',
        'add_relationship',
        'add_reference_table',
        'add_value_type',
        'update_value_type',
        'add_shared_property_type',
        'add_interface',
        'update_interface',
        'add_interface_link_constraint',
        'add_custom_action',
    ];

    /** Destructive operations, only when the user opted in. */
    private const DESTRUCTIVE_OPS = [
        'delete_table',
        'delete_column',
        'delete_relationship',
        'rename_table',
        'rename_column',
    ];

    /**
     * @return array{message: string, operations: list<array<string, mixed>>, warnings: list<string>}
     *
     * @throws FoundryException when the reply is not valid JSON.
     */
    public function parse(string $content, bool $allowDestructive): array
    {
        $decoded = json_decode($this->stripCodeFence($content), true);

        if (! is_array($decoded)) {
            throw FoundryException::llmInvalidResponse('The model did not return a JSON object.');
        }

        $message = '';
        foreach (['message', 'summary', 'reply'] as $key) {
            if (is_string($decoded[$key] ?? null) && $decoded[$key] !== '') {
                $message = trim($decoded[$key]);
                break;
            }
        }

        $rawOps = [];
        if (isset($decoded['patch']) && is_array($decoded['patch']) && is_array($decoded['patch']['operations'] ?? null)) {
            $rawOps = $decoded['patch']['operations'];
        } elseif (is_array($decoded['operations'] ?? null)) {
            $rawOps = $decoded['operations'];
        }

        $warnings = [];
        foreach ((array) ($decoded['warnings'] ?? []) as $warning) {
            if (is_string($warning) && $warning !== '') {
                $warnings[] = $warning;
            }
        }

        $operations = [];
        foreach ($rawOps as $op) {
            $result = $this->normalizeOperation(is_array($op) ? $op : [], $allowDestructive);
            if ($result['ok']) {
                $operations[] = $result['operation'];
            } elseif ($result['warning'] !== null) {
                $warnings[] = $result['warning'];
            }
        }

        if ($message === '' && $operations === []) {
            $message = 'The agent did not propose any changes.';
        }

        return ['message' => $message, 'operations' => $operations, 'warnings' => array_values($warnings)];
    }

    /**
     * @param array<string, mixed> $op
     * @return array{ok: bool, operation: array<string, mixed>, warning: ?string}
     */
    private function normalizeOperation(array $op, bool $allowDestructive): array
    {
        $name = is_string($op['op'] ?? null) ? $op['op'] : '';

        if ($name === '') {
            return $this->reject('Skipped an operation with no "op" type.');
        }

        $isDestructive = in_array($name, self::DESTRUCTIVE_OPS, true);
        if (! in_array($name, self::ADDITIVE_OPS, true) && ! $isDestructive) {
            return $this->reject("Skipped unsupported operation \"{$name}\".");
        }

        if ($isDestructive && ! $allowDestructive) {
            return $this->reject("Skipped destructive operation \"{$name}\" (not enabled for this request).");
        }

        $required = $this->requiredFields($name);
        foreach ($required as $field) {
            if (! $this->hasField($op, $field)) {
                return $this->reject("Skipped \"{$name}\": missing required field \"{$field}\".");
            }
        }

        return ['ok' => true, 'operation' => $this->cleanOperation($name, $op), 'warning' => null];
    }

    /** @return list<string> */
    private function requiredFields(string $name): array
    {
        return match ($name) {
            'add_table', 'add_reference_table' => ['name'],
            'update_table', 'delete_table' => ['table'],
            'rename_table' => ['table', 'name'],
            'add_column' => ['table', 'name'],
            'update_column', 'delete_column' => ['table', 'column'],
            'rename_column' => ['table', 'column', 'name'],
            'add_relationship', 'delete_relationship' => ['from', 'to'],
            'add_value_type', 'update_value_type', 'add_shared_property_type',
            'add_interface', 'update_interface', 'add_custom_action' => ['name'],
            'add_interface_link_constraint' => ['from'],
            default => [],
        };
    }

    /**
     * @param array<string, mixed> $op
     */
    private function hasField(array $op, string $field): bool
    {
        $value = $op[$field] ?? null;

        if ($field === 'from' || $field === 'to') {
            // Relationship endpoints accept {table, column} or a string id.
            if (is_string($value)) {
                return $value !== '';
            }

            return is_array($value)
                && is_string($value['table'] ?? null)
                && ($value['table'] ?? '') !== '';
        }

        if ($field === 'name' || $field === 'table' || $field === 'column') {
            // "name" may arrive as apiName for metadata operations.
            if ($field === 'name' && (! isset($op['name']) || $op['name'] === '') && is_string($op['apiName'] ?? null)) {
                return $op['apiName'] !== '';
            }

            return is_string($value) && $value !== '';
        }

        return $value !== null;
    }

    /**
     * Keep a bounded set of known keys to avoid passing arbitrary data through.
     *
     * @param array<string, mixed> $op
     * @return array<string, mixed>
     */
    private function cleanOperation(string $name, array $op): array
    {
        $allowedKeys = [
            'op', 'table', 'column', 'name', 'apiName', 'newName', 'from', 'to',
            'columns', 'type', 'sqlType', 'key', 'keyMod', 'nullable', 'indexed',
            'valueType', 'color', 'cardinality', 'description', 'definition',
            'properties', 'displayName', 'reason',
        ];

        $clean = ['op' => $name];
        foreach ($allowedKeys as $key) {
            if ($key === 'op' || ! array_key_exists($key, $op)) {
                continue;
            }
            $clean[$key] = $op[$key];
        }

        // Normalize a metadata name that arrived only as apiName.
        if (! isset($clean['name']) && isset($clean['apiName']) && is_string($clean['apiName'])) {
            $clean['name'] = $clean['apiName'];
        }

        return $clean;
    }

    /**
     * @return array{ok: bool, operation: array<string, mixed>, warning: ?string}
     */
    private function reject(string $warning): array
    {
        return ['ok' => false, 'operation' => [], 'warning' => $warning];
    }

    private function stripCodeFence(string $content): string
    {
        $trimmed = trim($content);

        if (str_starts_with($trimmed, '```')) {
            $trimmed = preg_replace('/^```[a-zA-Z0-9]*\s*/', '', $trimmed) ?? $trimmed;
            $trimmed = preg_replace('/\s*```$/', '', $trimmed) ?? $trimmed;
        }

        return trim($trimmed);
    }
}
