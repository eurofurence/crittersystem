<?php

declare(strict_types=1);

namespace Engelsystem\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int                    $id
 * @property int                    $certification_id
 * @property string                 $token
 * @property Carbon                 $expires_at
 * @property Carbon                 $created_at
 * @property Carbon                 $updated_at
 *
 * @property-read Certification     $certification
 *
 * @method static Builder|CertificationToken whereId($value)
 * @method static Builder|CertificationToken whereCertificationId($value)
 * @method static Builder|CertificationToken whereToken($value)
 * @method static Builder|CertificationToken whereExpiresAt($value)
 * @method static Builder|CertificationToken whereCreatedAt($value)
 * @method static Builder|CertificationToken whereUpdatedAt($value)
 */
class CertificationToken extends BaseModel
{
    use HasFactory;

    /** @var string The table associated with the model */
    protected $table = 'certification_tokens'; // phpcs:ignore

    /** @var string[] */
    protected $fillable = [ // phpcs:ignore
        'certification_id',
        'token',
        'expires_at',
    ];

    /** @var array<string, string> */
    protected $casts = [ // phpcs:ignore
        'certification_id' => 'integer',
        'expires_at' => 'datetime',
    ];

    public function certification(): BelongsTo
    {
        return $this->belongsTo(Certification::class);
    }

    /**
     * Generate a cryptographically secure token.
     */
    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Find an active (non-expired) token.
     */
    public static function findActiveToken(string $token): ?self
    {
        return static::where('token', $token)
            ->where('expires_at', '>', Carbon::now())
            ->first();
    }

    /**
     * Clean up expired tokens.
     */
    public static function cleanupExpiredTokens(): int
    {
        return static::where('expires_at', '<=', Carbon::now())->delete();
    }

    /**
     * Check if the token is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Scope to filter only active (non-expired) tokens.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('expires_at', '>', Carbon::now());
    }

    /**
     * Scope to filter only expired tokens.
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<=', Carbon::now());
    }
}
