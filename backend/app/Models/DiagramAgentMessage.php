<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One turn in a diagram agent session. Prompt/response/patch bodies are
 * encrypted at rest; usage and warnings stay queryable.
 *
 * @property int $id
 * @property int $session_id
 * @property int $diagram_id
 * @property int|null $user_id
 * @property string $role
 * @property string|null $model
 * @property string|null $prompt
 * @property string|null $response
 * @property array<string, mixed>|null $patch
 * @property array<int, mixed>|null $warnings
 * @property array<string, mixed>|null $context_summary
 * @property array<string, mixed>|null $usage
 * @property string $status
 * @property string|null $error_code
 * @property string|null $error_message
 * @property \Illuminate\Support\Carbon|null $applied_at
 * @property int|null $applied_by_user_id
 */
class DiagramAgentMessage extends Model
{
    /** @use HasFactory<\Database\Factories\DiagramAgentMessageFactory> */
    use HasFactory;

    public const ROLE_USER = 'user';

    public const ROLE_ASSISTANT = 'assistant';

    public const ROLE_SYSTEM = 'system';

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'session_id',
        'diagram_id',
        'user_id',
        'role',
        'model',
        'prompt',
        'response',
        'patch',
        'warnings',
        'context_summary',
        'usage',
        'status',
        'error_code',
        'error_message',
        'applied_at',
        'applied_by_user_id',
    ];

    protected $hidden = [
        'response',
    ];

    protected $casts = [
        'prompt' => 'encrypted',
        'response' => 'encrypted',
        'patch' => 'encrypted:array',
        'warnings' => 'array',
        'context_summary' => 'array',
        'usage' => 'array',
        'applied_at' => 'datetime',
    ];

    public function isApplied(): bool
    {
        return $this->applied_at !== null;
    }

    /** @return BelongsTo<DiagramAgentSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(DiagramAgentSession::class, 'session_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
}
