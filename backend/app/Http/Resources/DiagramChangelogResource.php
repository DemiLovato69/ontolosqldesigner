<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DiagramChangelog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;

/** @property DiagramChangelog $resource */
class DiagramChangelogResource extends JsonResource
{
    /** @return array<string, mixed> */
    #[ResponseField('id', 'int', 'The changelog entry ID.')]
    #[ResponseField('diagram_id', 'int', 'The associated diagram ID.')]
    #[ResponseField('user_id', 'int', 'The ID of the user who made the change.')]
    #[ResponseField('user_name', 'string', 'The email of the user who made the change.')]
    #[ResponseField('action', 'string', 'The action performed (e.g. table_added, column_removed).')]
    #[ResponseField('details', 'object', 'Additional details about the action.', required: false)]
    #[ResponseField('created_at', 'string', 'ISO 8601 timestamp of when the change was recorded.')]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'diagram_id' => $this->resource->diagram_id,
            'user_id' => $this->resource->user_id,
            'user_name' => $this->resource->user_name,
            'action' => $this->resource->action,
            'details' => $this->resource->details,
            'created_at' => $this->resource->created_at,
        ];
    }
}
