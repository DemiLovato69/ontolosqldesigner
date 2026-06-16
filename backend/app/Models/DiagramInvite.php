<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DiagramAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiagramInvite extends Model
{
    protected $fillable = ['diagram_id', 'email', 'access'];

    protected $casts = [
        'access' => DiagramAccess::class,
    ];

    /** @return BelongsTo<Diagram, $this> */
    public function diagram(): BelongsTo
    {
        return $this->belongsTo(Diagram::class);
    }
}
