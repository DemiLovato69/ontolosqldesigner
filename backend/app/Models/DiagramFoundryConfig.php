<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $diagram_id
 * @property string|null $host_url
 * @property string|null $default_project_rid
 * @property string|null $default_folder_rid
 * @property string|null $default_ontology_rid
 * @property array<string, mixed>|null $settings
 */
class DiagramFoundryConfig extends Model
{
    /** @use HasFactory<\Database\Factories\DiagramFoundryConfigFactory> */
    use HasFactory;

    protected $fillable = [
        'diagram_id',
        'host_url',
        'default_project_rid',
        'default_folder_rid',
        'default_ontology_rid',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    /** @return BelongsTo<Diagram, $this> */
    public function diagram(): BelongsTo
    {
        return $this->belongsTo(Diagram::class);
    }

    public function hasHost(): bool
    {
        return is_string($this->host_url) && $this->host_url !== '';
    }
}
