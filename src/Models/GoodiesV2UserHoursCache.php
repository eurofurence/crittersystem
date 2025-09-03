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
 * @property int                    $user_id
 * @property float                  $total_hours
 * @property float                  $day_shifts_hours
 * @property float                  $night_shifts_hours
 * @property float                  $freeload_penalty_hours
 * @property float                  $worklog_hours
 * @property int                    $completed_shifts_count
 * @property int                    $night_shifts_count
 * @property int                    $freeload_shifts_count
 * @property Carbon|null            $last_calculated_at
 * @property Carbon                 $created_at
 * @property Carbon                 $updated_at
 *
 * @property-read User              $user
 * @property-read bool              $isStale
 * @property-read int               $hoursSinceCalculation
 *
 * @method static Builder|GoodiesV2UserHoursCache whereId($value)
 * @method static Builder|GoodiesV2UserHoursCache whereUserId($value)
 * @method static Builder|GoodiesV2UserHoursCache whereTotalHours($value)
 * @method static Builder|GoodiesV2UserHoursCache whereLastCalculatedAt($value)
 * @method static Builder|GoodiesV2UserHoursCache whereCreatedAt($value)
 * @method static Builder|GoodiesV2UserHoursCache whereUpdatedAt($value)
 */
class GoodiesV2UserHoursCache extends BaseModel
{
    use HasFactory;
    use UsesUserModel;

    protected $keyType = 'integer'; // phpcs:ignore
    public $incrementing = true; // phpcs:ignore
    public $timestamps = true; // phpcs:ignore

    protected $table = 'goodiesv2_user_hours_cache'; // phpcs:ignore

    /** @var int Cache is considered stale after this many hours */
    public const STALE_THRESHOLD_HOURS = 24;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [ // phpcs:ignore
        'user_id',
        'total_hours',
        'day_shifts_hours',
        'night_shifts_hours',
        'freeload_penalty_hours',
        'worklog_hours',
        'completed_shifts_count',
        'night_shifts_count',
        'freeload_shifts_count',
        'last_calculated_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    /** @var array<string, string> */
    protected $casts = [ // phpcs:ignore
        'user_id' => 'integer',
        'total_hours' => 'float',
        'day_shifts_hours' => 'float',
        'night_shifts_hours' => 'float',
        'freeload_penalty_hours' => 'float',
        'worklog_hours' => 'float',
        'completed_shifts_count' => 'integer',
        'night_shifts_count' => 'integer',
        'freeload_shifts_count' => 'integer',
        'last_calculated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user this cache entry belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the total hours for this user.
     */
    public function getTotalHours(): int
    {
        return $this->total_hours;
    }

    /**
     * Check if this cache entry is stale and needs refreshing.
     */
    public function isStale(): bool
    {
        if (!$this->last_calculated_at) {
            return true;
        }

        return $this->last_calculated_at->isBefore(
            Carbon::now()->subHours(self::STALE_THRESHOLD_HOURS)
        );
    }

    /**
     * Get the number of hours since the last calculation.
     */
    public function getHoursSinceCalculationAttribute(): int
    {
        if (!$this->last_calculated_at) {
            return PHP_INT_MAX;
        }

        return (int) $this->last_calculated_at->diffInHours(Carbon::now());
    }

    /**
     * Get the stale attribute for direct access.
     */
    public function getIsStaleAttribute(): bool
    {
        return $this->isStale();
    }

    /**
     * Check if the cache was calculated today.
     */
    public function isCalculatedToday(): bool
    {
        return $this->last_calculated_at && $this->last_calculated_at->isToday();
    }

    /**
     * Update the cache with new hours and current timestamp.
     */
    public function refresh(int $totalHours = null): void
    {
        $this->update([
            'total_hours' => $totalHours,
            'last_calculated_at' => Carbon::now(),
        ]);
    }

    /**
     * Touch the last calculated timestamp without changing hours.
     */
    public function touchCalculatedAt(): void
    {
        $this->update([
            'last_calculated_at' => Carbon::now(),
        ]);
    }

    /**
     * Get a formatted string showing when this was last calculated.
     */
    public function getLastCalculatedFormatted(): string
    {
        if (!$this->last_calculated_at) {
            return 'Never calculated';
        }

        return $this->last_calculated_at->diffForHumans();
    }

    /**
     * Scope to filter cache entries by user.
     */
    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter stale cache entries.
     */
    public function scopeStale(Builder $query, int $thresholdHours = self::STALE_THRESHOLD_HOURS): Builder
    {
        return $query->where(function ($q) use ($thresholdHours): void {
            $q->whereNull('last_calculated_at')
              ->orWhere('last_calculated_at', '<', Carbon::now()->subHours($thresholdHours));
        });
    }

    /**
     * Scope to filter fresh cache entries.
     */
    public function scopeFresh(Builder $query, int $thresholdHours = self::STALE_THRESHOLD_HOURS): Builder
    {
        return $query->whereNotNull('last_calculated_at')
            ->where('last_calculated_at', '>=', Carbon::now()->subHours($thresholdHours));
    }

    /**
     * Scope to filter cache entries with minimum hours.
     */
    public function scopeWithMinimumHours(Builder $query, int $minHours): Builder
    {
        return $query->where('total_hours', '>=', $minHours);
    }

    /**
     * Scope to order by total hours descending.
     */
    public function scopeOrderByHours(Builder $query): Builder
    {
        return $query->orderByDesc('total_hours');
    }

    /**
     * Scope to order by most recently calculated first.
     */
    public function scopeOrderByCalculated(Builder $query): Builder
    {
        return $query->orderByDesc('last_calculated_at');
    }

    /**
     * Scope to include user data for efficient loading.
     */
    public function scopeWithUser(Builder $query): Builder
    {
        return $query->with('user');
    }

    /**
     * Create or update a cache entry for a user.
     */
    public static function updateForUser(int $userId, int $totalHours): self
    {
        return static::updateOrCreate(
            ['user_id' => $userId],
            [
                'total_hours' => $totalHours,
                'last_calculated_at' => Carbon::now(),
            ]
        );
    }

    /**
     * Get cached hours for a user, or null if not cached or stale.
     */
    public static function getCachedHoursForUser(int $userId): ?int
    {
        $cache = static::where('user_id', $userId)->first();

        if (!$cache || $cache->isStale()) {
            return null;
        }

        return $cache->total_hours;
    }
}
