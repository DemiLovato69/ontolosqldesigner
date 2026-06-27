<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DiagramAgentSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property DiagramAgentSession $resource */
class DiagramAgentSessionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'model' => $this->resource->model,
            'status' => $this->resource->status,
            'archived' => $this->resource->isArchived(),
            'created_by' => $this->whenLoaded('creator', fn () => $this->resource->creator ? [
                'id' => $this->resource->creator->id,
                'name' => $this->resource->creator->name,
            ] : null),
            'last_message_at' => $this->resource->last_message_at?->toIso8601String(),
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'messages' => DiagramAgentMessageResource::collection(
                $this->whenLoaded('messages')
            ),
        ];
    }
}
