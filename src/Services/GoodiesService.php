<?php

declare(strict_types=1);

namespace Engelsystem\Services;

use Engelsystem\Models\User\User;
use Engelsystem\Models\GoodiesV2Category;
use Engelsystem\Models\GoodiesV2Item;
use Engelsystem\Services\HoursCalculationService;
use Engelsystem\Services\GoodiesDistributionService;
use Engelsystem\Services\UserSearchService;
use Carbon\Carbon;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Main service for goodies eligibility, qualification, and management.
 *
 * This service orchestrates the complex goodies workflow by coordinating
 * with other specialized services to determine user eligibility,
 * manage qualification processes, and handle distribution logistics.
 */
class GoodiesService
{
    public function __construct(
        protected LoggerInterface $log,
        protected HoursCalculationService $hoursCalculationService,
        protected GoodiesDistributionService $distributionService,
        protected UserSearchService $userSearchService
    ) {
    }

    /**
     * Check if a user is eligible for goodies based on hours threshold.
     *
     * @param User $user The user to check eligibility for
     * @param float $minimumHours Minimum hours required for eligibility
     * @param array $filters Optional filters for hours calculation
     * @return array Eligibility result with detailed breakdown
     */
    public function checkUserEligibility(User $user, float $minimumHours = 12.0, array $filters = []): array
    {
        if ($minimumHours < 0) {
            throw new InvalidArgumentException('Minimum hours cannot be negative');
        }

        $this->log->info('Checking user goodies eligibility', [
            'user' => $user->name,
            'user_id' => $user->id,
            'minimum_hours' => $minimumHours,
            'filters' => $filters,
        ]);

        // Get user's hours calculation
        $hoursCalculation = $this->hoursCalculationService->calculateUserHours($user, $filters);
        $totalHours = $hoursCalculation['total_hours'];

        // Determine eligibility
        $isEligible = $totalHours >= $minimumHours;
        $hoursShortfall = $isEligible ? 0.0 : round($minimumHours - $totalHours, 2);

        // Get user statistics for additional context
        $statistics = $this->hoursCalculationService->getUserHoursStatistics($user, $filters);

        $eligibilityResult = [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'is_eligible' => $isEligible,
            'total_hours' => $totalHours,
            'minimum_hours_required' => $minimumHours,
            'hours_shortfall' => $hoursShortfall,
            'completed_shifts' => $hoursCalculation['completed_shifts'],
            'night_shifts' => $hoursCalculation['night_shifts'],
            'freeloaded_shifts' => $hoursCalculation['freeloaded_shifts'],
            'statistics' => $statistics,
            'check_timestamp' => Carbon::now()->toDateTimeString(),
            'filters_applied' => $filters,
        ];

        $this->log->info('User eligibility check completed', [
            'user' => $user->name,
            'user_id' => $user->id,
            'is_eligible' => $isEligible,
            'total_hours' => $totalHours,
            'minimum_hours' => $minimumHours,
            'hours_shortfall' => $hoursShortfall,
        ]);

        return $eligibilityResult;
    }

