<?php

declare(strict_types=1);

namespace Engelsystem\Models;

use Engelsystem\Models\User\User;
use Engelsystem\Models\User\UsesUserModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

/**
 * @property int                    $id
 * @property int                    $user_id
 * @property int                    $item_id
 * @property int                    $quantity
 * @property int                    $hours_at_distribution
 * @property int                    $distributed_by
 * @property Carbon                 $distributed_at
 * @property string|null            $notes
 * @property Carbon                 $created_at
 * @property Carbon                 $updated_at
 *
 * @property-read User              $user
 * @property-read GoodiesV2Item     $item
 * @property-read User              $distributedBy
 *
 * @method static Builder|GoodiesV2Distribution whereId($value)
 * @method static Builder|GoodiesV2Distribution whereUserId($value)
 * @method static Builder|GoodiesV2Distribution whereItemId($value)
 * @method static Builder|GoodiesV2Distribution whereHoursAtDistribution($value)
 * @method static Builder|GoodiesV2Distribution whereDistributedBy($value)
 * @method static Builder|GoodiesV2Distribution whereNotes($value)
 * @method static Builder|GoodiesV2Distribution whereCreatedAt($value)
 * @method static Builder|GoodiesV2Distribution whereUpdatedAt($value)
 */
class GoodiesV2Distribution extends BaseModel
{
    use HasFactory;
    use UsesUserModel;

    protected $keyType = 'integer'; // phpcs:ignore
    public $incrementing = true; // phpcs:ignore
    public $timestamps = true; // phpcs:ignore

    protected $table = 'goodiesv2_distributions'; // phpcs:ignore

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [ // phpcs:ignore
        'user_id',
        'item_id',
        'quantity',
        'hours_at_distribution',
        'distributed_by',
        'distributed_at',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [ // phpcs:ignore
        'user_id' => 'integer',
        'item_id' => 'integer',
        'quantity' => 'integer',
        'hours_at_distribution' => 'integer',
        'distributed_by' => 'integer',
        'distributed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user who received this goodie.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the item that was distributed.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(GoodiesV2Item::class);
    }

    /**
     * Get the user who distributed this goodie.
     */
    public function distributedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'distributed_by');
    }

    /**
     * Get the user who distributed this goodie.
     */
    public function getDistributedBy(): ?User
    {
        return $this->distributedBy;
    }

    /**
     * Get the hours the user had at the time of distribution.
     */
    public function getHoursAtDistribution(): int
    {
        return $this->hours_at_distribution;
    }

    /**
     * Check if this distribution was made recently (within last 24 hours).
     */
    public function isRecent(): bool
    {
        return $this->created_at && $this->created_at->isAfter(Carbon::now()->subDay());
    }

    /**
     * Get formatted distribution info for display.
     */
    public function getDistributionInfo(): string
    {
        $itemName = $this->item ? $this->item->name : 'Unknown Item';
        $userName = $this->user ? $this->user->displayName : 'Unknown User';
        $distributorName = $this->distributedBy ? $this->distributedBy->displayName : 'Unknown Distributor';

        return sprintf(
            '%s distributed to %s by %s (%d hours)',
            $itemName,
            $userName,
            $distributorName,
            $this->hours_at_distribution
        );
    }

    /**
     * Scope to filter distributions by user.
     */
    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter distributions by item.
     */
    public function scopeByItem(Builder $query, int $itemId): Builder
    {
        return $query->where('item_id', $itemId);
    }

    /**
     * Scope to filter distributions by distributor.
     */
    public function scopeByDistributor(Builder $query, int $distributorId): Builder
    {
        return $query->where('distributed_by', $distributorId);
    }

    /**
     * Scope to filter recent distributions (within specified days).
     */
    public function scopeRecent(Builder $query, int $days = 1): Builder
    {
        return $query->where('created_at', '>=', Carbon::now()->subDays($days));
    }

    /**
     * Scope to filter distributions within a date range.
     */
    public function scopeInDateRange(Builder $query, Carbon $startDate, Carbon $endDate): Builder
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope to filter distributions by minimum hours threshold.
     */
    public function scopeWithMinimumHours(Builder $query, int $minHours): Builder
    {
        return $query->where('hours_at_distribution', '>=', $minHours);
    }

    /**
     * Scope to order distributions by most recent first.
     */
    public function scopeLatest(Builder $query): Builder
    {
        return $query->orderByDesc('created_at');
    }

    /**
     * Scope to include related models for efficient loading.
     */
    public function scopeWithDetails(Builder $query): Builder
    {
        return $query->with(['user', 'item.category', 'distributedBy']);
    }
}
