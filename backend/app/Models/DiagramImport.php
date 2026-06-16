<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DiagramImportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiagramImport extends Model
{
    /** @use HasFactory<DiagramImportFactory> */
    use HasFactory;

    public const STATUS_UPLOADING = 'uploading';

    public const STATUS_UPLOADED = 'uploaded';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'diagram_id',
        'user_id',
        'format',
        'status',
        'disk',
        'directory',
        'path',
        'original_name',
        'size',
        'chunk_size',
        'chunks_total',
        'chunks_received',
        'error',
    ];

    protected $casts = [
        'chunks_received' => 'array',
        'size' => 'integer',
        'chunk_size' => 'integer',
        'chunks_total' => 'integer',
    ];

    /** @return BelongsTo<Diagram, $this> */
    public function diagram(): BelongsTo
    {
        return $this->belongsTo(Diagram::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
