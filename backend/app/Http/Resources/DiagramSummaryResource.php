<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Diagram;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property Diagram $resource */
class DiagramSummaryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'db_type' => $this->resource->db_type ?? 'mysql',
            'schema' => [],
            'share_token' => $this->resource->share_token,
            'share_access' => $this->resource->share_access?->value,
            'require_approval' => (bool) $this->resource->require_approval,
            'library' => (bool) $this->resource->library,
            'is_owner' => $request->user()?->id === $this->resource->user_id,
        ];
    }
}
