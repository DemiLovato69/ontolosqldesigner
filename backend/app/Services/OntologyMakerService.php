<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InvalidSchemaException;

class OntologyMakerService
{
    public function createModule(string $schema): string
    {
        [$tables, $rows, $connections] = $this->parseSchema($schema);
        $rowsById = [];
        $rowsByTable = [];

        foreach ($rows as $row) {
            $rowsById[$row['id']] = $row;
            $rowsByTable[$row['table_id']][] = $row;
        }

        $valueTypes = [];
        $objects = [];
        $objectNamesByTable = [];

        foreach ($tables as $table) {
            $tableRows = $rowsByTable[$table['id']] ?? [];
            if ($tableRows === []) {
                continue;
            }

            $names = $this->entityNames($table['name']);
            $objectNamesByTable[$table['id']] = $names;
            $properties = [];

            foreach ($tableRows as $row) {
                $property = [
                    'api_name' => $this->apiName($row['name']),
                    'display_name' => $this->displayName($row['name']),
                    'type' => $this->mapType($row['sql_type']),
                    'description' => $row['note'],
                ];

                $enumValues = $this->enumValues($row['sql_type']);
                if ($enumValues !== []) {
                    $valueTypeName = $this->apiName($table['name'].'_'.$row['name']).'ValueType';
                    $valueTypes[$valueTypeName] = [
                        'api_name' => $this->apiName($table['name'].'_'.$row['name']),
                        'display_name' => $this->displayName($table['name'].' '.$row['name']),
                        'values' => $enumValues,
                    ];
                    $property['type'] = '"string"';
                    $property['value_type'] = $valueTypeName;
                }

                $properties[] = $property;
            }

            $primaryRows = array_values(array_filter(
                $tableRows,
                fn (array $row): bool => $row['key_mod'] === 'PRIMARY KEY'
            ));
            if (count($primaryRows) === 1) {
                $primaryKey = $this->apiName($primaryRows[0]['name']);
            } elseif (count($primaryRows) > 1) {
                $primaryKey = $this->compositeKeyName($primaryRows);
                $properties[] = [
                    'api_name' => $primaryKey,
                    'display_name' => 'Composite Key',
                    'type' => '"string"',
                ];
            } else {
                $primaryKey = $this->inferPrimaryKey($tableRows);
            }

            $titleProperty = $this->chooseTitleProperty($properties, $primaryKey);
            $objects[] = [
                'const_name' => $names['object'],
                'api_name' => $names['object'],
                'display_name' => $names['display'],
                'plural_display_name' => $names['plural_display'],
                'description' => $table['note'],
                'primary_key' => $primaryKey,
                'title_property' => $titleProperty,
                'properties' => $properties,
            ];
        }

        $links = [];
        $usedLinkNames = [];
        foreach ($connections as $connection) {
            $oneRow = $rowsById[$connection['source_id']] ?? null;
            $manyRow = $rowsById[$connection['target_id']] ?? null;
            if (! $oneRow || ! $manyRow || $oneRow['table_id'] === $manyRow['table_id']) {
                continue;
            }

            $one = $objectNamesByTable[$oneRow['table_id']] ?? null;
            $many = $objectNamesByTable[$manyRow['table_id']] ?? null;
            if (! $one || ! $many) {
                continue;
            }

            $baseName = $one['singular'].'To'.$this->upperFirst($many['plural']);
            $linkName = $baseName;
            if (isset($usedLinkNames[$linkName])) {
                $linkName .= 'By'.$this->upperFirst($this->apiName($manyRow['name']));
            }
            $suffix = 2;
            while (isset($usedLinkNames[$linkName])) {
                $linkName = $baseName.$suffix++;
            }
            $usedLinkNames[$linkName] = true;

            $links[] = [
                'const_name' => $linkName,
                'api_name' => $linkName,
                'one' => $one,
                'many' => $many,
                'foreign_key' => $this->apiName($manyRow['name']),
            ];
        }

        return $this->render($valueTypes, $objects, $links);
    }

    /** @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>, 2: list<array<string, string>>} */
    private function parseSchema(string $schema): array
    {
        if (trim($schema) === '') {
            throw InvalidSchemaException::emptySchema();
        }

        $decoded = json_decode($schema, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw InvalidSchemaException::malformedJson();
        }
        if (! is_array($decoded)) {
            throw InvalidSchemaException::notAnArray();
        }

        $tables = [];
        $rows = [];
        $connections = [];
        foreach ($decoded as $item) {
            if (($item['type'] ?? null) === 'table') {
                $tables[] = [
                    'id' => (string) $item['id'],
                    'name' => (string) $item['label'],
                    'note' => trim((string) ($item['data']['note'] ?? '')),
                ];
            } elseif (($item['type'] ?? null) === 'row') {
                $rows[] = [
                    'id' => (string) $item['id'],
                    'name' => (string) $item['label'],
                    'table_id' => (string) $item['parentNode'],
                    'key_mod' => match ($item['data']['keyMod'] ?? null) {
                        null, 'None' => null,
                        default => $item['data']['keyMod'],
                    },
                    'sql_type' => (string) ($item['data']['sqlType'] ?? 'VARCHAR(255)'),
                    'note' => trim((string) ($item['data']['note'] ?? $item['data']['comment'] ?? '')),
                ];
            } else {
                $sourceId = $item['sourceNode']['id'] ?? $item['source'] ?? null;
                $targetId = $item['targetNode']['id'] ?? $item['target'] ?? null;
                if ($sourceId !== null && $targetId !== null) {
                    $connections[] = ['source_id' => (string) $sourceId, 'target_id' => (string) $targetId];
                }
            }
        }

        return [$tables, $rows, $connections];
    }

