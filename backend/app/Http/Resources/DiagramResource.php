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
    #[ResponseField('schema', 'object', 'The diagram schema (tables, columns, relations). Visual-only reference tables, reference links, and pipeline transforms are marked with data.exportable=false; table nodes may carry editsEnabled and editsHistory.')]
    #[ResponseField('value_types', 'object[]', 'Ontology value type definitions. Enum value types use a string base type with a oneOf constraint. Empty for non-ontology diagrams.')]
    #[ResponseField('interfaces', 'object[]', 'Ontology interface definitions.')]
    #[ResponseField('interface_link_constraints', 'object[]', 'Ontology interface link constraint definitions.')]
    #[ResponseField('custom_actions', 'object[]', 'Ontology custom action definitions.')]
    #[ResponseField('shared_property_types', 'object[]', 'Ontology shared property type definitions.')]
    #[ResponseField('import_warnings', 'string[]', 'Warnings produced by the most recent import, if any.')]
    #[ResponseField('share_token', 'string', 'Token used to share the diagram publicly.')]
    #[ResponseField('share_access', 'string', 'The share access level.')]
    #[ResponseField('require_approval', 'boolean', 'Whether viewer access requires approval.')]
    #[ResponseField('library', 'boolean', 'Whether the diagram appears in Company Wide Diagrams.')]
    #[ResponseField('is_owner', 'boolean', 'Whether the authenticated user owns this diagram.')]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'db_type' => $this->resource->db_type ?? 'mysql',
            'schema' => DiagramSchema::withoutRuntimeState($this->resource->schema),
            'value_types' => $this->resource->value_types ?? [],
            'interfaces' => $this->resource->interfaces ?? [],
            'interface_link_constraints' => $this->resource->interface_link_constraints ?? [],
            'custom_actions' => $this->resource->custom_actions ?? [],
            'shared_property_types' => $this->resource->shared_property_types ?? [],
            'import_warnings' => $this->resource->import_warnings ?? [],
            'share_token' => $this->resource->share_token,
            'share_access' => $this->resource->share_access?->value,
            'require_approval' => (bool) $this->resource->require_approval,
            'library' => (bool) $this->resource->library,
            'is_owner' => $request->user()?->id === $this->resource->user_id,
        ];
    }
}
