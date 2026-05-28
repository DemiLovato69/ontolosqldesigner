<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\DiagramAccess;
use App\Models\DiagramVisitor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Knuckles\Scribe\Attributes\ResponseField;

/** @property DiagramVisitor $resource */
class DiagramVisitorResource extends JsonResource
{
    /** @return array<string, mixed> */
    #[ResponseField('id', 'int', 'The visitor record ID.')]
    #[ResponseField('user_id', 'int', "The visitor's user ID.")]
    #[ResponseField('name', 'string', "The visitor's display name, falling back to email.")]
    #[ResponseField('email', 'string', "The visitor's email address.")]
    #[ResponseField('status', 'string', 'Approval status.', enum: ['pending', 'approved'])]
    #[ResponseField('access', 'string', 'Access level granted to this visitor.', enum: DiagramAccess::class)]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'user_id' => $this->resource->user_id,
            'name' => $this->resource->user->email,
            'email' => $this->resource->user->email,
            'status' => $this->resource->status,
            'access' => $this->resource->access,
        ];
    }
}
