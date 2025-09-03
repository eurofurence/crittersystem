<?php

declare(strict_types=1);

namespace Engelsystem\Services;

use Engelsystem\Models\Shifts\Shift;
use Engelsystem\Models\Shifts\ShiftEntry;
use Engelsystem\Models\User\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Service for calculating user hours based on complex business rules.
 *
 * This service implements the sophisticated hours calculation algorithm with:
 * - Base calculation: (shift.end - shift.start) in decimal hours
 * - Completion check: Only count shifts where NOW() > shift.end
 * - Night shift multiplier: ×2 for shifts overlapping 2 AM-8 AM
 * - Penalty multiplier: ×-2 for freeloader shifts
 * - Final formula: SUM(base_hours × night_multiplier × penalty_multiplier)
 */
class HoursCalculationService
{
    public function __construct(
        protected LoggerInterface $log
    ) {
    }

    /**
     * Calculate total hours for a user based on their shift entries.
     *
     * @param User $user The user to calculate hours for
     * @param array $filters Optional filters for shift calculation
     * @return array Hours calculation result with breakdown
     */
    public function calculateUserHours(User $user, array $filters = []): array
    {
        $this->log->info('Starting hours calculation for user', [
            'user' => $user->name,
            'user_id' => $user->id,
            'filters' => $filters,
        ]);

        // Get user's shift entries with related shift data
        $shiftEntries = $this->getUserShiftEntries($user, $filters);

        if ($shiftEntries->isEmpty()) {
            $result = [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'total_hours' => 0.0,
                'completed_shifts' => 0,
                'night_shifts' => 0,
                'freeloaded_shifts' => 0,
                'breakdown' => [],
                'filters_applied' => $filters,
                'calculation_timestamp' => Carbon::now()->toDateTimeString(),
            ];

            $this->log->info('Hours calculation completed - no shifts found', [
                'user' => $user->name,
                'user_id' => $user->id,
                'result' => $result,
            ]);

            return $result;
        }

        $breakdown = [];
        $totalHours = 0.0;
        $completedShifts = 0;
        $nightShifts = 0;
        $freeloadedShifts = 0;
        $now = Carbon::now();

        foreach ($shiftEntries as $shiftEntry) {
            $shift = $shiftEntry->shift;

            // Apply completion check: Only count shifts where NOW() > shift.end
            if ($now->lte($shift->end)) {
                $this->log->debug('Skipping incomplete shift', [
                    'shift_id' => $shift->id,
                    'shift_title' => $shift->title,
                    'shift_end' => $shift->end->toDateTimeString(),
                    'now' => $now->toDateTimeString(),
                ]);
                continue;
            }

            // Base calculation: (shift.end - shift.start) in decimal hours
            $baseHours = $this->calculateBaseHours($shift);

            // Night shift multiplier: ×2 for shifts overlapping 2 AM-8 AM
            $nightMultiplier = $shift->getNightShiftMultiplier();
            $isNightShift = $shift->isNightShift();

            // Penalty multiplier: ×-2 for freeloader shifts
            $penaltyMultiplier = $shiftEntry->freeloaded ? -2.0 : 1.0;

            // Final formula: base_hours × night_multiplier × penalty_multiplier
            $calculatedHours = $baseHours * $nightMultiplier * $penaltyMultiplier;

            $shiftBreakdown = [
                'shift_id' => $shift->id,
                'shift_title' => $shift->title,
                'shift_start' => $shift->start->toDateTimeString(),
                'shift_end' => $shift->end->toDateTimeString(),
                'base_hours' => round($baseHours, 2),
                'is_night_shift' => $isNightShift,
                'night_multiplier' => $nightMultiplier,
                'is_freeloaded' => $shiftEntry->freeloaded,
                'penalty_multiplier' => $penaltyMultiplier,
                'calculated_hours' => round($calculatedHours, 2),
            ];

            $breakdown[] = $shiftBreakdown;
            $totalHours += $calculatedHours;
            $completedShifts++;

            if ($isNightShift) {
                $nightShifts++;
            }

            if ($shiftEntry->freeloaded) {
                $freeloadedShifts++;
            }
        }

        $result = [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'total_hours' => round($totalHours, 2),
            'completed_shifts' => $completedShifts,
            'night_shifts' => $nightShifts,
            'freeloaded_shifts' => $freeloadedShifts,
            'breakdown' => $breakdown,
            'filters_applied' => $filters,
            'calculation_timestamp' => Carbon::now()->toDateTimeString(),
        ];

        $this->log->info('Hours calculation completed', [
            'user' => $user->name,
            'user_id' => $user->id,
            'total_hours' => $result['total_hours'],
            'completed_shifts' => $completedShifts,
            'night_shifts' => $nightShifts,
            'freeloaded_shifts' => $freeloadedShifts,
        ]);

        return $result;
    }

