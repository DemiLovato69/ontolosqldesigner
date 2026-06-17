<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InvalidSchemaException;

class OntologyMakerService
{
    /**
     * @param list<array<string, mixed>> $definitions
     * @param array<string, list<array<string, mixed>>> $metadata
     */
    public function createModule(string $schema, array $definitions = [], array $metadata = []): string
    {
        [$tables, $rows, $connections] = $this->parseSchema($schema);
        $rowsById = [];
        $rowsByTable = [];

        foreach ($rows as $row) {
            $rowsById[$row['id']] = $row;
            $rowsByTable[$row['table_id']][] = $row;
        }

        $valueTypes = [];
        $valueTypeNamesById = [];
        $valueTypeDefinitionsById = [];
        foreach ($definitions as $definition) {
            if (! is_string($definition['id'] ?? null)) {
                continue;
            }
            $constName = (string) ($definition['apiName'] ?? '');
            if ($constName === '') {
                continue;
            }
            $valueTypes[$constName] = [
                'api_name' => $constName,
                'display_name' => (string) ($definition['displayName'] ?? $constName),
                'description' => (string) ($definition['description'] ?? ''),
                'version' => (string) ($definition['version'] ?? '1.0.0'),
                'base_type' => is_array($definition['baseType'] ?? null)
                    ? $definition['baseType']
                    : ['type' => 'string'],
                'constraints' => is_array($definition['constraints'] ?? null)
                    ? $definition['constraints']
                    : [],
            ];
            $valueTypeNamesById[$definition['id']] = $constName;
            $valueTypeDefinitionsById[$definition['id']] = $definition;
        }
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
            $indexedColumns = $this->indexedColumns($table, $tableRows);

            foreach ($tableRows as $row) {
                $apiName = $this->apiName($row['name']);
                [$propertyType, $isArray] = $this->mapPropertyType($row);
                $property = [
                    'api_name' => $apiName,
                    'display_name' => $this->displayName($row['name']),
                    'type' => $propertyType,
                    'array' => $isArray,
                    'description' => $row['note'],
                    'nullable' => $row['nullable'],
                    'key_mod' => $row['key_mod'],
                    'indexed' => $row['indexed'] || in_array($row['name'], $indexedColumns, true),
                ];

                $enumValues = [];
                $referencedValueType = $valueTypeNamesById[$row['value_type_id'] ?? ''] ?? null;
                if ($referencedValueType !== null) {
                    $property['value_type'] = $referencedValueType;
                    [$property['type'], $property['array']] = $this->mapValueTypeBaseToProperty(
                        $valueTypeDefinitionsById[$row['value_type_id']]['baseType'] ?? ['type' => 'string']
                    );
                } else {
                    $enumValues = $this->enumValues($row['sql_type']);
                }
                if ($enumValues !== []) {
                    $valueTypeName = $this->apiName($table['name'].'_'.$row['name']).'ValueType';
                    while (isset($valueTypes[$valueTypeName])) {
                        $valueTypeName .= 'Generated';
                    }
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
                    'description' => 'Synthetic key generated from composite primary key columns: '.implode(', ', array_map(fn (array $row): string => $row['name'], $primaryRows)),
                    'nullable' => false,
                    'key_mod' => 'PRIMARY KEY',
                    'indexed' => true,
                ];
            } else {
                $primaryKey = $this->inferPrimaryKey($tableRows);
            }

            $titleProperty = $this->selectedTitleProperty($table, $tableRows)
                ?? $this->chooseTitleProperty($properties, $primaryKey);
            $objects[] = [
                'const_name' => $names['object'],
                'api_name' => $names['object'],
                'display_name' => $names['display'],
                'plural_display_name' => $names['plural_display'],
                'description' => $table['note'],
                'primary_key' => $primaryKey,
                'title_property' => $titleProperty,
                'properties' => $properties,
                'implements_interfaces' => is_array($table['data']['implementsInterfaces'] ?? null)
                    ? array_values($table['data']['implementsInterfaces'])
                    : [],
                'constraints' => $this->objectConstraints($table, $tableRows, $primaryRows),
                'actions' => $this->ontologyActions($table),
            ];
        }

