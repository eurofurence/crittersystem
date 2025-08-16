<?php
// phpcs:ignoreFile

declare(strict_types=1);

namespace Engelsystem\Models;

use Engelsystem\Models\User\User;
use Engelsystem\Models\User\UsesUserModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Carbon\Carbon;

/**
 * @mixin Builder
 *
 * @property int                    $id
 * @property int                    $user_id
 * @property int                    $certification_id
 * @property string                 $status
 * @property Carbon|null            $date_certified
 * @property Carbon|null            $date_expires
 * @property int|null               $certified_by
 * @property string|null            $notes
 * @property Carbon                 $created_at
 * @property Carbon                 $updated_at
 *
 * @property-read User              $user
 * @property-read Certification     $certification
 * @property-read bool              $isExpired
 * @property-read bool              $isValid
 * @property-read bool              $isSelfConfirmed
 * @property-read bool              $isApproved
 *
 * @method static QueryBuilder|CertificationUser[] whereId($value)
 * @method static QueryBuilder|CertificationUser[] whereUserId($value)
 * @method static QueryBuilder|CertificationUser[] whereCertificationId($value)
 * @method static QueryBuilder|CertificationUser[] whereStatus($value)
 * @method static QueryBuilder|CertificationUser[] whereDateCompleted($value)
 * @method static QueryBuilder|CertificationUser[] whereDateExpires($value)
 * @method static QueryBuilder|CertificationUser[] whereNotes($value)
 * @method static QueryBuilder|CertificationUser[] whereCreatedAt($value)
 * @method static QueryBuilder|CertificationUser[] whereUpdatedAt($value)
 */
class CertificationUser extends Pivot
{
    use HasFactory;
    use UsesUserModel;

    /** @var bool Increment the IDs */
    public $incrementing = true; // phpcs:ignore

    /** @var bool Enable timestamps for this feature as per PRD */
    public $timestamps = true; // phpcs:ignore

    /** @var string The table associated with the model */
    protected $table = 'certifications_user'; // phpcs:ignore

    /** @var array<string> Status enumeration values */
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_SELF_CONFIRMED = 'self_confirmed';
    public const STATUS_REVOKED = 'revoked';
    public const STATUS_EXPIRED = 'expired';

    /** @var array<string> Valid status values */
    public const VALID_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_SELF_CONFIRMED,
        self::STATUS_REVOKED,
        self::STATUS_EXPIRED,
    ];

    /** @var array<string, mixed> Default attributes */
    protected $attributes = [ // phpcs:ignore
        'status' => self::STATUS_PENDING,
    ];

    /** @var array<string> */
    protected $fillable = [ // phpcs:ignore
        'user_id',
        'certification_id',
        'status',
        'date_certified',
        'date_expires',
        'certified_by',
        'notes',
    ];

    /** @var array<string, string> */
    protected $casts = [ // phpcs:ignore
        'user_id' => 'integer',
        'certification_id' => 'integer',
        'date_certified' => 'datetime',
        'date_expires' => 'datetime',
        'certified_by' => 'integer',
    ];

    /**
     * Returns a list of attributes that can be requested for this pivot table
     *
     * @return string[]
     */
    public static function getPivotAttributes(): array
    {
        return ['id', 'status', 'date_certified', 'date_expires', 'certified_by', 'notes'];
    }

    /**
     * Get the user that owns this certification.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the certification this record refers to.
     */
    public function certification(): BelongsTo
    {
        return $this->belongsTo(Certification::class);
    }

    /**
     * Check if this certification has expired.
     */
    public function getIsExpiredAttribute(): bool
    {
        if (!$this->date_expires) {
            return false; // Perpetual certifications never expire
        }

        return $this->date_expires->isPast();
    }

    /**
     * Check if this certification is currently valid.
     */
    public function getIsValidAttribute(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_SELF_CONFIRMED])
            && !$this->isExpired;
    }

    /**
     * Check if this certification was self-confirmed.
     */
    public function getIsSelfConfirmedAttribute(): bool
    {
        return $this->status === self::STATUS_SELF_CONFIRMED;
    }

    /**
     * Check if this certification was approved by an admin.
     */
    public function getIsApprovedAttribute(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Calculate the expiry date based on completion date and certification validity period.
     */
    public function calculateExpiryDate(): ?Carbon
    {
        if (!$this->certification || $this->certification->isPerpetual()) {
            return null; // Perpetual certifications never expire
        }

        if (!$this->date_certified || !$this->certification->getValidityPeriodDays()) {
            return null; // No completion date or validity period
        }

        return $this->date_certified->copy()->addDays($this->certification->getValidityPeriodDays());
    }

    /**
     * Update the expiry date based on the current completion date and certification settings.
     */
    public function updateExpiryDate(): void
    {
        $this->date_expires = $this->calculateExpiryDate();
    }

    /**
     * Scope to filter only valid certifications.
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_APPROVED, self::STATUS_SELF_CONFIRMED])
            ->where(function ($q): void {
                $q->whereNull('date_expires')
                  ->orWhere('date_expires', '>', Carbon::now());
            });
    }

    /**
     * Scope to filter only expired certifications.
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('date_expires')
            ->where('date_expires', '<=', Carbon::now());
    }

    /**
     * Scope to filter by status.
     */
    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter pending certifications.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->withStatus(self::STATUS_PENDING);
    }

    /**
     * Scope to filter approved certifications.
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->withStatus(self::STATUS_APPROVED);
    }

    /**
     * Scope to filter self-confirmed certifications.
     */
    public function scopeSelfConfirmed(Builder $query): Builder
    {
        return $query->withStatus(self::STATUS_SELF_CONFIRMED);
    }

    /**
     * Scope to filter revoked certifications.
     */
    public function scopeRevoked(Builder $query): Builder
    {
        return $query->withStatus(self::STATUS_REVOKED);
    }

    /**
     * Scope to filter certifications expiring within a given number of days.
     */
    public function scopeExpiringWithin(Builder $query, int $days): Builder
    {
        $expiryThreshold = Carbon::now()->addDays($days);

        return $query->whereNotNull('date_expires')
            ->where('date_expires', '<=', $expiryThreshold)
            ->where('date_expires', '>', Carbon::now());
    }
}
