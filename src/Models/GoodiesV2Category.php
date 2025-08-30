<?php

declare(strict_types=1);

namespace Engelsystem\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @property int                          $id
 * @property string                       $uuid
 * @property string                       $name
 * @property string|null                  $description
 * @property bool                         $is_active
 * @property int                          $display_order
 * @property \Carbon\Carbon               $created_at
 * @property \Carbon\Carbon               $updated_at
 *
 * @property-read Collection|GoodiesV2Item[]  $items
 * @property-read int                     $items_count
 *
 * @method static Builder|GoodiesV2Category whereId($value)
 * @method static Builder|GoodiesV2Category whereUuid($value)
 * @method static Builder|GoodiesV2Category whereName($value)
 * @method static Builder|GoodiesV2Category whereDescription($value)
 * @method static Builder|GoodiesV2Category whereIsActive($value)
 * @method static Builder|GoodiesV2Category whereDisplayOrder($value)
 * @method static Builder|GoodiesV2Category whereCreatedAt($value)
 * @method static Builder|GoodiesV2Category whereUpdatedAt($value)
 */
class GoodiesV2Category extends BaseModel
{
    use HasFactory;
    use HasUuids;

    protected $keyType = 'integer'; // phpcs:ignore
    public $incrementing = true; // phpcs:ignore
    public $timestamps = true; // phpcs:ignore

    protected $table = 'goodiesv2_categories'; // phpcs:ignore

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
        'name',
        'description',
        'is_active',
        'sort_order', // Temporarily use sort_order until migration is run
        'display_order',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [ // phpcs:ignore
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
     * Get all items that belong to this category.
     */
    public function items(): HasMany
    {
        return $this->hasMany(GoodiesV2Item::class, 'category_id');
    }

    /**
     * Get all active items that belong to this category.
     */
    public function activeItems(): HasMany
    {
        return $this->hasMany(GoodiesV2Item::class, 'category_id')
            ->where('is_active', true);
    }

    /**
     * Check if this category is currently active.
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Get the display order for this category.
     */
    public function getDisplayOrder(): int
    {
        return $this->display_order;
    }

    /**
     * Scope to filter only active categories.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order categories by their display order.
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
     * Scope to filter categories that have active items.
     */
    public function scopeWithActiveItems(Builder $query): Builder
    {
        return $query->whereHas('items', function ($q): void {
            $q->where('is_active', true);
        });
    }

    /**
     * Scope to include item counts.
     */
    public function scopeWithItemCounts(Builder $query): Builder
    {
        return $query->withCount(['items', 'activeItems']);
    }
}