        $linksByRelationship = [];
        $usedLinkNames = [];
        foreach ($connections as $connection) {
            $sourceRow = $rowsById[$connection['source_id']] ?? null;
            $targetRow = $rowsById[$connection['target_id']] ?? null;
            if (! $sourceRow || ! $targetRow || $sourceRow['table_id'] === $targetRow['table_id']) {
                continue;
            }

            if (($connection['relationship_type'] ?? null) === 'many-to-many') {
                $source = $objectNamesByTable[$sourceRow['table_id']] ?? null;
                $target = $objectNamesByTable[$targetRow['table_id']] ?? null;
                if (! $source || ! $target) {
                    continue;
                }

                $relationshipKey = 'many-to-many:'.implode(':', [
                    min($sourceRow['id'], $targetRow['id']),
                    max($sourceRow['id'], $targetRow['id']),
                ]);
                $linkName = $linksByRelationship[$relationshipKey]['const_name'] ?? null;
                if ($linkName === null) {
                    $linkName = $this->uniqueLinkName(
                        $source['singular'].'To'.$this->upperFirst($target['plural']),
                        $usedLinkNames,
                        $this->apiName($targetRow['name'])
                    );
                }

                $linksByRelationship[$relationshipKey] = [
                    'const_name' => $linkName,
                    'api_name' => $linkName,
                    'kind' => 'many-to-many',
                    'many' => $source,
                    'to_many' => $target,
                ];
                continue;
            }

            [$oneRow, $manyRow] = $this->oneToManyRows($sourceRow, $targetRow, $connection['relationship_type'] ?? null);
            $one = $objectNamesByTable[$oneRow['table_id']] ?? null;
            $many = $objectNamesByTable[$manyRow['table_id']] ?? null;
            if (! $one || ! $many) {
                continue;
            }

            $relationshipKey = 'one-to-many:'.$oneRow['id'].':'.$manyRow['id'];
            $linkName = $linksByRelationship[$relationshipKey]['const_name'] ?? null;
            if ($linkName === null) {
                $linkName = $this->uniqueLinkName(
                    $one['singular'].'To'.$this->upperFirst($many['plural']),
                    $usedLinkNames,
                    $this->apiName($manyRow['name'])
                );
            }

            $linksByRelationship[$relationshipKey] = [
                'const_name' => $linkName,
                'api_name' => $linkName,
                'kind' => 'one-to-many',
                'one' => $one,
                'many' => $many,
                'foreign_key' => $this->apiName($manyRow['name']),
                'cardinality' => ($connection['relationship_type'] ?? null) === 'one-to-one' ? 'OneToOne' : 'OneToMany',
            ];
        }

