<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DiagramAgentMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property DiagramAgentMessage $resource */
class DiagramAgentMessageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'session_id' => $this->resource->session_id,
            'role' => $this->resource->role,
            'model' => $this->resource->model,
            'prompt' => $this->resource->prompt,
            'message' => $this->resource->response,
            'patch' => $this->resource->patch,
            'warnings' => $this->resource->warnings ?? [],
            'usage' => $this->resource->usage,
            'context_summary' => $this->resource->context_summary,
            'status' => $this->resource->status,
            'applied' => $this->resource->isApplied(),
            'applied_at' => $this->resource->applied_at?->toIso8601String(),
            'error_code' => $this->resource->error_code,
            'error_message' => $this->resource->error_message,
            'user' => $this->whenLoaded('user', fn () => $this->resource->user ? [
                'id' => $this->resource->user->id,
                'name' => $this->resource->user->name,
            ] : null),
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
