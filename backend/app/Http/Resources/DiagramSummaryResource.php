<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Diagram;
use App\Support\DiagramSchema;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property Diagram $resource */
class DiagramSummaryResource extends JsonResource
{
    private const MAX_PREVIEW_TABLES = 20;
    private const MAX_PREVIEW_ROWS_PER_TABLE = 4;
    private const MAX_PREVIEW_EDGES = 64;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'db_type' => $this->resource->db_type ?? 'mysql',
            'schema' => $this->previewSchema($this->resource->schema ?? []),
            'share_token' => $this->resource->share_token,
            'share_access' => $this->resource->share_access?->value,
            'require_approval' => (bool) $this->resource->require_approval,
            'library' => (bool) $this->resource->library,
            'is_owner' => $request->user()?->id === $this->resource->user_id,
        ];
    }

    /**
     * @param array<int, mixed> $schema
     * @return array<int, mixed>
     */
    private function previewSchema(array $schema): array
    {
        $schema = DiagramSchema::withoutRuntimeState($schema) ?? [];

        $tables = [];
        $tableIds = [];

        foreach ($schema as $element) {
            if (! is_array($element) || ($element['type'] ?? null) !== 'table') {
                continue;
            }

            $tables[] = $element;
            $tableIds[$element['id']] = true;

            if (count($tables) >= self::MAX_PREVIEW_TABLES) {
                break;
            }
        }

        $rows = [];
        $rowIds = [];
        $rowCountsByTable = [];

        foreach ($schema as $element) {
            if (! is_array($element) || ($element['type'] ?? null) !== 'row') {
                continue;
            }

            $parentId = $element['parentNode'] ?? null;
            if (! is_string($parentId) || ! isset($tableIds[$parentId])) {
                continue;
            }

            $rowCountsByTable[$parentId] ??= 0;
            if ($rowCountsByTable[$parentId] >= self::MAX_PREVIEW_ROWS_PER_TABLE) {
                continue;
            }

            $rows[] = $element;
            $rowIds[$element['id']] = true;
            $rowCountsByTable[$parentId]++;
        }

        $edges = [];

        foreach ($schema as $element) {
            if (! is_array($element)) {
                continue;
            }

            $source = $element['source'] ?? null;
            $target = $element['target'] ?? null;

            if (! is_string($source) || ! is_string($target)) {
                continue;
            }

            if (! isset($rowIds[$source], $rowIds[$target])) {
                continue;
            }

            $edges[] = $element;

            if (count($edges) >= self::MAX_PREVIEW_EDGES) {
                break;
            }
        }

        return array_values([...$tables, ...$rows, ...$edges]);
    }
}