        return $this->render($valueTypes, $objects, array_values($linksByRelationship), $metadata);
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
        $referenceTableIds = [];
        $rowTableIds = [];
        foreach ($decoded as $item) {
            if (! is_array($item)) {
                continue;
            }
            if (($item['type'] ?? null) === 'table' && $this->isReferenceTableItem($item)) {
                $referenceTableIds[(string) $item['id']] = true;
            }
            if (($item['type'] ?? null) === 'row') {
                $rowTableIds[(string) $item['id']] = (string) ($item['parentNode'] ?? '');
            }
        }
        foreach ($decoded as $item) {
            if (! is_array($item) || $this->isNonExportableDiagramItem($item)) {
                continue;
            }
            if (($item['type'] ?? null) === 'table' && ! isset($referenceTableIds[(string) $item['id']])) {
                $tables[] = [
                    'id' => (string) $item['id'],
                    'name' => (string) $item['label'],
                    'note' => trim((string) ($item['data']['description'] ?? $item['data']['note'] ?? '')),
                    'data' => $item['data'] ?? [],
                ];
            } elseif (($item['type'] ?? null) === 'row' && ! isset($referenceTableIds[(string) ($item['parentNode'] ?? '')])) {
                $rows[] = [
                    'id' => (string) $item['id'],
                    'name' => (string) $item['label'],
                    'table_id' => (string) $item['parentNode'],
                    'key_mod' => match ($item['data']['keyMod'] ?? null) {
                        null, 'None' => null,
                        default => $item['data']['keyMod'],
                    },
                    'sql_type' => (string) ($item['data']['sqlType'] ?? 'VARCHAR(255)'),
                    'note' => trim((string) ($item['data']['description'] ?? $item['data']['note'] ?? $item['data']['comment'] ?? '')),
                    'nullable' => (bool) ($item['data']['nullable'] ?? false),
                    'indexed' => (bool) ($item['data']['indexed'] ?? true),
                    'ontology_base_type' => is_array($item['data']['ontologyBaseType'] ?? null)
                        ? $item['data']['ontologyBaseType']
                        : null,
                    'value_type_id' => is_string($item['data']['valueTypeId'] ?? null)
                        ? $item['data']['valueTypeId']
                        : null,
                ];
            } else {
                // sourceNode/targetNode are Vue Flow caches and may be stale after
                // reconnecting an edge. The scalar endpoint fields are canonical.
                $sourceId = $item['source'] ?? $item['sourceNode']['id'] ?? null;
                $targetId = $item['target'] ?? $item['targetNode']['id'] ?? null;
                if ($sourceId !== null && $targetId !== null && $this->isExportableConnectionItem($item, $rowTableIds, $referenceTableIds)) {
                    $connections[] = [
                        'source_id' => (string) $sourceId,
                        'target_id' => (string) $targetId,
                        'relationship_type' => $item['data']['relationshipType'] ?? null,
                    ];
                }
            }
        }

