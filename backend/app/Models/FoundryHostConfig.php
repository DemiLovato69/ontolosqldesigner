<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin-managed Foundry OAuth host configuration. Mirrors the env-based
 * FOUNDRY_HOSTS_JSON map but is editable from the admin dashboard. Secrets are
 * encrypted at rest.
 *
 * @property int $id
 * @property string $host_url
 * @property string|null $display_name
 * @property string $client_id
 * @property string|null $client_secret
 * @property bool $enabled
 */
class FoundryHostConfig extends Model
{
    /** @use HasFactory<\Database\Factories\FoundryHostConfigFactory> */
    use HasFactory;

    protected $fillable = [
        'host_url',
        'display_name',
        'client_id',
        'client_secret',
        'enabled',
    ];

    protected $hidden = [
        'client_secret',
    ];

    protected $casts = [
        'client_secret' => 'encrypted',
        'enabled' => 'boolean',
    ];
}
