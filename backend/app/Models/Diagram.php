<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DbType;
use App\Enums\ExportStatus;
use App\Enums\ImportStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Diagram extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'db_type',
        'schema',
        'script',
        'user_id',
        'share_token',
        'share_access',
        'require_approval',
        'library',
        'featured',
        'featured_url',
        'import_status',
        'import_error',
        'export_status',
        'export_error',
        'export_json',
    ];

    protected $casts = [
        'import_status' => ImportStatus::class,
        'export_status' => ExportStatus::class,
        'db_type' => DbType::class,
        'schema' => 'array',
        'script' => 'array',
        'export_json' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Diagram $diagram) {
            if (empty($diagram->share_token)) {
                $diagram->share_token = Str::uuid()->toString();
            }
        });
    }

    public function scopeShared(Builder $query): Builder
    {
        return $query->whereNotNull('share_access');
    }

    public function scopeLibrary(Builder $query): Builder
    {
        return $query->where('library', true);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('featured', true);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function visitors(): HasMany
    {
        return $this->hasMany(DiagramVisitor::class);
    }
}
