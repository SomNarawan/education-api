<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * One portal login. The JWT carries only its id (claim "sid"); the portal
 * tokens stay here, encrypted with APP_KEY — changing APP_KEY ends every
 * session.
 */
class AuthSession extends Model
{
    use HasUuids;

    protected $table = 'auth_sessions';

    protected $fillable = [
        'nontri_id',
        'roles',
        'intranet_token',
        'sso_token',
        'expires_at',
        'checked_at',
    ];

    protected $hidden = [
        'intranet_token',
        'sso_token',
    ];

    protected $casts = [
        'roles' => 'array',
        'intranet_token' => 'encrypted',
        'sso_token' => 'encrypted',
        'expires_at' => 'datetime',
        'checked_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function isActive(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }

    public function revoke(string $reason): void
    {
        if ($this->revoked_at !== null) {
            return;
        }

        $this->forceFill([
            'revoked_at' => now(),
            'revoke_reason' => $reason,
        ])->save();
    }
}
