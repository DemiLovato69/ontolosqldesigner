<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A diagram agent conversation, shared across diagram collaborators. Sessions
 * are archived (not deleted) to keep the prompt/response audit trail intact.
 *
 * @property int $id
 * @property int $diagram_id
 * @property int|null $created_by_user_id
 * @property string|null $foundry_host_url
 * @property string|null $title
 * @property string|null $model
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $last_message_at
 * @property \Illuminate\Support\Carbon|null $archived_at
 * @property int|null $archived_by_user_id
 */
class DiagramAgentSession extends Model
{
    /** @use HasFactory<\Database\Factories\DiagramAgentSessionFactory> */
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'diagram_id',
        'created_by_user_id',
        'foundry_host_url',
        'title',
        'model',
        'status',
        'last_message_at',
        'archived_at',
        'archived_by_user_id',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /** @return BelongsTo<Diagram, $this> */
    public function diagram(): BelongsTo
    {
        return $this->belongsTo(Diagram::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return HasMany<DiagramAgentMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(DiagramAgentMessage::class, 'session_id');
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }
}
