<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\FoundryLlmModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property FoundryLlmModel $resource */
class FoundryLlmModelResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'model' => $this->resource->model,
            'provider' => $this->resource->provider,
            'display_name' => $this->resource->label(),
            'description' => $this->resource->description,
            'is_default' => $this->resource->is_default,
        ];
    }
}
