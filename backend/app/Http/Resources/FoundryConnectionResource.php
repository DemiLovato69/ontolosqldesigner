<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\FoundryConnection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property FoundryConnection $resource */
class FoundryConnectionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'host_url' => $this->resource->host_url,
            'auth_type' => $this->resource->auth_type,
            'display_name' => $this->resource->display_name,
            'scopes' => $this->resource->scopes ?? [],
            'active' => $this->resource->isActive(),
            'revoked' => $this->resource->isRevoked(),
            'expires_at' => $this->resource->expires_at?->toIso8601String(),
            'last_used_at' => $this->resource->last_used_at?->toIso8601String(),
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
