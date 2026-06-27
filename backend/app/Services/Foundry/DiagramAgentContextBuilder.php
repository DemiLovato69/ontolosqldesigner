<?php

declare(strict_types=1);

namespace App\Services\Foundry;

use App\Models\Diagram;
use App\Support\DiagramSchema;

/**
 * Builds a compact, complete representation of a diagram for the agent. The full
 * diagram is included (tables, columns, relationships, pipeline transforms, and
 * all ontology metadata), but Vue Flow runtime/view-only state is stripped.
 */
class DiagramAgentContextBuilder
{
    /**
     * @return array{context: array<string, mixed>, summary: array<string, mixed>, bytes: int}
     */
    public function build(Diagram $diagram): array
    {
        $schema = DiagramSchema::withoutRuntimeState(is_array($diagram->schema) ? $diagram->schema : []) ?? [];

        /** @var array<int, array<string, mixed>> $elements */
        $elements = array_values(array_filter($schema, 'is_array'));

        $rowParent = [];
        $rowLabel = [];
        $tableLabel = [];
        foreach ($elements as $element) {
            $id = (string) ($element['id'] ?? '');
            if ($id === '') {
                continue;
            }
            if (($element['type'] ?? null) === 'row') {
                $rowParent[$id] = (string) ($element['parentNode'] ?? '');
                $rowLabel[$id] = (string) ($element['label'] ?? '');
            } elseif (($element['type'] ?? null) === 'table') {
                $tableLabel[$id] = (string) ($element['label'] ?? '');
            }
        }

        $columnsByTable = [];
        foreach ($elements as $element) {
            if (($element['type'] ?? null) !== 'row') {
                continue;
            }
            $parent = (string) ($element['parentNode'] ?? '');
            if ($parent === '') {
                continue;
            }
            $data = is_array($element['data'] ?? null) ? $element['data'] : [];
            $columnsByTable[$parent][] = array_filter([
                'id' => (string) ($element['id'] ?? ''),
                'name' => (string) ($element['label'] ?? ''),
                'type' => $this->stringOrNull($data['sqlType'] ?? null),
                'key' => $this->stringOrNull($data['keyMod'] ?? null),
                'nullable' => isset($data['nullable']) ? (bool) $data['nullable'] : null,
                'indexed' => isset($data['indexed']) ? (bool) $data['indexed'] : null,
                'valueType' => $this->stringOrNull($data['valueType'] ?? null),
            ], static fn ($value): bool => $value !== null && $value !== '');
        }

        $tables = [];
        $transforms = [];
        $relationships = [];
        $referenceTables = 0;
        $columnCount = 0;

        foreach ($elements as $element) {
            $type = $element['type'] ?? null;
            $data = is_array($element['data'] ?? null) ? $element['data'] : [];

            if ($type === 'table') {
                $id = (string) ($element['id'] ?? '');
                $isReference = ($data['reference'] ?? false) === true || ($data['tableKind'] ?? null) === 'reference';
                if ($isReference) {
                    $referenceTables++;
                }
                $columns = $columnsByTable[$id] ?? [];
                $columnCount += count($columns);

                $foundry = null;
                $source = is_array($data['referenceSource'] ?? null) ? $data['referenceSource'] : [];
                if (($source['importedFrom'] ?? null) === 'foundry-dataset') {
                    $foundry = array_filter([
                        'datasetRid' => $this->stringOrNull($source['datasetRid'] ?? null),
                        'datasetName' => $this->stringOrNull($source['datasetName'] ?? null),
                    ], static fn ($value): bool => $value !== null);
                }

                $tables[] = array_filter([
                    'id' => $id,
                    'name' => (string) ($element['label'] ?? ''),
                    'kind' => $isReference ? 'reference' : 'real',
                    'exportable' => ($data['exportable'] ?? null) === false ? false : true,
                    'foundry' => $foundry,
                    'columns' => $columns,
                ], static fn ($value): bool => $value !== null);

                continue;
            }

            if ($type === 'pipeline-transform') {
                $transforms[] = array_filter([
                    'id' => (string) ($element['id'] ?? ''),
                    'name' => (string) ($element['label'] ?? ''),
                    'sourceRowIds' => array_values(array_filter((array) ($data['sourceRowIds'] ?? []), 'is_string')),
                    'targetRowIds' => array_values(array_filter((array) ($data['targetRowIds'] ?? []), 'is_string')),
                ], static fn ($value): bool => $value !== null && $value !== '');

                continue;
            }

            // Edges carry both source and target.
            if (isset($element['source'], $element['target'])) {
                $source = (string) $element['source'];
                $target = (string) $element['target'];
                $relationships[] = array_filter([
                    'id' => (string) ($element['id'] ?? ''),
                    'kind' => $this->stringOrNull($data['linkKind'] ?? null) ?? 'relationship',
                    'exportable' => ($data['exportable'] ?? null) === false ? false : true,
                    'from' => array_filter([
                        'table' => $tableLabel[$rowParent[$source] ?? ''] ?? null,
                        'column' => $rowLabel[$source] ?? null,
                    ], static fn ($value): bool => $value !== null && $value !== ''),
                    'to' => array_filter([
                        'table' => $tableLabel[$rowParent[$target] ?? ''] ?? null,
                        'column' => $rowLabel[$target] ?? null,
                    ], static fn ($value): bool => $value !== null && $value !== ''),
                ], static fn ($value): bool => $value !== null && $value !== []);
            }
        }

        $metadata = [
            'value_types' => $this->toArray($diagram->value_types),
            'shared_property_types' => $this->toArray($diagram->shared_property_types),
            'interfaces' => $this->toArray($diagram->interfaces),
            'interface_link_constraints' => $this->toArray($diagram->interface_link_constraints),
            'custom_actions' => $this->toArray($diagram->custom_actions),
        ];

        $context = [
            'diagram' => [
                'name' => (string) $diagram->name,
                'db_type' => $diagram->db_type?->value ?? 'ontology',
            ],
            'tables' => $tables,
            'relationships' => $relationships,
            'transforms' => $transforms,
        ] + $metadata;

        $bytes = strlen((string) json_encode($context));

        $summary = [
            'table_count' => count($tables) - $referenceTables,
            'reference_table_count' => $referenceTables,
            'column_count' => $columnCount,
            'relationship_count' => count($relationships),
            'transform_count' => count($transforms),
            'metadata_counts' => [
                'value_types' => count($metadata['value_types']),
                'shared_property_types' => count($metadata['shared_property_types']),
                'interfaces' => count($metadata['interfaces']),
                'interface_link_constraints' => count($metadata['interface_link_constraints']),
                'custom_actions' => count($metadata['custom_actions']),
            ],
            'context_bytes' => $bytes,
        ];

        return ['context' => $context, 'summary' => $summary, 'bytes' => $bytes];
    }

    /** @return array<int, mixed> */
    private function toArray(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : (is_scalar($value) ? (string) $value : null);
    }
}
