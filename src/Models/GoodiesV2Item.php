<?php

declare(strict_types=1);

namespace Engelsystem\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @property int                               $id
 * @property string                            $uuid
 * @property int                               $category_id
 * @property string                            $name
 * @property string|null                       $description
 * @property int                               $required_hours
 * @property int|null                          $max_per_person
 * @property bool                              $is_active
 * @property int                               $display_order
 * @property \Carbon\Carbon                    $created_at
 * @property \Carbon\Carbon                    $updated_at
 *
 * @property-read GoodiesV2Category            $category
 * @property-read Collection|GoodiesV2Distribution[]  $distributions
 * @property-read int                          $distributions_count
 * @property-read Collection|Certification[]   $certifications
 * @property-read int                          $certifications_count
 *
 * @method static Builder|GoodiesV2Item whereId($value)
 * @method static Builder|GoodiesV2Item whereUuid($value)
 * @method static Builder|GoodiesV2Item whereCategoryId($value)
 * @method static Builder|GoodiesV2Item whereName($value)
 * @method static Builder|GoodiesV2Item whereDescription($value)
 * @method static Builder|GoodiesV2Item whereRequiredHours($value)
 * @method static Builder|GoodiesV2Item whereMaxPerPerson($value)
 * @method static Builder|GoodiesV2Item whereIsActive($value)
 * @method static Builder|GoodiesV2Item whereDisplayOrder($value)
 * @method static Builder|GoodiesV2Item whereCreatedAt($value)
 * @method static Builder|GoodiesV2Item whereUpdatedAt($value)
 */
class GoodiesV2Item extends BaseModel
{
    use HasFactory;
    use HasUuids;

    protected $keyType = 'integer'; // phpcs:ignore
    public $incrementing = true; // phpcs:ignore
    public $timestamps = true; // phpcs:ignore

    protected $table = 'goodiesv2_items'; // phpcs:ignore

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
        'category_id',
        'name',
        'description',
        'required_hours',
        'max_per_person',
        'is_active',
        'display_order',
        'sort_order', // Temporarily use sort_order until migration is run
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [ // phpcs:ignore
        'category_id' => 'integer',
        'required_hours' => 'integer',
        'max_per_person' => 'integer',
        'is_active' => 'boolean',
        'display_order' => 'integer',
        'sort_order' => 'integer', // Temporarily use sort_order until migration is run
    ];

    /**
     * Get the route key name for Laravel.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Get the category this item belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(GoodiesV2Category::class);
    }

    /**
     * Get all distributions of this item.
     */
    public function distributions(): HasMany
    {
        return $this->hasMany(GoodiesV2Distribution::class, 'item_id');
    }

    /**
     * Get all certifications required for this item.
     */
    public function certifications(): BelongsToMany
    {
        return $this->belongsToMany(Certification::class, 'goodiesv2_items_certifications', 'item_id', 'certification_id') // phpcs:ignore
            ->withTimestamps();
    }

    /**
     * Check if this item is currently active.
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Get the required hours for this item.
     */
    public function getRequiredHours(): int
    {
        return $this->required_hours;
    }

    /**
     * Get the display order for this item.
     */
    public function getDisplayOrder(): int
    {
        return $this->display_order;
    }

    /**
     * Get the maximum quantity per person for this item.
     */
    public function getMaxPerPerson(): ?int
    {
        return $this->max_per_person;
    }

    /**
     * Check if this item has a quantity limit per person.
     */
    public function hasQuantityLimit(): bool
    {
        return $this->max_per_person !== null && $this->max_per_person > 0;
    }

    /**
     * Check if this item requires any certifications.
     */
    public function requiresCertifications(): bool
    {
        return $this->certifications()->count() > 0;
    }

    /**
     * Check if this item is available for distribution (active category and item).
     */
    public function isAvailable(): bool
    {
        return $this->is_active && $this->category && $this->category->is_active;
    }

    /**
     * Scope to filter only active items.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter items by category.
     */
    public function scopeByCategory(Builder $query, int $categoryId): Builder
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Scope to filter items by hours range.
     */
    public function scopeByHoursRange(Builder $query, int $minHours, ?int $maxHours = null): Builder
    {
        $query->where('required_hours', '>=', $minHours);

        if ($maxHours !== null) {
            $query->where('required_hours', '<=', $maxHours);
        }

        return $query;
    }

    /**
     * Scope to filter items that are available (both item and category are active).
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereHas('category', function ($q): void {
                $q->where('is_active', true);
            });
    }

    /**
     * Scope to order items by their display order within categories.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        // Try display_order first (after migration), fallback to sort_order (before migration)
        $columnName = $this->getConnection()->getSchemaBuilder()->hasColumn($this->getTable(), 'display_order')
            ? 'display_order'
            : 'sort_order';
        return $query->orderBy($columnName)->orderBy('name');
    }

    /**
     * Scope to include distribution counts.
     */
    public function scopeWithDistributionCounts(Builder $query): Builder
    {
        return $query->withCount('distributions');
    }

    /**
     * Scope to filter items that require fewer or equal hours than specified.
     */
    public function scopeAffordableFor(Builder $query, int $userHours): Builder
    {
        return $query->where('required_hours', '<=', $userHours);
    }

    /**
     * Scope to include certification counts.
     */
    public function scopeWithCertificationCounts(Builder $query): Builder
    {
        return $query->withCount('certifications');
    }

    /**
     * Scope to filter items that require specific certifications.
     */
    public function scopeWithCertifications(Builder $query, array $certificationIds): Builder
    {
        return $query->whereHas('certifications', function ($q) use ($certificationIds): void {
            $q->whereIn('certifications.id', $certificationIds);
        });
    }

    /**
     * Scope to filter items that don't require any certifications.
     */
    public function scopeWithoutCertifications(Builder $query): Builder
    {
        return $query->whereDoesntHave('certifications');
    }

    /**
     * Scope to filter items with quantity limits.
     */
    public function scopeWithQuantityLimits(Builder $query): Builder
    {
        return $query->whereNotNull('max_per_person')->where('max_per_person', '>', 0);
    }

    /**
     * Scope to filter items without quantity limits.
     */
    public function scopeWithoutQuantityLimits(Builder $query): Builder
    {
        return $query->whereNull('max_per_person');
    }
}
