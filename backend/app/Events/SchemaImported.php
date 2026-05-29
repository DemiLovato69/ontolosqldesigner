<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SchemaImported implements ShouldBroadcastNow // Don't believe the IDE, every method here is used
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $shareToken,
        public readonly string $schema,
        public readonly string $importedBy,
    ) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel('diagram.'.$this->shareToken)];
    }

    public function broadcastAs(): string
    {
        return 'schema.imported';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'imported_by' => $this->importedBy,
        ];
    }
}
