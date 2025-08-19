<?php

declare(strict_types=1);

namespace Engelsystem\Models;

use Engelsystem\Models\User\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @property int                          $id
 * @property string                       $uuid
 * @property string                       $title
 * @property string                       $description
 * @property string|null                  $contact_person
 * @property string|null                  $contact_email
 * @property string|null                  $location
 * @property bool                         $is_perpetual
 * @property int|null                     $validity_period_days
 * @property bool                         $allow_self_confirmation
 * @property bool                         $is_active
 * @property \Carbon\Carbon               $created_at
 * @property \Carbon\Carbon               $updated_at
 *
 * @property-read Collection|User[]       $users
 * @property-read Collection|AngelType[]  $angelTypes
 *
 * @method static Builder|Certification whereId($value)
 * @method static Builder|Certification whereUuid($value)
 * @method static Builder|Certification whereTitle($value)
 * @method static Builder|Certification whereDescription($value)
 * @method static Builder|Certification whereContactPerson($value)
 * @method static Builder|Certification whereContactEmail($value)
 * @method static Builder|Certification whereLocation($value)
 * @method static Builder|Certification whereIsPerpetual($value)
 * @method static Builder|Certification whereValidityPeriodDays($value)
 * @method static Builder|Certification whereAllowSelfConfirmation($value)
 * @method static Builder|Certification whereIsActive($value)
 * @method static Builder|Certification whereCreatedAt($value)
 * @method static Builder|Certification whereUpdatedAt($value)
 */
class Certification extends BaseModel
{
    use HasFactory;
    use HasUuids;

    protected $keyType = 'integer'; // phpcs:ignore
    public $incrementing = true; // phpcs:ignore
    public $timestamps = true; // phpcs:ignore

    protected $table = 'certifications'; // phpcs:ignore

    /**
     * Get the columns that should receive a unique identifier.
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [ // phpcs:ignore
        'title',
        'description',
        'contact_person',
        'contact_email',
        'location',
        'is_perpetual',
        'validity_period_days',
        'allow_self_confirmation',
        'staff_only',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [ // phpcs:ignore
        'is_perpetual' => 'boolean',
        'allow_self_confirmation' => 'boolean',
        'staff_only' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get the route key name for Laravel.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Get all users that have this certification.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'certifications_user')
            ->using(CertificationUser::class)
            ->withPivot(['status', 'date_certified', 'date_expires', 'certified_by', 'notes'])
            ->withTimestamps();
    }

    /**
     * Get all angel types that require this certification.
     */
    public function angelTypes(): BelongsToMany
    {
        return $this->belongsToMany(AngelType::class, 'certifications_angel_type')
            ->using(CertificationAngelType::class)
            ->withTimestamps();
    }

    /**
     * Check if this certification is currently active.
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Check if this certification is perpetual (never expires).
     */
    public function isPerpetual(): bool
    {
        return $this->is_perpetual;
    }

    /**
     * Check if this certification allows self-confirmation.
     */
    public function allowsSelfConfirmation(): bool
    {
        return $this->allow_self_confirmation;
    }

    /**
     * Get the validity period in days, or null if perpetual.
     */
    public function getValidityPeriodDays(): ?int
    {
        return $this->validity_period_days;
    }

    /**
     * Scope to filter only active certifications.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter only perpetual certifications.
     */
    public function scopePerpetual(Builder $query): Builder
    {
        return $query->where('is_perpetual', true);
    }

    /**
     * Scope to filter only time-limited certifications.
     */
    public function scopeTimeLimited(Builder $query): Builder
    {
        return $query->where('is_perpetual', false);
    }

    /**
     * Scope to filter only self-confirmable certifications.
     */
    public function scopeSelfConfirmable(Builder $query): Builder
    {
        return $query->where('allow_self_confirmation', true);
    }
}