        return [$tables, $rows, $connections];
    }

    /** @param array<string, mixed> $item */
    private function isReferenceTableItem(array $item): bool
    {
        return (bool) ($item['data']['reference'] ?? false)
            || ($item['data']['tableKind'] ?? null) === 'reference';
    }

    /** @param array<string, mixed> $item */
    private function isNonExportableDiagramItem(array $item): bool
    {
        return ($item['type'] ?? null) === 'pipeline-transform'
            || ($item['data']['exportable'] ?? true) === false
            || in_array($item['data']['linkKind'] ?? null, ['reference', 'transform'], true);
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, string> $rowTableIds
     * @param array<string, true> $referenceTableIds
     */
    private function isExportableConnectionItem(array $item, array $rowTableIds, array $referenceTableIds): bool
    {
        $sourceId = $item['source'] ?? $item['sourceNode']['id'] ?? null;
        $targetId = $item['target'] ?? $item['targetNode']['id'] ?? null;
        if (! is_string($sourceId) || ! is_string($targetId)) {
            return false;
        }
        $sourceTableId = $rowTableIds[$sourceId] ?? null;
        $targetTableId = $rowTableIds[$targetId] ?? null;

        return $sourceTableId !== null
            && $targetTableId !== null
            && ! isset($referenceTableIds[$sourceTableId])
            && ! isset($referenceTableIds[$targetTableId]);
    }

    /**
     * @param array<string, mixed> $table
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private function indexedColumns(array $table, array $rows): array
    {
        $columns = [];
        foreach (($table['data']['uniqueTogether'] ?? []) as $group) {
            $validGroup = $this->validConstraintColumns($group, $rows);
            if (count($validGroup) >= 2) {
                array_push($columns, ...$validGroup);
            }
        }
        foreach (($table['data']['fulltextIndexes'] ?? []) as $group) {
            $validGroup = $this->validConstraintColumns($group, $rows);
            if (count($validGroup) >= 1) {
                array_push($columns, ...$validGroup);
            }
        }

        return array_values(array_unique($columns));
    }

    /**
     * @param list<string> $group
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private function validConstraintColumns(array $group, array $rows): array
    {
        $rowNames = array_column($rows, 'name');

        return array_values(array_filter($group, fn (string $column): bool => in_array($column, $rowNames, true)));
    }

    /**
     * @param array<string, mixed> $table
     * @param list<array<string, mixed>> $rows
     * @param list<array<string, mixed>> $primaryRows
     * @return list<string>
     */
    private function objectConstraints(array $table, array $rows, array $primaryRows): array
    {
        $constraints = [];

        if (count($primaryRows) > 1) {
            $constraints[] = 'composite primary key: '.implode(', ', array_map(fn (array $row): string => $row['name'], $primaryRows));
        } elseif (count($primaryRows) === 1) {
            $constraints[] = 'primary key: '.$primaryRows[0]['name'];
        }

        foreach ($rows as $row) {
            if ($row['key_mod'] === 'UNIQUE') {
                $constraints[] = 'unique: '.$row['name'];
            } elseif ($row['key_mod'] === 'INDEX') {
                $constraints[] = 'index: '.$row['name'];
            }
        }

        foreach (($table['data']['uniqueTogether'] ?? []) as $group) {
            $validGroup = $this->validConstraintColumns($group, $rows);
            if (count($validGroup) >= 2) {
                $constraints[] = 'unique together: '.implode(', ', $validGroup);
            }
        }
        foreach (($table['data']['fulltextIndexes'] ?? []) as $group) {
            $validGroup = $this->validConstraintColumns($group, $rows);
            if (count($validGroup) >= 1) {
                $constraints[] = 'fulltext index: '.implode(', ', $validGroup);
            }
        }

        return $constraints;
    }

    /**
     * @param array<string, mixed> $table
     * @return array{create: bool, modify: bool, delete: bool}
     */
    private function ontologyActions(array $table): array
    {
        $actions = $table['data']['ontologyActions'] ?? [];

        return [
            'create' => (bool) ($actions['create'] ?? false),
            'modify' => (bool) ($actions['modify'] ?? false),
            'delete' => (bool) ($actions['delete'] ?? false),
        ];
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
        if (in_array($base, ['mediumint', 'int', 'integer', 'int4', 'serial', 'autoincrement'], true)) {
            return '"integer"';
        }
        if (in_array($base, ['bigint', 'int8', 'bigserial', 'long'], true)) {
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

    /**
     * @param array<string, mixed> $row
     * @return array{0: string, 1: bool}
     */
    private function mapPropertyType(array $row): array
    {
        $baseType = $row['ontology_base_type'] ?? null;
        if (is_array($baseType)) {
            return $this->mapOntologyBaseType($baseType);
        }

        $sqlType = trim((string) $row['sql_type']);
        if (preg_match('/^ARRAY\s*<(.+)>$/i', $sqlType, $matches)) {
            return [$this->mapType($matches[1]), true];
        }
        if (preg_match('/^VECTOR(?:\(\d+\))?$/i', $sqlType)) {
            return ['"vector"', false];
        }
        if (strcasecmp($sqlType, 'GEOHASH') === 0) {
            return ['"geohash"', false];
        }
        if (strcasecmp($sqlType, 'STRUCT') === 0) {
            return ['{ type: "struct", structDefinition: {} }', false];
        }

        return [$this->mapType($sqlType), false];
    }

    /**
     * @param  array<string, mixed>  $baseType
     * @return array{0: string, 1: bool}
     */
    private function mapOntologyBaseType(array $baseType): array
    {
        $type = strtoupper((string) ($baseType['type'] ?? 'STRING'));
        if ($type === 'ARRAY') {
            [$subType] = $this->mapOntologyBaseType(
                is_array($baseType['subType'] ?? null) ? $baseType['subType'] : ['type' => 'STRING']
            );

            return [$subType, true];
        }
        if ($type === 'STRUCT') {
            $fields = [];
            foreach (($baseType['structFields'] ?? []) as $field) {
                if (! is_array($field)) {
                    continue;
                }
                [$fieldType] = $this->mapOntologyBaseType(
                    is_array($field['fieldType'] ?? null) ? $field['fieldType'] : ['type' => 'STRING']
                );
                $apiName = $this->apiName((string) ($field['apiName'] ?? 'field'));
                $fields[] = '"'.$apiName.'": '.$fieldType;
            }

            return ['{ type: "struct", structDefinition: { '.implode(', ', $fields).' } }', false];
        }

        return [match ($type) {
            'VECTOR' => '"vector"',
            'GEOHASH' => '"geohash"',
            'MEDIA_REFERENCE' => '"mediaReference"',
            'DECIMAL' => isset($baseType['precision'], $baseType['scale'])
                ? sprintf('{ type: "decimal", precision: %d, scale: %d }', $baseType['precision'], $baseType['scale'])
                : '"decimal"',
            default => $this->mapType($type),
        }, false];
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

    /**
     * @param array<string, mixed> $sourceRow
     * @param array<string, mixed> $targetRow
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function oneToManyRows(array $sourceRow, array $targetRow, ?string $relationshipType): array
    {
        if ($sourceRow['key_mod'] === 'FOREIGN KEY' && $targetRow['key_mod'] !== 'FOREIGN KEY') {
            return [$targetRow, $sourceRow];
        }
        if ($targetRow['key_mod'] === 'FOREIGN KEY' && $sourceRow['key_mod'] !== 'FOREIGN KEY') {
            return [$sourceRow, $targetRow];
        }
        if ($relationshipType === 'many-to-one') {
            return [$targetRow, $sourceRow];
        }

        return [$sourceRow, $targetRow];
    }

    /** @param array<string, true> $usedLinkNames */
    private function uniqueLinkName(string $baseName, array &$usedLinkNames, string $dedupeSuffix): string
    {
        $linkName = $baseName;
        if (isset($usedLinkNames[$linkName])) {
            $linkName .= 'By'.$this->upperFirst($dedupeSuffix);
        }
        $suffix = 2;
        while (isset($usedLinkNames[$linkName])) {
            $linkName = $baseName.$suffix++;
        }
        $usedLinkNames[$linkName] = true;

        return $linkName;
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

    /** @param list<array<string, mixed>> $rows */
    private function selectedTitleProperty(array $table, array $rows): ?string
    {
        $selectedRowId = $table['data']['titlePropertyRowId'] ?? null;
        if (! is_string($selectedRowId) || $selectedRowId === '') {
            return null;
        }

        foreach ($rows as $row) {
            if ($row['id'] === $selectedRowId) {
                return $this->apiName($row['name']);
            }
        }

        return null;
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
     * @param array<string, mixed> $baseType
     * @param  list<array<string, mixed>>  $constraints
     */
    private function renderValueTypeType(array $baseType, array $constraints): string
    {
        $type = (string) ($baseType['type'] ?? 'string');
        $renderedBaseType = match ($type) {
            'array' => '{ type: "array", elementType: "'.$this->escape((string) ($baseType['elementType'] ?? 'string')).'" }',
            'struct' => $this->renderValueTypeStruct($baseType),
            default => '"'.$this->escape($type).'"',
        };

        $renderedConstraints = array_map(
            fn (array $constraint): string => $this->renderValueTypeConstraint($constraint),
            $constraints
        );
        $constraintsLine = $renderedConstraints === []
            ? ''
            : ",\n    constraints: [\n      ".implode(",\n      ", $renderedConstraints)."\n    ]";

        return "{\n    type: {$renderedBaseType}{$constraintsLine}\n  }";
    }

    /**
     * @param array<string, mixed> $baseType
     * @return array{0: string, 1: bool}
     */
    private function mapValueTypeBaseToProperty(array $baseType): array
    {
        $type = (string) ($baseType['type'] ?? 'string');
        if ($type === 'array') {
            return ['"'.$this->escape((string) ($baseType['elementType'] ?? 'string')).'"', true];
        }
        if ($type === 'struct') {
            $fields = [];
            foreach (($baseType['fields'] ?? []) as $field) {
                if (! is_array($field)) {
                    continue;
                }
                $fields[] = '"'.$this->escape((string) ($field['apiName'] ?? 'field')).'": "'
                    .$this->escape((string) ($field['type'] ?? 'string')).'"';
            }

            return ['{ type: "struct", structDefinition: { '.implode(', ', $fields).' } }', false];
        }

        return ['"'.$this->escape($type).'"', false];
    }

    /** @param array<string, mixed> $baseType */
    private function renderValueTypeStruct(array $baseType): string
    {
        $fields = [];
        foreach (($baseType['fields'] ?? []) as $field) {
            if (! is_array($field)) {
                continue;
            }
            $fields[] = '{ identifier: "'.$this->escape((string) ($field['apiName'] ?? 'field'))
                .'", baseType: "'.$this->escape((string) ($field['type'] ?? 'string')).'" }';
        }

        return '{ type: "struct", fields: ['.implode(', ', $fields).'] }';
    }

    /** @param array<string, mixed> $constraint */
    private function renderValueTypeConstraint(array $constraint): string
    {
        $payload = match ($constraint['type'] ?? null) {
            'regex' => 'type: "regex", regex: { regexPattern: "'.$this->escape((string) ($constraint['regexPattern'] ?? ''))
                .'", usePartialMatch: '.(($constraint['usePartialMatch'] ?? false) ? 'true' : 'false').' }',
            'isRid' => 'type: "isRid", isRid: {}',
            'isUuid' => 'type: "isUuid", isUuid: {}',
            'length' => 'type: "length", length: { '.implode(', ', array_filter([
                isset($constraint['minSize']) ? 'minSize: '.(int) $constraint['minSize'] : null,
                isset($constraint['maxSize']) ? 'maxSize: '.(int) $constraint['maxSize'] : null,
            ])).' }',
            default => '',
        };
        $failureMessage = trim((string) ($constraint['failureMessage'] ?? ''));
        $message = $failureMessage !== ''
            ? ', failureMessage: { message: "'.$this->escape($failureMessage).'" }'
            : '';

        return '{ constraint: { '.$payload.' }'.$message.' }';
    }

    /**
     * @param array<string, array<string, mixed>> $valueTypes
     * @param list<array<string, mixed>> $objects
     * @param list<array<string, mixed>> $links
     * @param array<string, list<array<string, mixed>>> $metadata
     */
    private function render(array $valueTypes, array $objects, array $links, array $metadata = []): string
    {
        $imports = ['defineObject'];
        if (($metadata['interfaces'] ?? []) !== []) {
            $imports[] = 'defineInterface';
        }
        if (($metadata['interface_link_constraints'] ?? []) !== []) {
            $imports[] = 'defineInterfaceLinkConstraint';
        }
        if (($metadata['shared_property_types'] ?? []) !== []) {
            $imports[] = 'defineSharedPropertyType';
        }
        if (($metadata['custom_actions'] ?? []) !== []) {
            $imports[] = 'defineAction';
        }
        if ($valueTypes !== []) {
            $imports[] = 'defineValueType';
        }
        if ($links !== []) {
            $imports[] = 'defineLink';
        }
        foreach ($objects as $object) {
            if ($object['actions']['create']) {
                $imports[] = 'defineCreateObjectAction';
            }
            if ($object['actions']['modify']) {
                $imports[] = 'defineModifyObjectAction';
            }
            if ($object['actions']['delete']) {
                $imports[] = 'defineDeleteObjectAction';
            }
        }
        $imports = array_values(array_unique($imports));
        sort($imports);

        $blocks = ['import { '.implode(', ', $imports).' } from "@osdk/maker";'];

        foreach ($metadata['shared_property_types'] ?? [] as $definition) {
            $constName = $this->metadataConstName($definition);
            if ($constName === null) {
                continue;
            }
            $blocks[] = "export const {$constName} = defineSharedPropertyType(".$this->renderJsLiteral($this->makerDefinition($definition)).");";
        }

        foreach ($valueTypes as $constName => $valueType) {
            if (isset($valueType['values'])) {
                $values = implode(', ', array_map(fn (string $value): string => '"'.$this->escape($value).'"', $valueType['values']));
                $type = <<<MTS
{
    type: "string",
    constraints: [{
      constraint: { type: "oneOf", oneOf: { values: [{$values}], useIgnoreCase: false } },
    }],
  }
MTS;
                $description = '';
                $version = '0.1.0';
            } else {
                $type = $this->renderValueTypeType($valueType['base_type'], $valueType['constraints']);
                $description = $valueType['description'] !== ''
                    ? "\n  description: \"".$this->escape($valueType['description'])."\","
                    : '';
                $version = $valueType['version'];
            }
            $blocks[] = <<<MTS
export const {$constName} = defineValueType({
  apiName: "{$valueType['api_name']}",
  displayName: "{$this->escape($valueType['display_name'])}",{$description}
  type: {$type},
  version: "{$version}",
});
MTS;
        }

        foreach ($metadata['interfaces'] ?? [] as $definition) {
            $constName = $this->metadataConstName($definition);
            if ($constName === null) {
                continue;
            }
            $blocks[] = "export const {$constName} = defineInterface(".$this->renderJsLiteral($this->makerDefinition($definition)).");";
        }

        foreach ($objects as $object) {
            $propertyLines = [];
            foreach ($object['properties'] as $property) {
                $valueType = isset($property['value_type']) ? ', valueType: '.$property['value_type'] : '';
                $propertyDescription = $property['description'] ?? '';
                $description = $propertyDescription !== ''
                    ? ', description: "'.$this->escape($propertyDescription).'"'
                    : '';
                $nullability = ($property['nullable'] ?? true) === false || ($property['key_mod'] ?? null) === 'PRIMARY KEY'
                    ? ', nullability: { ' . (($property['array'] ?? true) ? 'noNulls: true, noEmptyCollections: true' : 'noNulls: true, noEmptyCollections: false' ) . ' }'
                    : '';
                $indexedForSearch = ($property['indexed'] ?? false) || in_array($property['key_mod'] ?? null, ['PRIMARY KEY', 'UNIQUE', 'INDEX'], true)
                    ? ', indexedForSearch: true'
                    : '';
                $array = ($property['array'] ?? false) ? ', array: true' : '';
                $propertyLines[] = '    "'.$property['api_name'].'": { type: '.$property['type'].$array.', displayName: "'.$this->escape($property['display_name']).'"'.$description.$nullability.$indexedForSearch.$valueType.' },';
            }
            $properties = implode("\n", $propertyLines);
            $description = $object['description'] !== ''
                ? "\n  description: \"".$this->escape($object['description'])."\","
                : '';
            $implementsInterfaces = '';
            if ($object['implements_interfaces'] !== []) {
                $interfaces = implode(', ', array_map(
                    fn (mixed $apiName): string => $this->apiName((string) $apiName),
                    array_filter($object['implements_interfaces'], fn (mixed $apiName): bool => is_string($apiName) && $apiName !== '')
                ));
                if ($interfaces !== '') {
                    $implementsInterfaces = "\n  implementsInterfaces: [{$interfaces}],";
                }
            }
            $constraintComments = '';
            if ($object['constraints'] !== []) {
                $constraintComments = "\n  // SQL constraints captured from the diagram:";
                foreach ($object['constraints'] as $constraint) {
                    $constraintComments .= "\n  // - ".$this->escape($constraint);
                }
            }
            $blocks[] = <<<MTS
export const {$object['const_name']} = defineObject({
  apiName: "{$object['api_name']}",
  displayName: "{$this->escape($object['display_name'])}",
  pluralDisplayName: "{$this->escape($object['plural_display_name'])}",{$description}
  titlePropertyApiName: "{$object['title_property']}",
  primaryKeyPropertyApiName: "{$object['primary_key']}",{$implementsInterfaces}{$constraintComments}
  properties: {
{$properties}
  },
});
MTS;
        }

        foreach ($links as $link) {
            if ($link['kind'] === 'many-to-many') {
                $blocks[] = <<<MTS
export const {$link['const_name']} = defineLink({
  apiName: "{$link['api_name']}",
  many: {
    object: {$link['many']['object']},
    metadata: {
      apiName: "{$link['to_many']['plural']}",
      displayName: "{$this->escape($link['to_many']['display'])}",
      pluralDisplayName: "{$this->escape($link['to_many']['plural_display'])}",
    },
  },
  toMany: {
    object: {$link['to_many']['object']},
    metadata: {
      apiName: "{$link['many']['plural']}",
      displayName: "{$this->escape($link['many']['display'])}",
      pluralDisplayName: "{$this->escape($link['many']['plural_display'])}",
    },
  },
});
MTS;
                continue;
            }

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
  cardinality: "{$link['cardinality']}",
});
MTS;
        }

        foreach ($metadata['interface_link_constraints'] ?? [] as $definition) {
            $constName = $this->metadataConstName($definition);
            if ($constName === null) {
                continue;
            }
            $blocks[] = "export const {$constName} = defineInterfaceLinkConstraint(".$this->renderJsLiteral($this->makerDefinition($definition)).");";
        }

        foreach ($objects as $object) {
            $actionName = $this->upperFirst($object['const_name']);
            if ($object['actions']['create']) {
                $blocks[] = <<<MTS
export const create{$actionName}Action = defineCreateObjectAction({
  objectType: {$object['const_name']},
});
MTS;
            }
            if ($object['actions']['modify']) {
                $blocks[] = <<<MTS
export const modify{$actionName}Action = defineModifyObjectAction({
  objectType: {$object['const_name']},
});
MTS;
            }
            if ($object['actions']['delete']) {
                $blocks[] = <<<MTS
export const delete{$actionName}Action = defineDeleteObjectAction({
  objectType: {$object['const_name']},
});
MTS;
            }
        }

        foreach ($metadata['custom_actions'] ?? [] as $definition) {
            $constName = $this->metadataConstName($definition);
            if ($constName === null) {
                continue;
            }
            $blocks[] = "export const {$constName} = defineAction(".$this->renderJsLiteral($this->makerDefinition($definition)).");";
        }

        return implode("\n\n", $blocks)."\n";
    }

    /** @param array<string, mixed> $definition */
    private function metadataConstName(array $definition): ?string
    {
        $apiName = (string) ($definition['apiName'] ?? '');
        if ($apiName === '') {
            return null;
        }

        return $this->apiName($apiName);
    }

    private function renderJsLiteral(mixed $value): string
    {
        if (is_array($value)) {
            if (isset($value['__raw']) && is_string($value['__raw'])) {
                return $this->apiName($value['__raw']);
            }
            if (array_is_list($value)) {
                return '['.implode(', ', array_map(fn (mixed $item): string => $this->renderJsLiteral($item), $value)).']';
            }

            $pairs = [];
            foreach ($value as $key => $item) {
                $pairs[] = json_encode((string) $key, JSON_THROW_ON_ERROR).': '.$this->renderJsLiteral($item);
            }

            return '{ '.implode(', ', $pairs).' }';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return json_encode((string) $value, JSON_THROW_ON_ERROR);
    }

    /** @param array<string, mixed> $definition */
    private function makerDefinition(array $definition): array
    {
        unset($definition['id']);
        if (isset($definition['makerParameters']) && is_array($definition['makerParameters'])) {
            $definition['parameters'] = $definition['makerParameters'];
            unset($definition['makerParameters']);
        }
        if (isset($definition['properties']) && is_array($definition['properties']) && array_is_list($definition['properties'])) {
            $properties = [];
            foreach ($definition['properties'] as $property) {
                if (! is_array($property)) {
                    continue;
                }
                $apiName = (string) ($property['apiName'] ?? '');
                if ($apiName === '') {
                    continue;
                }
                unset($property['id'], $property['apiName']);
                $properties[$apiName] = $property;
            }
            $definition['properties'] = $properties;
        }
        if (isset($definition['from']) && is_string($definition['from']) && $definition['from'] !== '') {
            $definition['from'] = ['__raw' => $definition['from']];
        }
        if (isset($definition['toMany']['interface']) && is_string($definition['toMany']['interface']) && $definition['toMany']['interface'] !== '') {
            $definition['toMany']['interface'] = ['__raw' => $definition['toMany']['interface']];
        }
        if (isset($definition['toOne']['interface']) && is_string($definition['toOne']['interface']) && $definition['toOne']['interface'] !== '') {
            $definition['toOne']['interface'] = ['__raw' => $definition['toOne']['interface']];
        }
        unset($definition['actionType'], $definition['rules'], $definition['functionRid'], $definition['functionVersion']);

        return $definition;
    }
}