    /**
     * Get all eligible users for goodies based on hours threshold.
     *
     * @param float $minimumHours Minimum hours required for eligibility
     * @param array $filters Optional filters for hours calculation
     * @param array $searchCriteria Optional search criteria for user filtering
     * @return array List of eligible users with their eligibility details
     */
    public function getEligibleUsers(float $minimumHours = 12.0, array $filters = [], array $searchCriteria = []): array
    {
        $this->log->info('Getting eligible users for goodies', [
            'minimum_hours' => $minimumHours,
            'filters' => $filters,
            'search_criteria' => $searchCriteria,
        ]);

        // Get users based on search criteria
        $users = $this->userSearchService->searchUsers($searchCriteria);

        if ($users->isEmpty()) {
            $this->log->info('No users found matching search criteria', [
                'search_criteria' => $searchCriteria,
            ]);
            return [
                'eligible_users' => [],
                'total_users_checked' => 0,
                'eligible_count' => 0,
                'ineligible_count' => 0,
                'minimum_hours_required' => $minimumHours,
                'search_criteria' => $searchCriteria,
                'filters_applied' => $filters,
                'check_timestamp' => Carbon::now()->toDateTimeString(),
            ];
        }

        // Calculate hours for all users
        $hoursCalculations = $this->hoursCalculationService->calculateMultipleUserHours($users, $filters);

        $eligibleUsers = [];
        $ineligibleUsers = [];

        foreach ($hoursCalculations as $userId => $calculation) {
            $user = $users->firstWhere('id', $userId);
            if (!$user) {
                continue;
            }

            $isEligible = !isset($calculation['error']) && $calculation['total_hours'] >= $minimumHours;

            $eligibilityData = [
                'user_id' => $userId,
                'user_name' => $calculation['user_name'],
                'total_hours' => $calculation['total_hours'],
                'is_eligible' => $isEligible,
                'hours_shortfall' => $isEligible ? 0.0 : round($minimumHours - $calculation['total_hours'], 2),
                'completed_shifts' => $calculation['completed_shifts'],
                'night_shifts' => $calculation['night_shifts'],
                'freeloaded_shifts' => $calculation['freeloaded_shifts'],
                'calculation_error' => $calculation['error'] ?? null,
            ];

            if ($isEligible) {
                $eligibleUsers[] = $eligibilityData;
            } else {
                $ineligibleUsers[] = $eligibilityData;
            }
        }

        // Sort eligible users by total hours (descending)
        usort($eligibleUsers, fn($a, $b) => $b['total_hours'] <=> $a['total_hours']);

        $result = [
            'eligible_users' => $eligibleUsers,
            'ineligible_users' => $ineligibleUsers,
            'total_users_checked' => count($hoursCalculations),
            'eligible_count' => count($eligibleUsers),
            'ineligible_count' => count($ineligibleUsers),
            'minimum_hours_required' => $minimumHours,
            'search_criteria' => $searchCriteria,
            'filters_applied' => $filters,
            'check_timestamp' => Carbon::now()->toDateTimeString(),
        ];

        $this->log->info('Eligible users check completed', [
            'total_users_checked' => $result['total_users_checked'],
            'eligible_count' => $result['eligible_count'],
            'ineligible_count' => $result['ineligible_count'],
            'minimum_hours' => $minimumHours,
        ]);

        return $result;
    }