    private function mapType(string $sqlType): string
    {
        $type = strtolower(trim($sqlType));
        $base = preg_replace('/\s*\(.*/', '', $type);

        if ($type === 'tinyint(1)' || in_array($base, ['bool', 'boolean', 'yesno'], true) || $base === 'bit') {
            return '"boolean"';
        }
        if (in_array($base, ['tinyint', 'byte'], true)) {
            return '"byte"';
        }
        if (in_array($base, ['smallint', 'int2', 'short', 'smallserial'], true)) {
            return '"short"';
        }
        if (in_array($base, ['mediumint', 'int', 'integer', 'int4', 'serial', 'long', 'autoincrement'], true)) {
            return '"integer"';
        }
        if (in_array($base, ['bigint', 'int8', 'bigserial'], true)) {
            return '"long"';
        }
        if (in_array($base, ['decimal', 'numeric', 'number', 'currency', 'money', 'smallmoney'], true)) {
            if (preg_match('/\((\d+)\s*,\s*(\d+)\)/', $type, $matches)) {
                return sprintf('{ type: "decimal", precision: %d, scale: %d }', $matches[1], $matches[2]);
            }

            return '"decimal"';
        }
        if (in_array($base, ['float', 'real', 'single', 'float4', 'binary_float'], true)) {
            return '"float"';
        }
        if (in_array($base, ['double', 'double precision', 'float8', 'binary_double'], true)) {
            return '"double"';
        }
        if ($base === 'date') {
            return '"date"';
        }
        if (str_starts_with($base, 'timestamp') || in_array($base, ['datetime', 'datetime2', 'smalldatetime', 'datetimeoffset'], true)) {
            return '"timestamp"';
        }
        if (in_array($base, ['blob', 'tinyblob', 'mediumblob', 'longblob', 'bytea', 'binary', 'varbinary', 'raw', 'oleobject', 'attachment'], true)) {
            return '"attachment"';
        }
        if (in_array($base, ['geopoint', 'geoshape', 'mediareference', 'geotimeseries'], true)) {
            return '"'.match ($base) {
                'mediareference' => 'mediaReference',
                'geotimeseries' => 'geotimeSeries',
                default => $base,
            }.'"';
        }

        return '"string"';
    }

    /** @return list<string> */
    private function enumValues(string $sqlType): array
    {
        if (! preg_match('/^(?:ENUM|SET)\s*\((.*)\)$/is', trim($sqlType), $matches)) {
            return [];
        }

        preg_match_all('/\'((?:\\\\.|[^\'])*)\'|"((?:\\\\.|[^"])*)"/', $matches[1], $values, PREG_SET_ORDER);

        return array_values(array_filter(array_map(
            fn (array $value): string => stripcslashes($value[1] !== '' ? $value[1] : $value[2]),
            $values
        ), fn (string $value): bool => $value !== ''));
    }

    /** @param list<array<string, mixed>> $rows */
    private function inferPrimaryKey(array $rows): string
    {
        foreach ($rows as $row) {
            $name = strtolower($row['name']);
            if ($name === 'id' || str_ends_with($name, '_id') || str_ends_with($name, 'id')) {
                return $this->apiName($row['name']);
            }
        }

        return $this->apiName($rows[0]['name']);
    }

    /** @param list<array<string, mixed>> $rows */
    private function compositeKeyName(array $rows): string
    {
        $name = '';
        foreach ($rows as $index => $row) {
            $part = $this->apiName($row['name']);
            $name .= $index === 0 ? $part : $this->upperFirst($part);
        }

        return $name.'Key';
    }

    /** @param list<array<string, mixed>> $properties */
    private function chooseTitleProperty(array $properties, string $primaryKey): string
    {
        foreach ($properties as $property) {
            if ($property['api_name'] !== $primaryKey && $property['type'] === '"string"') {
                return $property['api_name'];
            }
        }

        return $primaryKey;
    }

    /** @return array{object: string, singular: string, plural: string, display: string, plural_display: string} */
    private function entityNames(string $tableName): array
    {
        $object = $this->apiName($tableName);
        $words = preg_split('/[\s_\-]+/', trim($tableName)) ?: [$tableName];
        $last = array_pop($words) ?: $tableName;
        $singularLast = $this->singularize($last);
        $singularSource = implode(' ', [...$words, $singularLast]);
        $display = $this->displayName($singularSource);
        $pluralDisplay = $this->displayName($tableName);
        if (strcasecmp($display, $pluralDisplay) === 0) {
            $pluralDisplay = $display.'s';
        }

        return [
            'object' => $object,
            'singular' => $this->apiName($singularSource),
            'plural' => $object,
            'display' => $display,
            'plural_display' => $pluralDisplay,
        ];
    }

