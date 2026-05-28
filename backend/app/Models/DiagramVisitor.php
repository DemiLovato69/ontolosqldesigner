<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DiagramAccess;
use App\Enums\VisitorStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiagramVisitor extends Model
{
    use HasFactory;

    protected $fillable = ['diagram_id', 'user_id', 'status', 'access'];

    protected $casts = [
        'status' => VisitorStatus::class,
        'access' => DiagramAccess::class,
    ];

    public function diagram(): BelongsTo
    {
        return $this->belongsTo(Diagram::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
