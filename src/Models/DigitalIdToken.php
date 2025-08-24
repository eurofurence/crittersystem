<?php

declare(strict_types=1);

namespace Engelsystem\Models;

use Carbon\Carbon;
use Engelsystem\Models\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int         $id
 * @property int         $user_id
 * @property string      $token
 * @property Carbon      $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read User   $user
 */
class DigitalIdToken extends BaseModel
{
    /** @var string[] */
    protected $fillable = [ // phpcs:ignore
        'user_id',
        'token',
        'expires_at',
    ];

    /** @var array<string, string> */
    protected $casts = [ // phpcs:ignore
        'user_id' => 'integer',
        'expires_at' => 'datetime',
    ];

    /** @var string The table associated with the model */
    protected $table = 'digital_id_tokens'; // phpcs:ignore

    /**
     * Get the user that owns the digital ID token
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the token is still valid (not expired)
     */
    public function isValid(): bool
    {
        return $this->expires_at->isAfter(Carbon::now());
    }

    /**
     * Scope query to only active (non-expired) tokens
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('expires_at', '>', Carbon::now());
    }

    /**
     * Scope query to only expired tokens
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<=', Carbon::now());
    }

    /**
     * Clean up expired tokens
     */
    public static function cleanupExpiredTokens(): int
    {
        return static::query()->expired()->delete();
    }

    /**
     * Find active token by token string
     */
    public static function findActiveToken(string $token): ?self
    {
        return static::where('token', $token)->active()->first();
    }

    /**
     * Generate a new cryptographically secure token
     */
    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