    private function singularize(string $word): string
    {
        $lower = strtolower($word);
        if (str_ends_with($lower, 'ies') && strlen($word) > 3) {
            return substr($word, 0, -3).'y';
        }
        if (str_ends_with($lower, 'ses') || str_ends_with($lower, 'xes') || str_ends_with($lower, 'zes') || str_ends_with($lower, 'ches') || str_ends_with($lower, 'shes')) {
            return substr($word, 0, -2);
        }
        if (str_ends_with($lower, 's') && ! str_ends_with($lower, 'ss')) {
            return substr($word, 0, -1);
        }

        return $word;
    }

    private function apiName(string $value): string
    {
        $words = preg_split('/[^a-zA-Z0-9]+/', trim($value)) ?: [];
        if (count($words) === 1) {
            $words = preg_split('/(?<=[a-z0-9])(?=[A-Z])/', $words[0]) ?: $words;
        }
        $words = array_values(array_filter($words, fn (string $word): bool => $word !== ''));
        $name = strtolower(array_shift($words) ?? 'entity');
        foreach ($words as $word) {
            $name .= ucfirst(strtolower($word));
        }
        $name = preg_replace('/[^a-zA-Z0-9_$]/', '', $name) ?: 'entity';

        return ctype_digit($name[0]) ? '_'.$name : $name;
    }

    private function displayName(string $value): string
    {
        $spaced = preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', ' ', $value);
        $spaced = preg_replace('/[_\-]+/', ' ', $spaced ?? $value);

        return ucwords(strtolower(trim($spaced)));
    }

    private function upperFirst(string $value): string
    {
        return ucfirst($value);
    }

    private function escape(string $value): string
    {
        return addcslashes($value, "\\\"\n\r\t");
    }

    /**
     * @param array<string, array<string, mixed>> $valueTypes
     * @param list<array<string, mixed>> $objects
     * @param list<array<string, mixed>> $links
     */
    private function render(array $valueTypes, array $objects, array $links): string
    {
        $imports = ['defineObject'];
        if ($valueTypes !== []) {
            $imports[] = 'defineValueType';
        }
        if ($links !== []) {
            $imports[] = 'defineLink';
        }
        sort($imports);

        $blocks = ['import { '.implode(', ', $imports).' } from "@osdk/maker";'];

        foreach ($valueTypes as $constName => $valueType) {
            $values = implode(', ', array_map(fn (string $value): string => '"'.$this->escape($value).'"', $valueType['values']));
            $blocks[] = <<<MTS
export const {$constName} = defineValueType({
  apiName: "{$valueType['api_name']}",
  displayName: "{$this->escape($valueType['display_name'])}",
  type: {
    type: "string",
    constraints: [{
      constraint: { type: "oneOf", oneOf: { values: [{$values}], useIgnoreCase: false } },
    }],
  },
  version: "0.1.0",
});
MTS;
        }

        foreach ($objects as $object) {
            $propertyLines = [];
            foreach ($object['properties'] as $property) {
                $valueType = isset($property['value_type']) ? ', valueType: '.$property['value_type'] : '';
                $propertyDescription = $property['description'] ?? '';
                $description = $propertyDescription !== ''
                    ? ', description: "'.$this->escape($propertyDescription).'"'
                    : '';
                $propertyLines[] = '    "'.$property['api_name'].'": { type: '.$property['type'].', displayName: "'.$this->escape($property['display_name']).'"'.$description.$valueType.' },';
            }
            $properties = implode("\n", $propertyLines);
            $description = $object['description'] !== ''
                ? "\n  description: \"".$this->escape($object['description']).'",'
                : '';
            $blocks[] = <<<MTS
export const {$object['const_name']} = defineObject({
  apiName: "{$object['api_name']}",
  displayName: "{$this->escape($object['display_name'])}",
  pluralDisplayName: "{$this->escape($object['plural_display_name'])}",{$description}
  titlePropertyApiName: "{$object['title_property']}",
  primaryKeyPropertyApiName: "{$object['primary_key']}",
  properties: {
{$properties}
  },
});
MTS;
        }

        foreach ($links as $link) {
            $blocks[] = <<<MTS
export const {$link['const_name']} = defineLink({
  apiName: "{$link['api_name']}",
  one: {
    object: {$link['one']['object']},
    metadata: {
      apiName: "{$link['many']['plural']}",
      displayName: "{$this->escape($link['many']['display'])}",
      pluralDisplayName: "{$this->escape($link['many']['plural_display'])}",
    },
  },
  toMany: {
    object: {$link['many']['object']},
    metadata: {
      apiName: "{$link['one']['singular']}",
      displayName: "{$this->escape($link['one']['display'])}",
      pluralDisplayName: "{$this->escape($link['one']['plural_display'])}",
    },
  },
  manyForeignKeyProperty: "{$link['foreign_key']}",
});
MTS;
        }

        return implode("\n\n", $blocks)."\n";
    }
}
