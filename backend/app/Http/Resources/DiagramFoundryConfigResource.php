<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DiagramFoundryConfig;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property DiagramFoundryConfig $resource */
class DiagramFoundryConfigResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'diagram_id' => $this->resource->diagram_id,
            'host_url' => $this->resource->host_url,
            'default_project_rid' => $this->resource->default_project_rid,
            'default_folder_rid' => $this->resource->default_folder_rid,
            'default_ontology_rid' => $this->resource->default_ontology_rid,
            'settings' => $this->resource->settings,
        ];
    }
}
