<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $host_url
 * @property string $auth_type
 * @property string|null $client_id
 * @property string|null $foundry_user_id
 * @property string|null $display_name
 * @property array<int, string>|null $scopes
 * @property string|null $access_token
 * @property string|null $refresh_token
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $last_used_at
 * @property \Illuminate\Support\Carbon|null $revoked_at
 */
class FoundryConnection extends Model
{
    /** @use HasFactory<\Database\Factories\FoundryConnectionFactory> */
    use HasFactory;

    /** Refresh the access token when it expires within this many seconds. */
    private const REFRESH_SKEW_SECONDS = 60;

    public const AUTH_OAUTH = 'oauth';

    public const AUTH_TOKEN = 'token';

    protected $fillable = [
        'user_id',
        'host_url',
        'auth_type',
        'client_id',
        'foundry_user_id',
        'display_name',
        'scopes',
        'access_token',
        'refresh_token',
        'expires_at',
        'last_used_at',
        'revoked_at',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected $casts = [
        'scopes' => 'array',
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null
            && $this->expires_at->subSeconds(self::REFRESH_SKEW_SECONDS)->isPast();
    }

    public function isActive(): bool
    {
        return ! $this->isRevoked() && $this->access_token !== null && ! $this->isExpired();
    }

    public function canRefresh(): bool
    {
        return ! $this->isRevoked() && $this->refresh_token !== null;
    }

    public function isTokenAuth(): bool
    {
        return $this->auth_type === self::AUTH_TOKEN;
    }
}
