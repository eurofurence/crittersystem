<?php

declare(strict_types=1);

namespace Engelsystem\Models;

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
 * @property int                    $angel_type_id
 * @property int                    $certification_id
 * @property Carbon                 $created_at
 * @property Carbon                 $updated_at
 *
 * @property-read AngelType         $angelType
 * @property-read Certification     $certification
 *
 * @method static QueryBuilder|CertificationAngelType[] whereId($value)
 * @method static QueryBuilder|CertificationAngelType[] whereAngelTypeId($value)
 * @method static QueryBuilder|CertificationAngelType[] whereCertificationId($value)
 * @method static QueryBuilder|CertificationAngelType[] whereCreatedAt($value)
 * @method static QueryBuilder|CertificationAngelType[] whereUpdatedAt($value)
 */
class CertificationAngelType extends Pivot
{
    use HasFactory;

    /** @var bool Increment the IDs */
    public $incrementing = true; // phpcs:ignore

    /** @var bool Enable timestamps for this feature as per PRD */
    public $timestamps = true; // phpcs:ignore

    /** @var string The table associated with the model */
    protected $table = 'certifications_angel_type'; // phpcs:ignore

    /** @var array<string> */
    protected $fillable = [ // phpcs:ignore
        'angel_type_id',
        'certification_id',
    ];

    /** @var array<string, string> */
    protected $casts = [ // phpcs:ignore
        'angel_type_id' => 'integer',
        'certification_id' => 'integer',
    ];

    /**
     * Returns a list of attributes that can be requested for this pivot table
     *
     * @return string[]
     */
    public static function getPivotAttributes(): array
    {
        return ['id'];
    }

    /**
     * Get the angel type that requires this certification.
     */
    public function angelType(): BelongsTo
    {
        return $this->belongsTo(AngelType::class);
    }

    /**
     * Get the certification that is required.
     */
    public function certification(): BelongsTo
    {
        return $this->belongsTo(Certification::class);
    }

    /**
     * Scope to filter by angel type.
     */
    public function scopeForAngelType(Builder $query, int $angelTypeId): Builder
    {
        return $query->where('angel_type_id', $angelTypeId);
    }

    /**
     * Scope to filter by certification.
     */
    public function scopeForCertification(Builder $query, int $certificationId): Builder
    {
        return $query->where('certification_id', $certificationId);
    }

    /**
     * Scope to include only active certifications.
     */
    public function scopeWithActiveCertifications(Builder $query): Builder
    {
        return $query->whereHas('certification', function ($q): void {
            $q->where('is_active', true);
        });
    }

    /**
     * Scope to include only active angel types.
     */
    public function scopeWithActiveAngelTypes(Builder $query): Builder
    {
        return $query->whereHas('angelType');
    }
}
