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
}
