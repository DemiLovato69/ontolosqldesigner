<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Diagram;
use App\Support\DiagramSchema;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;

/** @property Diagram $resource */
class DiagramResource extends JsonResource
{
    /** @return array<string, mixed> */
    #[ResponseField('id', 'int', 'The diagram ID.')]
    #[ResponseField('name', 'string', 'The diagram name.')]
    #[ResponseField('db_type', 'string', 'The diagram output type.', enum: ['mysql', 'postgresql', 'sqlite', 'oracle', 'sqlserver', 'msaccess', 'ontology'])]
    #[ResponseField('schema', 'object', 'The diagram schema (tables, columns, relations).')]
    #[ResponseField('share_token', 'string', 'Token used to share the diagram publicly.')]
    #[ResponseField('share_access', 'string', 'The share access level.')]
    #[ResponseField('require_approval', 'boolean', 'Whether viewer access requires approval.')]
    #[ResponseField('library', 'boolean', 'Whether the diagram is in the public library.')]
    #[ResponseField('is_owner', 'boolean', 'Whether the authenticated user owns this diagram.')]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'db_type' => $this->resource->db_type ?? 'mysql',
            'schema' => DiagramSchema::withoutRuntimeState($this->resource->schema),
            'value_types' => $this->resource->value_types ?? [],
            'import_warnings' => $this->resource->import_warnings ?? [],
            'share_token' => $this->resource->share_token,
            'share_access' => $this->resource->share_access?->value,
            'require_approval' => (bool) $this->resource->require_approval,
            'library' => (bool) $this->resource->library,
            'is_owner' => $request->user()?->id === $this->resource->user_id,
        ];
    }
}
