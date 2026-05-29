<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DiagramAccess;
use App\Enums\VisitorStatus;
use Database\Factories\DiagramVisitorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiagramVisitor extends Model
{
    /** @use HasFactory<DiagramVisitorFactory> */
    use HasFactory;

    protected $fillable = ['diagram_id', 'user_id', 'status', 'access'];

    protected $casts = [
        'status' => VisitorStatus::class,
        'access' => DiagramAccess::class,
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