    /**
     * Qualify eligible users for goodies distribution.
     *
     * @param array $userIds Array of user IDs to qualify
     * @param float $minimumHours Minimum hours required for qualification
     * @param array $qualificationData Additional qualification data
     * @return array Qualification results
     */
    public function qualifyUsersForGoodies(array $userIds, float $minimumHours = 12.0, array $qualificationData = []): array  // phpcs:ignore
    {
        if (empty($userIds)) {
            throw new InvalidArgumentException('No user IDs provided for qualification');
        }

        $this->log->info('Starting goodies qualification process', [
            'user_count' => count($userIds),
            'minimum_hours' => $minimumHours,
            'qualification_data' => $qualificationData,
        ]);

        $users = User::whereIn('id', $userIds)->get();
        if ($users->count() !== count($userIds)) {
            $foundIds = $users->pluck('id')->toArray();
            $missingIds = array_diff($userIds, $foundIds);

            $this->log->warning('Some users not found during qualification', [
                'requested_user_ids' => $userIds,
                'missing_user_ids' => $missingIds,
            ]);
        }

        $qualificationResults = [];
        $successfulQualifications = 0;
        $failedQualifications = 0;

        foreach ($users as $user) {
            try {
                // Check eligibility first
                $eligibility = $this->checkUserEligibility($user, $minimumHours);

                if (!$eligibility['is_eligible']) {
                    $qualificationResults[] = [
                        'user_id' => $user->id,
                        'user_name' => $user->name,
                        'qualified' => false,
                        'reason' => 'Insufficient hours',
                        'total_hours' => $eligibility['total_hours'],
                        'hours_shortfall' => $eligibility['hours_shortfall'],
                        'error' => null,
                    ];
                    $failedQualifications++;
                    continue;
                }

                // Process qualification through distribution service
                $qualificationResult = $this->distributionService->qualifyUserForDistribution(
                    $user,
                    $qualificationData
                );

                $qualificationResults[] = [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'qualified' => $qualificationResult['success'],
                    'reason' => $qualificationResult['success'] ? 'Successfully qualified' : $qualificationResult['reason'], // phpcs:ignore
                    'total_hours' => $eligibility['total_hours'],
                    'hours_shortfall' => 0.0,
                    'qualification_id' => $qualificationResult['qualification_id'] ?? null,
                    'error' => $qualificationResult['error'] ?? null,
                ];

                if ($qualificationResult['success']) {
                    $successfulQualifications++;
                } else {
                    $failedQualifications++;
                }
            } catch (\Exception $e) {
                $this->log->error('Failed to qualify user for goodies', [
                    'user' => $user->name,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);

                $qualificationResults[] = [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'qualified' => false,
                    'reason' => 'System error during qualification',
                    'total_hours' => 0.0,
                    'hours_shortfall' => 0.0,
                    'error' => $e->getMessage(),
                ];
                $failedQualifications++;
            }
        }

        $result = [
            'total_users_processed' => count($qualificationResults),
            'successful_qualifications' => $successfulQualifications,
            'failed_qualifications' => $failedQualifications,
            'minimum_hours_required' => $minimumHours,
            'qualification_results' => $qualificationResults,
            'qualification_data_used' => $qualificationData,
            'processing_timestamp' => Carbon::now()->toDateTimeString(),
        ];

        $this->log->info('Goodies qualification process completed', [
            'total_processed' => $result['total_users_processed'],
            'successful' => $successfulQualifications,
            'failed' => $failedQualifications,
        ]);

        return $result;
    }

    /**
     * Get comprehensive goodies report for analysis.
     *
     * @param array $reportCriteria Criteria for report generation
     * @return array Comprehensive goodies report
     */
    public function generateGoodiesReport(array $reportCriteria = []): array
    {
        $minimumHours = $reportCriteria['minimum_hours'] ?? 12.0;
        $hoursFilters = $reportCriteria['hours_filters'] ?? [];
        $searchCriteria = $reportCriteria['search_criteria'] ?? [];

        $this->log->info('Generating comprehensive goodies report', [
            'report_criteria' => $reportCriteria,
        ]);

        // Get eligible users
        $eligibilityData = $this->getEligibleUsers($minimumHours, $hoursFilters, $searchCriteria);

        // Get distribution statistics
        $distributionStats = $this->distributionService->getDistributionStatistics($reportCriteria);

        // Calculate additional analytics
        $analytics = $this->calculateGoodiesAnalytics($eligibilityData, $distributionStats);

        $report = [
            'report_metadata' => [
                'generated_at' => Carbon::now()->toDateTimeString(),
                'minimum_hours_threshold' => $minimumHours,
                'criteria_used' => $reportCriteria,
                'total_users_analyzed' => $eligibilityData['total_users_checked'],
            ],
            'eligibility_summary' => [
                'eligible_users_count' => $eligibilityData['eligible_count'],
                'ineligible_users_count' => $eligibilityData['ineligible_count'],
                'eligibility_rate' => $eligibilityData['total_users_checked'] > 0
                    ? round(($eligibilityData['eligible_count'] / $eligibilityData['total_users_checked']) * 100, 1)
                    : 0.0,
            ],
            'eligible_users' => $eligibilityData['eligible_users'],
            'ineligible_users' => $eligibilityData['ineligible_users'],
            'distribution_statistics' => $distributionStats,
            'analytics' => $analytics,
            'recommendations' => $this->generateRecommendations($eligibilityData, $distributionStats, $analytics),
        ];

        $this->log->info('Goodies report generation completed', [
            'eligible_users' => $report['eligibility_summary']['eligible_users_count'],
            'ineligible_users' => $report['eligibility_summary']['ineligible_users_count'],
            'eligibility_rate' => $report['eligibility_summary']['eligibility_rate'],
        ]);

        return $report;
    }

    /**
     * Calculate analytics for goodies data.
     *
     * @param array $eligibilityData Eligibility data
     * @param array $distributionStats Distribution statistics
     * @return array Analytics results
     */
    protected function calculateGoodiesAnalytics(array $eligibilityData, array $distributionStats): array
    {
        $analytics = [
            'hours_distribution' => [
                'total_hours_eligible_users' => 0.0,
                'average_hours_eligible_users' => 0.0,
                'median_hours_eligible_users' => 0.0,
                'highest_hours' => 0.0,
                'lowest_hours' => 0.0,
            ],
            'shift_patterns' => [
                'total_night_shifts' => 0,
                'total_freeloaded_shifts' => 0,
                'users_with_night_shifts' => 0,
                'users_with_freeloaded_shifts' => 0,
            ],
            'performance_metrics' => [
                'users_above_threshold' => 0,
                'users_significantly_above_threshold' => 0, // > 1.5x threshold
                'users_barely_eligible' => 0, // within 1 hour of threshold
            ],
        ];

        if (empty($eligibilityData['eligible_users'])) {
            return $analytics;
        }

        $eligibleUsers = $eligibilityData['eligible_users'];
        $hours = array_column($eligibleUsers, 'total_hours');
        $minimumHours = $eligibilityData['minimum_hours_required'];

        // Hours distribution analytics
        $analytics['hours_distribution']['total_hours_eligible_users'] = array_sum($hours);
        $analytics['hours_distribution']['average_hours_eligible_users'] = round(array_sum($hours) / count($hours), 2); // phpcs:ignore
        $analytics['hours_distribution']['highest_hours'] = max($hours);
        $analytics['hours_distribution']['lowest_hours'] = min($hours);

        // Calculate median
        sort($hours);
        $count = count($hours);
        if ($count % 2 === 0) {
            $analytics['hours_distribution']['median_hours_eligible_users'] = ($hours[$count / 2 - 1] + $hours[$count / 2]) / 2; // phpcs:ignore
        } else {
            $analytics['hours_distribution']['median_hours_eligible_users'] = $hours[floor($count / 2)];
        }
        $analytics['hours_distribution']['median_hours_eligible_users'] = round($analytics['hours_distribution']['median_hours_eligible_users'], 2); // phpcs:ignore

        // Shift patterns analytics
        foreach ($eligibleUsers as $user) {
            $analytics['shift_patterns']['total_night_shifts'] += $user['night_shifts'];
            $analytics['shift_patterns']['total_freeloaded_shifts'] += $user['freeloaded_shifts'];

            if ($user['night_shifts'] > 0) {
                $analytics['shift_patterns']['users_with_night_shifts']++;
            }

            if ($user['freeloaded_shifts'] > 0) {
                $analytics['shift_patterns']['users_with_freeloaded_shifts']++;
            }
        }

        // Performance metrics
        foreach ($eligibleUsers as $user) {
            $userHours = $user['total_hours'];

            if ($userHours >= $minimumHours * 1.5) {
                $analytics['performance_metrics']['users_significantly_above_threshold']++;
            }

            if ($userHours >= $minimumHours && $userHours <= $minimumHours + 1.0) {
                $analytics['performance_metrics']['users_barely_eligible']++;
            }
        }

        $analytics['performance_metrics']['users_above_threshold'] = count($eligibleUsers);

        return $analytics;
    }

    /**
     * Generate recommendations based on goodies data analysis.
     *
     * @param array $eligibilityData Eligibility data
     * @param array $distributionStats Distribution statistics
     * @param array $analytics Analytics results
     * @return array Recommendations
     */
    protected function generateRecommendations(array $eligibilityData, array $distributionStats, array $analytics): array // phpcs:ignore
    {
        $recommendations = [];

        // Eligibility rate recommendations
        $eligibilityRate = $eligibilityData['total_users_checked'] > 0
            ? ($eligibilityData['eligible_count'] / $eligibilityData['total_users_checked']) * 100
            : 0;

        if ($eligibilityRate < 50) {
            $recommendations[] = [
                'type' => 'threshold_adjustment',
                'priority' => 'high',
                'message' => 'Low eligibility rate (' . round($eligibilityRate, 1) . '%). Consider lowering the minimum hours threshold.', // phpcs:ignore
                'current_threshold' => $eligibilityData['minimum_hours_required'],
                'suggested_threshold' => max(6.0, $eligibilityData['minimum_hours_required'] * 0.75),
            ];
        } elseif ($eligibilityRate > 85) {
            $recommendations[] = [
                'type' => 'threshold_adjustment',
                'priority' => 'medium',
                'message' => 'High eligibility rate (' . round($eligibilityRate, 1) . '%). Consider raising the minimum hours threshold.', // phpcs:ignore
                'current_threshold' => $eligibilityData['minimum_hours_required'],
                'suggested_threshold' => $eligibilityData['minimum_hours_required'] * 1.25,
            ];
        }

        // Freeloaded shifts recommendations
        if ($analytics['shift_patterns']['users_with_freeloaded_shifts'] > 0) {
            $freeloadedPercentage = ($analytics['shift_patterns']['users_with_freeloaded_shifts'] / $eligibilityData['eligible_count']) * 100; // phpcs:ignore

            if ($freeloadedPercentage > 20) {
                $recommendations[] = [
                    'type' => 'freeloaded_policy',
                    'priority' => 'medium',
                    'message' => 'High number of users with freeloaded shifts (' . round($freeloadedPercentage, 1) . '%). Review freeloading policies.', // phpcs:ignore
                    'affected_users' => $analytics['shift_patterns']['users_with_freeloaded_shifts'],
                ];
            }
        }

        // Night shift recommendations
        if ($analytics['shift_patterns']['users_with_night_shifts'] > 0) {
            $nightShiftPercentage = ($analytics['shift_patterns']['users_with_night_shifts'] / $eligibilityData['eligible_count']) * 100; // phpcs:ignore

            if ($nightShiftPercentage < 30) {
                $recommendations[] = [
                    'type' => 'night_shift_incentive',
                    'priority' => 'low',
                    'message' => 'Low participation in night shifts (' . round($nightShiftPercentage, 1) . '%). Consider additional incentives for night shifts.', // phpcs:ignore
                    'night_shift_users' => $analytics['shift_patterns']['users_with_night_shifts'],
                ];
            }
        }

        // Distribution capacity recommendations
        if (
            isset($distributionStats['pending_distributions']) &&
            $distributionStats['pending_distributions'] > $eligibilityData['eligible_count']
        ) {
            $recommendations[] = [
                'type' => 'distribution_capacity',
                'priority' => 'high',
                'message' => 'Distribution capacity may be insufficient for all eligible users.',
                'eligible_users' => $eligibilityData['eligible_count'],
                'distribution_capacity' => $distributionStats['pending_distributions'],
            ];
        }

        return $recommendations;
    }

    /**
     * Validate goodies configuration and settings.
     *
     * @param array $config Configuration to validate
     * @throws InvalidArgumentException if configuration is invalid
     */
    public function validateGoodiesConfiguration(array $config): void
    {
        $requiredFields = ['minimum_hours'];
        $missingFields = [];

        foreach ($requiredFields as $field) {
            if (!isset($config[$field])) {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            throw new InvalidArgumentException(
                'Missing required configuration fields: ' . implode(', ', $missingFields)
            );
        }

        if ($config['minimum_hours'] < 0) {
            throw new InvalidArgumentException('Minimum hours must be non-negative');
        }

        if (isset($config['maximum_hours']) && $config['maximum_hours'] <= $config['minimum_hours']) {
            throw new InvalidArgumentException('Maximum hours must be greater than minimum hours');
        }
    }

    /**
     * Get count of eligible users for goodies.
     */
    public function getEligibleUsersCount(float $minimumHours = 12.0): int
    {
        // Mock implementation - in real implementation would query database
        $this->log->info('Getting eligible users count', [
            'minimum_hours' => $minimumHours,
        ]);

        // Return mock data for now
        return 145;
    }

    /**
     * Get system KPIs for dashboard.
     */
    public function getSystemKpis(): array
    {
        // Mock implementation - in real implementation would aggregate actual data
        return [
            'eligible_users' => $this->getEligibleUsersCount(),
            'system_status' => 'operational',
            'last_updated' => Carbon::now()->toISOString(),
        ];
    }

    /**
     * Get paginated list of eligible users.
     */
    public function getEligibleUsersPaginated(int $page, int $perPage, array $filters = []): array
    {
        // Mock implementation - in real implementation would paginate database results
        $this->log->info('Getting paginated eligible users', [
            'page' => $page,
            'per_page' => $perPage,
            'filters' => $filters,
        ]);

        return [
            'data' => [],
            'total' => 145,
            'total_pages' => (int) ceil(145 / $perPage),
            'current_page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * Get all categories with optional filtering.
     */
    public function getAllCategories(array $filters = []): array
    {
        $this->log->info('Getting all goodies categories', [
            'filters' => $filters,
        ]);

        $query = GoodiesV2Category::query()->ordered()->withItemCounts();

        // Apply filters
        if (isset($filters['active_only']) && $filters['active_only']) {
            $query->active();
        }

        if (isset($filters['with_items_only']) && $filters['with_items_only']) {
            $query->withActiveItems();
        }

        $categories = $query->get();

        $this->log->info('Retrieved goodies categories', [
            'count' => $categories->count(),
            'filters_applied' => $filters,
        ]);

        return $categories->toArray();
    }

    /**
     * Get paginated goodies items.
     */
    public function getGoodiesItemsPaginated(int $page, int $perPage, array $filters = []): array
    {
        $this->log->info('Getting paginated goodies items', [
            'page' => $page,
            'per_page' => $perPage,
            'filters' => $filters,
        ]);

        $query = GoodiesV2Item::query()->with('category')->ordered();

        // Apply filters
        if (isset($filters['active_only']) && $filters['active_only']) {
            $query->where('is_active', true);
        }

        if (isset($filters['category_id']) && $filters['category_id']) {
            $query->where('category_id', $filters['category_id']);
        }

        $total = $query->count();
        $items = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        return [
            'data' => $items->map(function ($item) {
                $itemArray = $item->toArray();
                // Ensure category relationship is properly included
                if ($item->category) {
                    $itemArray['category'] = $item->category->toArray();
                } else {
                    $itemArray['category'] = null;
                }
                return $itemArray;
            })->toArray(),
            'total' => $total,
            'total_pages' => (int) ceil($total / $perPage),
            'current_page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * Get available goodies items for distribution.
     */
    public function getAvailableGoodiesItems(): array
    {
        // Mock implementation - would query active items with stock > 0
        return [];
    }

    /**
     * Search eligible users with filters.
     */
    public function searchEligibleUsers(array $filters = []): array
    {
        // Mock implementation
        return [];
    }

    /**
     * Create a new goodies category.
     */
    public function createCategory(array $data): GoodiesV2Category
    {
        $this->log->info('Creating new goodies category', [
            'data' => $data,
        ]);

        $category = GoodiesV2Category::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'display_order' => (int) ($data['display_order'] ?? 0),
        ]);

        $this->log->info('Created goodies category', [
            'category_id' => $category->id,
            'category_uuid' => $category->uuid,
            'category_name' => $category->name,
        ]);

        return $category;
    }

    /**
     * Create a new goodies item.
     */
    public function createItem(array $data): GoodiesV2Item
    {
        $this->log->info('Creating new goodies item', [
            'data' => $data,
        ]);

        $item = GoodiesV2Item::create([
            'category_id' => $data['category_id'] ?: null,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'required_hours' => (int) ($data['required_hours'] ?? 0),
            'max_per_person' => isset($data['max_per_person']) && $data['max_per_person'] !== ''
                ? (int) $data['max_per_person']
                : null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'display_order' => (int) ($data['display_order'] ?? 0),
        ]);

        // Handle certification relationships
        if (isset($data['certifications']) && is_array($data['certifications'])) {
            $certificationIds = array_filter(array_map('intval', $data['certifications']));
            $item->certifications()->sync($certificationIds);
        }

        $this->log->info('Created goodies item', [
            'item_id' => $item->id,
            'item_uuid' => $item->uuid,
            'item_name' => $item->name,
            'category_id' => $item->category_id,
        ]);

        return $item;
    }

    /**
     * Update a goodies category.
     */
    public function updateCategory(GoodiesV2Category $category, array $data): GoodiesV2Category
    {
        $this->log->info('Updating goodies category', [
            'category_id' => $category->id,
            'category_uuid' => $category->uuid,
            'category_name' => $category->name,
            'data' => $data,
        ]);

        // Update the category attributes
        $category->fill([
            'name' => $data['name'] ?? $category->name,
            'description' => $data['description'] ?? $category->description,
            'is_active' => (bool) ($data['is_active'] ?? $category->is_active),
            'display_order' => (int) ($data['display_order'] ?? $category->display_order),
        ]);

        // Save changes to database
        $category->save();

        $this->log->info('Updated goodies category', [
            'category_id' => $category->id,
            'category_uuid' => $category->uuid,
            'category_name' => $category->name,
            'updated_fields' => array_keys($data),
        ]);

        return $category;
    }

    /**
     * Update a goodies item.
     */
    public function updateItem(GoodiesV2Item $item, array $data): GoodiesV2Item
    {
        $this->log->info('Updating goodies item', [
            'item_id' => $item->id,
            'item_uuid' => $item->uuid,
            'item_name' => $item->name,
            'current_category_id' => $item->category_id,
            'new_category_id' => array_key_exists('category_id', $data) ? $data['category_id'] : 'NOT_PROVIDED',
            'category_id_type' => array_key_exists(
                'category_id',
                $data
            ) ? gettype($data['category_id']) : 'NOT_PROVIDED',
            'data' => $data,
        ]);

        // Update the item attributes
        $item->fill([
            'category_id' => array_key_exists('category_id', $data) ? $data['category_id'] : $item->category_id,
            'name' => $data['name'] ?? $item->name,
            'description' => $data['description'] ?? $item->description,
            'required_hours' => (int) ($data['required_hours'] ?? $item->required_hours),
            'max_per_person' => isset($data['max_per_person']) && $data['max_per_person'] !== ''
                ? (int) $data['max_per_person']
                : null,
            'is_active' => (bool) ($data['is_active'] ?? $item->is_active),
            'display_order' => (int) ($data['display_order'] ?? $item->display_order),
        ]);

        // Save changes to database
        $item->save();

        // Handle certification relationships
        if (array_key_exists('certifications', $data)) {
            $certificationIds = is_array($data['certifications'])
                ? array_filter(array_map('intval', $data['certifications']))
                : [];
            $item->certifications()->sync($certificationIds);
        }

        $this->log->info('Updated goodies item', [
            'item_id' => $item->id,
            'item_uuid' => $item->uuid,
            'item_name' => $item->name,
            'updated_fields' => array_keys($data),
        ]);

        return $item;
    }

    /**
     * Delete a goodies category.
     */
    public function deleteCategory(GoodiesV2Category $category): bool
    {
        $this->log->info('Deleting goodies category', [
            'category_id' => $category->id,
            'category_uuid' => $category->uuid,
            'category_name' => $category->name,
        ]);

        try {
            // Delete the category - this will cascade to related items if foreign key constraints are set up
            $deleted = $category->delete();

            if ($deleted) {
                $this->log->info('Goodies category deleted successfully', [
                    'category_id' => $category->id,
                    'category_uuid' => $category->uuid,
                    'category_name' => $category->name,
                ]);
            } else {
                $this->log->error('Failed to delete goodies category', [
                    'category_id' => $category->id,
                    'category_uuid' => $category->uuid,
                ]);
            }

            return $deleted;
        } catch (\Exception $e) {
            $this->log->error('Error deleting goodies category', [
                'category_id' => $category->id,
                'category_uuid' => $category->uuid,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Delete a goodies item.
     */
    public function deleteItem(GoodiesV2Item $item): bool
    {
        $this->log->info('Deleting goodies item', [
            'item_id' => $item->id,
            'item_uuid' => $item->uuid,
            'item_name' => $item->name,
        ]);

        try {
            // Delete related certifications first (many-to-many relationships)
            $item->certifications()->detach();

            // Delete the item - this will cascade to distributions if foreign key constraints are set up
            $deleted = $item->delete();

            if ($deleted) {
                $this->log->info('Goodies item deleted successfully', [
                    'item_id' => $item->id,
                    'item_uuid' => $item->uuid,
                    'item_name' => $item->name,
                ]);
            } else {
                $this->log->error('Failed to delete goodies item', [
                    'item_id' => $item->id,
                    'item_uuid' => $item->uuid,
                ]);
            }

            return $deleted;
        } catch (\Exception $e) {
            $this->log->error('Error deleting goodies item', [
                'item_id' => $item->id,
                'item_uuid' => $item->uuid,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get a category by its UUID.
     */
    public function getCategoryByUuid(string $uuid): ?GoodiesV2Category
    {
        $this->log->info('Getting category by UUID', [
            'uuid' => $uuid,
        ]);

        return GoodiesV2Category::where('uuid', $uuid)->withItemCounts()->first();
    }

    /**
     * Get items for a specific category.
     */
    public function getCategoryItems(GoodiesV2Category $category, array $filters = []): array
    {
        $this->log->info('Getting items for category', [
            'category_uuid' => $category->uuid,
            'category_name' => $category->name,
            'filters' => $filters,
        ]);

        $query = $category->items()->ordered();

        // Apply filters
        if (isset($filters['active_only']) && $filters['active_only']) {
            $query->where('is_active', true);
        }

        $items = $query->get();

        $this->log->info('Retrieved category items', [
            'category_uuid' => $category->uuid,
            'items_count' => $items->count(),
            'filters_applied' => $filters,
        ]);

        return $items->toArray();
    }

    /**
     * Get an item by its UUID.
     */
    public function getItemByUuid(string $uuid): ?GoodiesV2Item
    {
        $this->log->info('Getting item by UUID', [
            'uuid' => $uuid,
        ]);

        return GoodiesV2Item::where('uuid', $uuid)->with('category')->first();
    }

    /**
     * Check if a user has all required certifications for an item.
     *
     * @param User $user The user to check
     * @param GoodiesV2Item $item The item to check requirements for
     * @return array Result with status and missing certifications
     */
    public function checkUserCertifications(User $user, GoodiesV2Item $item): array
    {
        $this->log->info('Checking user certifications for item', [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'item_uuid' => $item->uuid,
            'item_name' => $item->name,
        ]);

        // Load required certifications for the item
        $requiredCertifications = $item->certifications()->active()->get();

        if ($requiredCertifications->isEmpty()) {
            $this->log->debug('No certifications required for item', [
                'item_uuid' => $item->uuid,
            ]);

            return [
                'has_certifications' => true,
                'required_certifications' => [],
                'user_certifications' => [],
                'missing_certifications' => [],
            ];
        }

        // Get user's active certifications
        $userCertifications = $user->certifications()
            ->wherePivot('status', 'confirmed')
            ->whereIn('certifications.id', $requiredCertifications->pluck('id'))
            ->get();

        // Find missing certifications
        $missingCertifications = $requiredCertifications->reject(function ($requiredCert) use ($userCertifications) {
            return $userCertifications->contains('id', $requiredCert->id);
        });

        $hasAllCertifications = $missingCertifications->isEmpty();

        $result = [
            'has_certifications' => $hasAllCertifications,
            'required_certifications' => $requiredCertifications->toArray(),
            'user_certifications' => $userCertifications->toArray(),
            'missing_certifications' => $missingCertifications->toArray(),
        ];

        $this->log->info('User certification check completed', [
            'user_id' => $user->id,
            'item_uuid' => $item->uuid,
            'has_all_certifications' => $hasAllCertifications,
            'required_count' => $requiredCertifications->count(),
            'user_has_count' => $userCertifications->count(),
            'missing_count' => $missingCertifications->count(),
        ]);

        return $result;
    }

    /**
     * Check if a user has exceeded the quantity limit for an item.
     *
     * @param User $user The user to check
     * @param GoodiesV2Item $item The item to check limits for
     * @return array Result with limit status and distribution count
     */
    public function checkUserQuantityLimit(User $user, GoodiesV2Item $item): array
    {
        $this->log->info('Checking user quantity limit for item', [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'item_uuid' => $item->uuid,
            'item_name' => $item->name,
            'max_per_person' => $item->max_per_person,
        ]);

        // If no limit is set, user can receive unlimited
        if ($item->max_per_person === null || $item->max_per_person <= 0) {
            return [
                'within_limit' => true,
                'max_allowed' => null,
                'current_count' => 0,
                'remaining_count' => null,
            ];
        }

        // Count how many of this item the user has already received
        $currentCount = $item->distributions()
            ->where('user_id', $user->id)
            ->sum('quantity');

        $withinLimit = $currentCount < $item->max_per_person;
        $remainingCount = max(0, $item->max_per_person - $currentCount);

        $result = [
            'within_limit' => $withinLimit,
            'max_allowed' => $item->max_per_person,
            'current_count' => $currentCount,
            'remaining_count' => $remainingCount,
        ];

        $this->log->info('User quantity limit check completed', [
            'user_id' => $user->id,
            'item_uuid' => $item->uuid,
            'within_limit' => $withinLimit,
            'current_count' => $currentCount,
            'max_allowed' => $item->max_per_person,
            'remaining_count' => $remainingCount,
        ]);

        return $result;
    }

    /**
     * Check if a user is eligible to receive a specific item.
     *
     * @param User $user The user to check
     * @param GoodiesV2Item $item The item to check eligibility for
     * @return array Comprehensive eligibility result
     */
    public function checkItemEligibility(User $user, GoodiesV2Item $item): array
    {
        $this->log->info('Checking user eligibility for specific item', [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'item_uuid' => $item->uuid,
            'item_name' => $item->name,
        ]);

        $result = [
            'eligible' => false,
            'reasons' => [],
        ];

        // Check if item is active
        if (!$item->is_active) {
            $result['reasons'][] = 'Item is not active';
        }

        // Check if category is active (if item has category)
        if ($item->category && !$item->category->is_active) {
            $result['reasons'][] = 'Item category is not active';
        }

        // Check hours requirement
        $hoursResult = $this->checkUserEligibility($user, (float) $item->required_hours);
        if (!$hoursResult['is_eligible']) {
            $result['reasons'][] = 'Insufficient hours: ' .
            $hoursResult['total_hours'] . '/' .
            $item->required_hours . ' required';

            $result['hours_check'] = $hoursResult;
        }

        // Check certification requirements
        $certificationResult = $this->checkUserCertifications($user, $item);
        if (!$certificationResult['has_certifications']) {
            $missingNames = array_column($certificationResult['missing_certifications'], 'title');
            $result['reasons'][] = 'Missing certifications: ' . implode(', ', $missingNames);
            $result['certification_check'] = $certificationResult;
        }

        // Check quantity limits
        $quantityResult = $this->checkUserQuantityLimit($user, $item);
        if (!$quantityResult['within_limit']) {
            $result['reasons'][] = 'Quantity limit exceeded: ' .
            $quantityResult['current_count'] . '/' .
            $quantityResult['max_allowed'] . ' maximum';

            $result['quantity_check'] = $quantityResult;
        }

        // User is eligible if no blocking reasons exist
        $result['eligible'] = empty($result['reasons']);

        $this->log->info('Item eligibility check completed', [
            'user_id' => $user->id,
            'item_uuid' => $item->uuid,
            'eligible' => $result['eligible'],
            'reason_count' => count($result['reasons']),
        ]);

        return $result;
    }
}