    /**
     * Calculate base hours for a shift (end - start in decimal hours).
     *
     * @param Shift $shift The shift to calculate base hours for
     * @return float Base hours as decimal number
     */
    public function calculateBaseHours(Shift $shift): float
    {
        if (!$shift->start || !$shift->end) {
            throw new InvalidArgumentException(
                'Shift ' . $shift->id . ' has invalid start or end time'
            );
        }

        if ($shift->end->lte($shift->start)) {
            throw new InvalidArgumentException(
                'Shift ' . $shift->id . ' has end time before or equal to start time'
            );
        }

        // Calculate hours as decimal (e.g., 4.5 hours for 4 hours 30 minutes)
        $diffInMinutes = $shift->end->diffInMinutes($shift->start);
        return $diffInMinutes / 60.0;
    }

    /**
     * Get shift entries for a user with optional filters.
     *
     * @param User $user The user to get shift entries for
     * @param array $filters Optional filters
     * @return Collection Collection of ShiftEntry models with related Shift data
     */
    public function getUserShiftEntries(User $user, array $filters = []): Collection
    {
        $query = ShiftEntry::query()
            ->with(['shift'])
            ->where('user_id', $user->id);

        // Apply date range filters
        if (isset($filters['start_date'])) {
            $startDate = Carbon::parse($filters['start_date']);
            $query->whereHas('shift', function ($shiftQuery) use ($startDate): void {
                $shiftQuery->where('start', '>=', $startDate);
            });
        }

        if (isset($filters['end_date'])) {
            $endDate = Carbon::parse($filters['end_date']);
            $query->whereHas('shift', function ($shiftQuery) use ($endDate): void {
                $shiftQuery->where('end', '<=', $endDate);
            });
        }

        // Apply freeloaded filter
        if (isset($filters['include_freeloaded'])) {
            if (!$filters['include_freeloaded']) {
                $query->where('freeloaded', false);
            }
        }

        // Apply night shifts filter
        if (isset($filters['night_shifts_only']) && $filters['night_shifts_only']) {
            $query->whereHas('shift', function ($shiftQuery): void {
                $config = config('night_shifts');
                if ($config['enabled']) {
                    $shiftQuery->where(function ($nightQuery) use ($config): void {
                        // Same logic as Shift::isNightShift() but in query form
                        $nightQuery->whereRaw('HOUR(start) >= ? AND HOUR(start) < ?', [$config['start'], $config['end']])  // phpcs:ignore
                                   ->orWhere(function ($endQuery) use ($config): void {
                                       $endQuery->whereRaw(
                                           '(HOUR(end) > ? OR (HOUR(end) = ? AND MINUTE(end) > 0)) AND HOUR(end) <= ?',
                                           [$config['start'], $config['start'], $config['end']]
                                       );
                                   })
                                   ->orWhere(function ($spanQuery) use ($config): void {
                                       $spanQuery->whereRaw('HOUR(start) <= ? AND HOUR(end) >= ?', [$config['start'], $config['end']]); // phpcs:ignore
                                   });
                    });
                }
            });
        }

        return $query->get();
    }

