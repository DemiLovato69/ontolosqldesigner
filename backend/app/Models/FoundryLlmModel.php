<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin-managed allowlist of Foundry AIP models the diagram agent may call. A
 * null host_url makes the model available on every Foundry host.
 *
 * @property int $id
 * @property string|null $host_url
 * @property string $provider
 * @property string $model
 * @property string|null $display_name
 * @property string|null $description
 * @property bool $enabled
 * @property bool $is_default
 * @property int|null $max_output_tokens
 * @property float|null $temperature
 * @property int $sort_order
 */
class FoundryLlmModel extends Model
{
    /** @use HasFactory<\Database\Factories\FoundryLlmModelFactory> */
    use HasFactory;

    protected $fillable = [
        'host_url',
        'provider',
        'model',
        'display_name',
        'description',
        'enabled',
        'is_default',
        'max_output_tokens',
        'temperature',
        'sort_order',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'is_default' => 'boolean',
        'max_output_tokens' => 'integer',
        'temperature' => 'float',
        'sort_order' => 'integer',
    ];

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    /**
     * Models usable for a host: host-specific entries plus global (null host)
     * entries. Pass a normalized host URL.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForHost(Builder $query, ?string $hostUrl): Builder
    {
        return $query->where(function (Builder $inner) use ($hostUrl): void {
            $inner->whereNull('host_url');
            if ($hostUrl !== null && $hostUrl !== '') {
                $inner->orWhere('host_url', $hostUrl);
            }
        });
    }

    public function label(): string
    {
        return $this->display_name !== null && $this->display_name !== ''
            ? $this->display_name
            : $this->model;
    }
}