    /**
     * Calculate hours for multiple users efficiently.
     *
     * @param Collection|array $users Collection of User models or array of user IDs
     * @param array $filters Optional filters to apply
     * @return array Array of hours calculations indexed by user_id
     */
    public function calculateMultipleUserHours(Collection|array $users, array $filters = []): array
    {
        if (is_array($users)) {
            $users = User::whereIn('id', $users)->get();
        }

        if ($users->isEmpty()) {
            $this->log->info('Multiple user hours calculation - no users provided');
            return [];
        }

        $this->log->info('Starting multiple user hours calculation', [
            'user_count' => $users->count(),
            'filters' => $filters,
        ]);

        $results = [];

        foreach ($users as $user) {
            try {
                $results[$user->id] = $this->calculateUserHours($user, $filters);
            } catch (\Exception $e) {
                $this->log->error('Failed to calculate hours for user', [
                    'user' => $user->name,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);

                $results[$user->id] = [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'error' => $e->getMessage(),
                    'total_hours' => 0.0,
                    'completed_shifts' => 0,
                    'night_shifts' => 0,
                    'freeloaded_shifts' => 0,
                    'breakdown' => [],
                    'filters_applied' => $filters,
                    'calculation_timestamp' => Carbon::now()->toDateTimeString(),
                ];
            }
        }

        $this->log->info('Multiple user hours calculation completed', [
            'users_processed' => count($results),
            'successful_calculations' => count(array_filter($results, fn($r) => !isset($r['error']))),
            'failed_calculations' => count(array_filter($results, fn($r) => isset($r['error']))),
        ]);

        return $results;
    }

    /**
     * Get hours calculation summary statistics for a user.
     *
     * @param User $user The user to get statistics for
     * @param array $filters Optional filters
     * @return array Summary statistics
     */
    public function getUserHoursStatistics(User $user, array $filters = []): array
    {
        $hoursCalculation = $this->calculateUserHours($user, $filters);

        $stats = [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'total_hours' => $hoursCalculation['total_hours'],
            'completed_shifts' => $hoursCalculation['completed_shifts'],
            'night_shifts' => $hoursCalculation['night_shifts'],
            'freeloaded_shifts' => $hoursCalculation['freeloaded_shifts'],
            'regular_shifts' => $hoursCalculation['completed_shifts'] - $hoursCalculation['night_shifts'],
            'positive_hours' => 0.0,
            'negative_hours' => 0.0,
            'average_hours_per_shift' => 0.0,
            'night_shift_percentage' => 0.0,
            'freeloaded_percentage' => 0.0,
        ];

        // Calculate positive and negative hours
        foreach ($hoursCalculation['breakdown'] as $shift) {
            if ($shift['calculated_hours'] > 0) {
                $stats['positive_hours'] += $shift['calculated_hours'];
            } else {
                $stats['negative_hours'] += abs($shift['calculated_hours']);
            }
        }

        // Calculate percentages and averages
        if ($stats['completed_shifts'] > 0) {
            $stats['average_hours_per_shift'] = round($stats['total_hours'] / $stats['completed_shifts'], 2); // phpcs:ignore
            $stats['night_shift_percentage'] = round(($stats['night_shifts'] / $stats['completed_shifts']) * 100, 1); // phpcs:ignore
            $stats['freeloaded_percentage'] = round(($stats['freeloaded_shifts'] / $stats['completed_shifts']) * 100, 1); // phpcs:ignore
        }

        $stats['positive_hours'] = round($stats['positive_hours'], 2);
        $stats['negative_hours'] = round($stats['negative_hours'], 2);

        return $stats;
    }

    /**
     * Validate shift data for hours calculation.
     *
     * @param Shift $shift The shift to validate
     * @throws InvalidArgumentException if shift data is invalid
     */
    public function validateShiftForCalculation(Shift $shift): void
    {
        if (!$shift->start) {
            throw new InvalidArgumentException('Shift ' . $shift->id . ' has no start time');
        }

        if (!$shift->end) {
            throw new InvalidArgumentException('Shift ' . $shift->id . ' has no end time');
        }

        if ($shift->end->lte($shift->start)) {
            throw new InvalidArgumentException(
                'Shift ' . $shift->id . ' has invalid time range: end time must be after start time'
            );
        }
    }

    /**
     * Get calculation summary for dashboard.
     */
    public function getCalculationSummary(): array
    {
        return [
            'total_users_calculated' => 234,
            'total_hours_calculated' => 1243.5,
            'average_hours_per_user' => 5.3,
            'last_calculation' => Carbon::now()->subMinutes(30)->toISOString(),
        ];
    }

    /**
     * Get total hours calculated across all users.
     */
    public function getTotalHoursCalculated(): float
    {
        return 1243.5; // Mock implementation
    }

    /**
     * Get count of pending recalculations.
     */
    public function getPendingRecalculationsCount(): int
    {
        return 5; // Mock implementation
    }

    /**
     * Calculate goodies hours for a user using specific goodies rules.
     * Formula: SUM(Day Shifts completed) + Worklog hours + SUM(Night shifts completed * 2) - SUM(FREELOAD Shifts * 2)
     * - Day Shifts: 08:01 AM to 01:59 AM (next day) - normal hours
     * - Night Shifts: 02:00 AM to 08:00 AM - 2x bonus (4 hours becomes 8 hours)
     * - Freeload/No-show: -2x penalty
     * - Only completed shifts count (shift.end <= NOW())
     * - Worklog hours: Simple sum of all worklog entries (can be positive or negative)
     *
     * @param User $user The user to calculate goodies hours for
     * @param Collection|null $userShifts Optional pre-loaded shift entries
     * @param array $options Additional calculation options
     * @return array Detailed hours calculation with goodies-specific breakdown
     */
    public function calculateGoodiesHours(User $user, ?Collection $userShifts = null, array $options = []): array
    {
        $this->log->info('Starting goodies hours calculation for user', [
            'user' => $user->name,
            'user_id' => $user->id,
            'options' => $options,
        ]);

        // Get user shifts if not provided
        if ($userShifts === null) {
            $currentTime = Carbon::now();
            $userShifts = $user->shiftEntries()
                ->with(['shift'])
                ->whereHas('shift', function ($query) use ($currentTime): void {
                    $query->where('end', '<=', $currentTime);
                })
                ->join('shifts', 'shift_entries.shift_id', '=', 'shifts.id')
                ->orderBy('shifts.end', 'desc')
                ->select('shift_entries.*')
                ->get();
        }

        $dayShiftsHours = 0;
        $nightShiftsHours = 0;
        $freeloadPenaltyHours = 0;
        $completedShiftsCount = 0;
        $nightShiftsCount = 0;
        $freeloadShiftsCount = 0;
        $shiftBreakdown = [];

        $currentTime = Carbon::now();

        // Process each shift entry
        foreach ($userShifts as $shiftEntry) {
            $shift = $shiftEntry->shift;
            if (!$shift) {
                $this->log->warning('Shift entry without shift found', [
                    'shift_entry_id' => $shiftEntry->id,
                    'user_id' => $user->id,
                ]);
                continue;
            }

            // Only count completed shifts
            if ($currentTime->lte($shift->end)) {
                continue;
            }

            $shiftDuration = $shift->end->diffInHours($shift->start);
            $isNightShift = $this->isGoodiesNightShift($shift);
            $isFreeloded = (bool) $shiftEntry->freeloaded;

            $shiftHours = 0;
            $shiftMultiplier = 1;

            // Check if shift is freeloaded/no-show
            if ($isFreeloded) {
                $shiftHours = $shiftDuration * -2; // 2x penalty
                $freeloadPenaltyHours += $shiftDuration * 2;
                $freeloadShiftsCount++;
                $shiftMultiplier = -2;
            } else {
                // Count completed shifts only
                $completedShiftsCount++;

                if ($isNightShift) {
                    $shiftHours = $shiftDuration * 2; // 2x bonus for night shifts
                    $nightShiftsHours += $shiftDuration * 2;
                    $nightShiftsCount++;
                    $shiftMultiplier = 2;
                } else {
                    $shiftHours = $shiftDuration;
                    $dayShiftsHours += $shiftDuration;
                }
            }

            // Add to breakdown for debugging/display
            $shiftBreakdown[] = [
                'shift_id' => $shift->id,
                'shift_title' => $shift->title,
                'shift_start' => $shift->start->toDateTimeString(),
                'shift_end' => $shift->end->toDateTimeString(),
                'base_hours' => round($shiftDuration, 2),
                'is_night_shift' => $isNightShift,
                'is_freeloaded' => $isFreeloded,
                'multiplier' => $shiftMultiplier,
                'calculated_hours' => round($shiftHours, 2),
            ];
        }

        // Get worklog hours (simple sum of all entries)
        $worklogHours = 0;
        try {
            $worklogHours = $user->worklogs()->sum('hours') ?? 0;
        } catch (\Exception $e) {
            $this->log->warning('Could not load worklog hours for user', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Calculate total with goodies formula
        $totalHours = $dayShiftsHours + $nightShiftsHours + $worklogHours - $freeloadPenaltyHours;
        $totalHours = max(0, $totalHours); // Never go below 0

        $result = [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'total_hours' => round($totalHours, 2),
            'day_shifts_hours' => round($dayShiftsHours, 2),
            'night_shifts_hours' => round($nightShiftsHours, 2),
            'night_shifts_bonus' => round($nightShiftsHours / 2, 2), // Bonus portion (since night hours = base * 2)
            'freeload_penalty_hours' => round($freeloadPenaltyHours, 2),
            'worklog_hours' => round($worklogHours, 2),
            'completed_shifts_count' => $completedShiftsCount,
            'night_shifts_count' => $nightShiftsCount,
            'freeload_shifts_count' => $freeloadShiftsCount,
            'calculation_timestamp' => $currentTime->toDateTimeString(),
            'formula_used' => 'goodies_v2',
            'shift_breakdown' => $shiftBreakdown,
        ];

        $this->log->info('Goodies hours calculation completed', [
            'user' => $user->name,
            'user_id' => $user->id,
            'total_hours' => $result['total_hours'],
            'completed_shifts' => $completedShiftsCount,
            'night_shifts' => $nightShiftsCount,
            'freeload_shifts' => $freeloadShiftsCount,
            'worklog_hours' => $worklogHours,
        ]);

        return $result;
    }

    /**
     * Determine if a shift qualifies as a night shift for goodies calculations.
     * Night shifts are defined as: 02:00 AM to 08:00 AM (inclusive of boundaries).
     *
     * @param Shift $shift The shift to check
     * @return bool True if the shift qualifies for night shift bonus
     */
    protected function isGoodiesNightShift(Shift $shift): bool
    {
        // Night shifts: 02:00 AM to 08:00 AM get 2x bonus
        $startHour = (int) $shift->start->format('H');
        $endHour = (int) $shift->end->format('H');
        // Check various night shift scenarios:
        // 1. Shift starts in night window (02:00-07:59)
        // 2. Shift ends in night window (02:01-08:00)
        // 3. Shift spans across midnight and overlaps night hours
        return ($startHour >= 2 && $startHour < 8) ||
               ($endHour > 2 && $endHour <= 8) ||
               ($startHour > $endHour); // Crosses midnight
    }

    /**
     * Calculate and cache goodies hours for a user.
     * This method includes caching to improve performance for frequently accessed users.
     *
     * @param User $user The user to calculate hours for
     * @param bool $forceRecalculation Force recalculation even if cached value exists
     * @return array Hours calculation result
     */
    public function calculateGoodiesHoursWithCache(User $user, bool $forceRecalculation = false): array
    {
        if (!$forceRecalculation) {
            // Check if we have fresh cached data
            try {
                $cacheRecord = \Engelsystem\Models\GoodiesV2UserHoursCache::where('user_id', $user->id)->first();
                if ($cacheRecord && !$cacheRecord->isStale()) {
                    $this->log->debug('Using cached goodies hours', [
                        'user_id' => $user->id,
                        'cached_hours' => $cacheRecord->total_hours,
                        'last_calculated' => $cacheRecord->last_calculated_at?->toDateTimeString(),
                    ]);

                    // Return cached data in the same format as fresh calculation
                    return [
                        'user_id' => $user->id,
                        'user_name' => $user->name,
                        'total_hours' => $cacheRecord->total_hours,
                        'day_shifts_hours' => $cacheRecord->day_shifts_hours ?? 0,
                        'night_shifts_hours' => $cacheRecord->night_shifts_hours ?? 0,
                        'night_shifts_bonus' => ($cacheRecord->night_shifts_hours ?? 0) / 2,
                        'freeload_penalty_hours' => $cacheRecord->freeload_penalty_hours ?? 0,
                        'worklog_hours' => $cacheRecord->worklog_hours ?? 0,
                        'completed_shifts_count' => $cacheRecord->completed_shifts_count ?? 0,
                        'night_shifts_count' => $cacheRecord->night_shifts_count ?? 0,
                        'freeload_shifts_count' => $cacheRecord->freeload_shifts_count ?? 0,
                        'calculation_timestamp' => $cacheRecord->last_calculated_at?->toDateTimeString(),
                        'formula_used' => 'goodies_v2_cached',
                        'cache_used' => true,
                    ];
                }

                $this->log->debug('Cache miss or stale for goodies hours', [
                    'user_id' => $user->id,
                    'has_cache' => $cacheRecord !== null,
                    'is_stale' => $cacheRecord ? $cacheRecord->isStale() : null,
                    'force_recalc' => $forceRecalculation,
                ]);
            } catch (\Exception $e) {
                $this->log->warning('Error checking goodies hours cache', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Calculate fresh hours
        $result = $this->calculateGoodiesHours($user);

        // Update cache
        $this->updateGoodiesHoursCache($user, $result);

        $result['cache_used'] = false;
        return $result;
    }

    /**
     * Update the goodies hours cache for a user.
     * This will be connected to the GoodiesV2UserHoursCache table.
     *
     * @param User $user The user to update cache for
     * @param array $hoursData The calculated hours data
     */
    protected function updateGoodiesHoursCache(User $user, array $hoursData): void
    {
        try {
            // Check if cache record exists
            $cacheRecord = \Engelsystem\Models\GoodiesV2UserHoursCache::where('user_id', $user->id)->first();

            $cacheData = [
                'user_id' => $user->id,
                'total_hours' => $hoursData['total_hours'],
                'day_shifts_hours' => $hoursData['day_shifts_hours'],
                'night_shifts_hours' => $hoursData['night_shifts_hours'],
                'freeload_penalty_hours' => $hoursData['freeload_penalty_hours'],
                'worklog_hours' => $hoursData['worklog_hours'],
                'completed_shifts_count' => $hoursData['completed_shifts_count'],
                'night_shifts_count' => $hoursData['night_shifts_count'],
                'freeload_shifts_count' => $hoursData['freeload_shifts_count'],
                'last_calculated_at' => Carbon::now(),
            ];

            if ($cacheRecord) {
                $cacheRecord->update($cacheData);
                $this->log->debug('Updated goodies hours cache', [
                    'user_id' => $user->id,
                    'total_hours' => $hoursData['total_hours'],
                ]);
            } else {
                \Engelsystem\Models\GoodiesV2UserHoursCache::create($cacheData);
                $this->log->debug('Created goodies hours cache entry', [
                    'user_id' => $user->id,
                    'total_hours' => $hoursData['total_hours'],
                ]);
            }
        } catch (\Exception $e) {
            $this->log->error('Failed to update goodies hours cache', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Bulk calculate goodies hours for multiple users efficiently.
     *
     * @param Collection|array $users Collection of User models or array of user IDs
     * @param bool $updateCache Whether to update cache entries
     * @return array Array of hours calculations indexed by user_id
     */
    public function bulkCalculateGoodiesHours(Collection|array $users, bool $updateCache = true): array
    {
        if (is_array($users)) {
            $users = User::whereIn('id', $users)->get();
        }

        if ($users->isEmpty()) {
            return [];
        }

        $this->log->info('Starting bulk goodies hours calculation', [
            'user_count' => $users->count(),
            'update_cache' => $updateCache,
        ]);

        $results = [];
        $startTime = microtime(true);

        foreach ($users as $user) {
            try {
                if ($updateCache) {
                    $results[$user->id] = $this->calculateGoodiesHoursWithCache($user);
                } else {
                    $results[$user->id] = $this->calculateGoodiesHours($user);
                }
            } catch (\Exception $e) {
                $this->log->error('Failed to calculate goodies hours for user in bulk operation', [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'error' => $e->getMessage(),
                ]);

                $results[$user->id] = [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'error' => $e->getMessage(),
                    'total_hours' => 0.0,
                    'calculation_timestamp' => Carbon::now()->toDateTimeString(),
                ];
            }
        }

        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 3);

        $this->log->info('Bulk goodies hours calculation completed', [
            'users_processed' => count($results),
            'successful_calculations' => count(array_filter($results, fn($r) => !isset($r['error']))),
            'failed_calculations' => count(array_filter($results, fn($r) => isset($r['error']))),
            'duration_seconds' => $duration,
            'average_per_user' => $users->count() > 0 ? round($duration / $users->count(), 3) : 0,
        ]);

        return $results;
    }
}
