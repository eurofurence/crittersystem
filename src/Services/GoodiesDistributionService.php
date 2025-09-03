<?php

declare(strict_types=1);

namespace Engelsystem\Services;

use Engelsystem\Models\User\User;
use Carbon\Carbon;
use InvalidArgumentException;
use RuntimeException;
use Psr\Log\LoggerInterface;

/**
 * Service for handling goodies distribution logistics and management.
 *
 * This service manages the distribution process including user qualification,
 * distribution tracking, inventory management, and logistics coordination.
 * It works in conjunction with GoodiesService to handle the operational aspects
 * of goodies distribution.
 */
class GoodiesDistributionService
{
    /** @var array Valid distribution statuses */
    public const VALID_STATUSES = ['pending', 'qualified', 'distributed', 'cancelled', 'expired'];

    /** @var array Valid distribution types */
    public const VALID_TYPES = ['t_shirt', 'hoodie', 'bag', 'mug', 'sticker_pack', 'custom'];

    public function __construct(
        protected LoggerInterface $log
    ) {
    }

    /**
     * Qualify a user for goodies distribution.
     *
     * @param User $user The user to qualify
     * @param array $qualificationData Qualification details
     * @return array Qualification result
     */
    public function qualifyUserForDistribution(User $user, array $qualificationData = []): array
    {
        $this->log->info('Qualifying user for goodies distribution', [
            'user' => $user->name,
            'user_id' => $user->id,
            'qualification_data' => $qualificationData,
        ]);

        try {
            // Validate qualification data
            $this->validateQualificationData($qualificationData);

            // Check if user is already qualified
            $existingQualification = $this->getUserQualification($user);

            if ($existingQualification && in_array($existingQualification['status'], ['qualified', 'distributed'])) {
                $this->log->warning('User already qualified or distributed', [
                    'user' => $user->name,
                    'user_id' => $user->id,
                    'existing_status' => $existingQualification['status'],
                ]);

                return [
                    'success' => false,
                    'reason' => 'User already qualified or has received distribution',
                    'existing_qualification' => $existingQualification,
                    'error' => null,
                ];
            }

            // Create or update qualification record
            $qualificationId = $this->createQualificationRecord($user, $qualificationData);

            // Update inventory if applicable
            $this->updateInventoryForQualification($qualificationData);

            $result = [
                'success' => true,
                'qualification_id' => $qualificationId,
                'user_id' => $user->id,
                'status' => 'qualified',
                'qualification_timestamp' => Carbon::now()->toDateTimeString(),
                'expires_at' => $this->calculateQualificationExpiry($qualificationData),
                'error' => null,
            ];

            $this->log->info('User successfully qualified for distribution', [
                'user' => $user->name,
                'user_id' => $user->id,
                'qualification_id' => $qualificationId,
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->log->error('Failed to qualify user for distribution', [
                'user' => $user->name,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'reason' => 'System error during qualification',
                'qualification_id' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Process the actual distribution of goodies to a user.
     *
     * @param User $user The user receiving distribution
     * @param array $distributionData Distribution details
     * @return array Distribution result
     */
    public function processDistribution(User $user, array $distributionData): array
    {
        $this->log->info('Processing goodies distribution', [
            'user' => $user->name,
            'user_id' => $user->id,
            'distribution_data' => $distributionData,
        ]);

        try {
            // Validate user is qualified
            $qualification = $this->getUserQualification($user);

            if (!$qualification || $qualification['status'] !== 'qualified') {
                throw new RuntimeException('User is not qualified for distribution');
            }

            // Validate distribution data
            $this->validateDistributionData($distributionData);

            // Check inventory availability
            $this->validateInventoryAvailability($distributionData);

            // Create distribution record
            $distributionId = $this->createDistributionRecord($user, $distributionData, $qualification);

            // Update qualification status
            $this->updateQualificationStatus($qualification['id'], 'distributed');

            // Update inventory
            $this->updateInventoryForDistribution($distributionData);

            // Generate distribution confirmation
            $confirmationData = $this->generateDistributionConfirmation($user, $distributionData, $distributionId);

            $result = [
                'success' => true,
                'distribution_id' => $distributionId,
                'qualification_id' => $qualification['id'],
                'user_id' => $user->id,
                'distributed_items' => $distributionData['items'] ?? [],
                'distribution_timestamp' => Carbon::now()->toDateTimeString(),
                'confirmation' => $confirmationData,
                'error' => null,
            ];

            $this->log->info('Distribution processed successfully', [
                'user' => $user->name,
                'user_id' => $user->id,
                'distribution_id' => $distributionId,
                'items_distributed' => count($distributionData['items'] ?? []),
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->log->error('Failed to process distribution', [
                'user' => $user->name,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'reason' => $e->getMessage(),
                'distribution_id' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get distribution statistics and metrics.
     *
     * @param array $criteria Filter criteria for statistics
     * @return array Distribution statistics
     */
    public function getDistributionStatistics(array $criteria = []): array
    {
        $this->log->info('Generating distribution statistics', [
            'criteria' => $criteria,
        ]);

        // This would typically query a distributions table
        // For now, returning mock statistics structure
        $statistics = [
            'total_qualified_users' => $this->getQualifiedUsersCount($criteria),
            'total_distributed_users' => $this->getDistributedUsersCount($criteria),
            'pending_distributions' => $this->getPendingDistributionsCount($criteria),
            'distribution_rate' => 0.0,
            'item_statistics' => $this->getItemDistributionStatistics($criteria),
            'daily_distribution_rates' => $this->getDailyDistributionRates($criteria),
            'distribution_locations' => $this->getDistributionLocationStats($criteria),
            'expiring_qualifications' => $this->getExpiringQualifications($criteria),
            'generated_at' => Carbon::now()->toDateTimeString(),
        ];

        // Calculate distribution rate
        if ($statistics['total_qualified_users'] > 0) {
            $statistics['distribution_rate'] = round(
                ($statistics['total_distributed_users'] / $statistics['total_qualified_users']) * 100,
                2
            );
        }

        $this->log->info('Distribution statistics generated', [
            'total_qualified' => $statistics['total_qualified_users'],
            'total_distributed' => $statistics['total_distributed_users'],
            'distribution_rate' => $statistics['distribution_rate'],
        ]);

        return $statistics;
    }

    /**
     * Get user's qualification status and details.
     *
     * @param User $user The user to check
     * @return array|null Qualification details or null if not found
     */
    public function getUserQualification(User $user): ?array
    {
        // This would typically query a user_qualifications table
        // For now, returning a mock structure
        // In real implementation, this would be:
        // return UserQualification::where('user_id', $user->id)->first()?->toArray();

        return null; // Mock implementation - no existing qualifications
    }

    /**
     * Get user's distribution history.
     *
     * @param User $user The user to get history for
     * @return array Distribution history
     */
    public function getUserDistributionHistory(User $user): array
    {
        $this->log->info('Retrieving user distribution history', [
            'user' => $user->name,
            'user_id' => $user->id,
        ]);

        // This would typically query a distributions table
        // For now, returning mock structure
        return [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'distributions' => [], // Mock - no distributions
            'total_distributions' => 0,
            'last_distribution' => null,
            'current_qualification' => $this->getUserQualification($user),
        ];
    }

    /**
     * Cancel a user's qualification.
     *
     * @param User $user The user whose qualification to cancel
     * @param string $reason Reason for cancellation
     * @return array Cancellation result
     */
    public function cancelUserQualification(User $user, string $reason): array
    {
        $this->log->info('Cancelling user qualification', [
            'user' => $user->name,
            'user_id' => $user->id,
            'reason' => $reason,
        ]);

        try {
            $qualification = $this->getUserQualification($user);

            if (!$qualification) {
                throw new RuntimeException('User has no qualification to cancel');
            }

            if ($qualification['status'] === 'distributed') {
                throw new RuntimeException('Cannot cancel qualification - items already distributed');
            }

            // Update qualification status
            $this->updateQualificationStatus($qualification['id'], 'cancelled', $reason);

            // Release any reserved inventory
            $this->releaseReservedInventory($qualification);

            $result = [
                'success' => true,
                'qualification_id' => $qualification['id'],
                'previous_status' => $qualification['status'],
                'cancellation_reason' => $reason,
                'cancelled_at' => Carbon::now()->toDateTimeString(),
            ];

            $this->log->info('User qualification cancelled successfully', [
                'user' => $user->name,
                'user_id' => $user->id,
                'qualification_id' => $qualification['id'],
                'reason' => $reason,
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->log->error('Failed to cancel user qualification', [
                'user' => $user->name,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'reason' => $e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Process expired qualifications.
     *
     * @param int $batchSize Number of qualifications to process at once
     * @return array Processing result
     */
    public function processExpiredQualifications(int $batchSize = 100): array
    {
        $this->log->info('Processing expired qualifications', [
            'batch_size' => $batchSize,
        ]);

        try {
            // This would typically query expired qualifications
            $expiredQualifications = $this->getExpiredQualifications($batchSize);

            $processed = 0;
            $errors = [];

            foreach ($expiredQualifications as $qualification) {
                try {
                    $this->updateQualificationStatus($qualification['id'], 'expired', 'Automatically expired');
                    $this->releaseReservedInventory($qualification);
                    $processed++;
                } catch (\Exception $e) {
                    $errors[] = [
                        'qualification_id' => $qualification['id'],
                        'error' => $e->getMessage(),
                    ];
                }
            }

            $result = [
                'total_expired' => count($expiredQualifications),
                'processed_successfully' => $processed,
                'errors' => $errors,
                'processing_timestamp' => Carbon::now()->toDateTimeString(),
            ];

            $this->log->info('Expired qualifications processing completed', [
                'total_processed' => $processed,
                'errors_count' => count($errors),
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->log->error('Failed to process expired qualifications', [
                'error' => $e->getMessage(),
            ]);

            return [
                'total_expired' => 0,
                'processed_successfully' => 0,
                'errors' => [['general_error' => $e->getMessage()]],
                'processing_timestamp' => Carbon::now()->toDateTimeString(),
            ];
        }
    }

    /**
     * Validate qualification data.
     *
     * @param array $data Qualification data to validate
     * @throws InvalidArgumentException if data is invalid
     */
    protected function validateQualificationData(array $data): void
    {
        // Basic validation - in real implementation would be more comprehensive
        if (isset($data['expires_at'])) {
            $expiryDate = Carbon::parse($data['expires_at']);
            if ($expiryDate->isPast()) {
                throw new InvalidArgumentException('Qualification expiry date cannot be in the past');
            }
        }

        if (isset($data['items'])) {
            foreach ($data['items'] as $item) {
                if (!isset($item['type']) || !in_array($item['type'], self::VALID_TYPES)) {
                    throw new InvalidArgumentException('Invalid item type: ' . ($item['type'] ?? 'missing'));
                }
            }
        }
    }

    /**
     * Validate distribution data.
     *
     * @param array $data Distribution data to validate
     * @throws InvalidArgumentException if data is invalid
     */
    protected function validateDistributionData(array $data): void
    {
        if (empty($data['items'])) {
            throw new InvalidArgumentException('No items specified for distribution');
        }

        foreach ($data['items'] as $item) {
            if (!isset($item['type']) || !in_array($item['type'], self::VALID_TYPES)) {
                throw new InvalidArgumentException('Invalid item type: ' . ($item['type'] ?? 'missing'));
            }

            if (!isset($item['quantity']) || $item['quantity'] <= 0) {
                throw new InvalidArgumentException('Invalid item quantity: ' . ($item['quantity'] ?? 'missing'));
            }
        }

        if (isset($data['location']) && empty($data['location'])) {
            throw new InvalidArgumentException('Distribution location cannot be empty');
        }
    }

    // Mock methods for database operations - in real implementation these would interact with actual models

    protected function createQualificationRecord(User $user, array $data): string
    {
        // Mock implementation - would create actual database record
        return 'qual_' . $user->id . '_' . time();
    }

    protected function createDistributionRecord(User $user, array $data, array $qualification): string
    {
        // Mock implementation - would create actual database record
        return 'dist_' . $user->id . '_' . time();
    }

    protected function updateQualificationStatus(string $qualificationId, string $status, ?string $reason = null): void
    {
        // Mock implementation - would update actual database record
        $this->log->debug('Updating qualification status', [
            'qualification_id' => $qualificationId,
            'new_status' => $status,
            'reason' => $reason,
        ]);
    }

    protected function updateInventoryForQualification(array $data): void
    {
        // Mock implementation - would update inventory records
    }

    protected function updateInventoryForDistribution(array $data): void
    {
        // Mock implementation - would update inventory records
    }

    protected function validateInventoryAvailability(array $data): void
    {
        // Mock implementation - would check inventory levels
    }

    protected function releaseReservedInventory(array $qualification): void
    {
        // Mock implementation - would release reserved inventory
    }

    protected function calculateQualificationExpiry(array $data): ?string
    {
        if (isset($data['expires_at'])) {
            return Carbon::parse($data['expires_at'])->toDateTimeString();
        }

        // Default expiry - 30 days from now
        return Carbon::now()->addDays(30)->toDateTimeString();
    }

    protected function generateDistributionConfirmation(User $user, array $data, string $distributionId): array
    {
        return [
            'confirmation_number' => 'CONF_' . $distributionId,
            'user_name' => $user->name,
            'items' => $data['items'] ?? [],
            'distribution_date' => Carbon::now()->toDateString(),
            'location' => $data['location'] ?? 'Main Distribution Point',
        ];
    }

    // Mock statistics methods

    public function getQualifiedUsersCount(array $criteria = []): int
    {
        return 32; // Mock implementation - return mock count
    }

    protected function getDistributedUsersCount(array $criteria): int
    {
        return 0; // Mock implementation
    }

    protected function getPendingDistributionsCount(array $criteria): int
    {
        return 0; // Mock implementation
    }

    protected function getItemDistributionStatistics(array $criteria): array
    {
        return []; // Mock implementation
    }

    protected function getDailyDistributionRates(array $criteria): array
    {
        return []; // Mock implementation
    }

    protected function getDistributionLocationStats(array $criteria): array
    {
        return []; // Mock implementation
    }

    protected function getExpiringQualifications(array $criteria): array
    {
        return []; // Mock implementation
    }

    protected function getExpiredQualifications(int $limit): array
    {
        return []; // Mock implementation
    }

    // Public methods for controller integration

    public function getRecentDistributionActivity(int $limit = 10): array
    {
        // Mock implementation - would query recent distribution records
        return [];
    }

//    public function getDistributionStatistics(array $filters = []): array
//    {
//        return [
//            'total_distributions' => 87,
//            'distributions_today' => 12,
//            'distributions_week' => 45,
//            'average_items_per_distribution' => 3.2,
//        ];
//    }

    public function getDistributionsCount(\Carbon\Carbon $startDate, \Carbon\Carbon $endDate): int
    {
        // Mock implementation - would count distributions in date range
        return rand(5, 25);
    }

    public function getTotalDistributionsCount(): int
    {
        return 87; // Mock total
    }

    public function getQualifiedUsersPaginated(int $page, int $perPage, array $filters = []): array
    {
        return [
            'data' => [],
            'total' => 32,
            'total_pages' => (int) ceil(32 / $perPage),
        ];
    }

    public function getDistributedUsersPaginated(int $page, int $perPage, array $filters = []): array
    {
        return [
            'data' => [],
            'total' => 87,
            'total_pages' => (int) ceil(87 / $perPage),
        ];
    }

    public function isUserQualified(User $user): bool
    {
        // Mock implementation - would check if user has valid qualification
        return rand(0, 1) === 1;
    }

    public function distributeToUser(User $user, array $itemIds, User $distributor, ?string $notes = null): object
    {
        // Mock implementation - would create distribution record
        $this->log->info('Mock distribution created', [
            'user' => $user->name,
            'distributor' => $distributor->name,
            'items' => $itemIds,
            'notes' => $notes,
        ]);

        return (object) [
            'id' => rand(1000, 9999),
            'user_id' => $user->id,
            'distributor_id' => $distributor->id,
            'items' => $itemIds,
            'notes' => $notes,
            'created_at' => Carbon::now(),
        ];
    }

    public function qualifyUser(User $user, User $agent, ?string $notes = null): object
    {
        // Mock implementation - would create qualification record
        $this->log->info('Mock qualification created', [
            'user' => $user->name,
            'agent' => $agent->name,
            'notes' => $notes,
        ]);

        return (object) [
            'id' => rand(1000, 9999),
            'user_id' => $user->id,
            'agent_id' => $agent->id,
            'notes' => $notes,
            'created_at' => Carbon::now(),
        ];
    }

    public function getDistributionHistoryPaginated(int $page, int $perPage, array $filters = []): array
    {
        return [
            'data' => [],
            'total' => 87,
            'total_pages' => (int) ceil(87 / $perPage),
        ];
    }

    public function searchQualifiedUsers(array $filters = []): array
    {
        // Mock implementation
        return [];
    }

    public function hasUserReceivedDistribution(User $user): bool
    {
        // Mock implementation
        return rand(0, 1) === 1;
    }

    /**
     * Distribute a specific goodie item to a user.
     * This method handles the actual distribution process including validation,
     * inventory management, and record creation.
     *
     * @param User $user The user receiving the goodie
     * @param \Engelsystem\Models\GoodiesV2Item $item The goodie item to distribute
     * @param int $quantity Number of items to distribute
     * @param string|null $notes Optional notes about the distribution
     * @return array Distribution result with success status and details
     */
    public function distributeItem(
        User $user,
        \Engelsystem\Models\GoodiesV2Item $item,
        int $quantity = 1,
        ?string $notes = null
    ): array {
        $this->log->info('Starting goodie item distribution', [
            'user' => $user->name,
            'user_id' => $user->id,
            'item_id' => $item->id,
            'item_name' => $item->name,
            'quantity' => $quantity,
            'notes' => $notes,
            'distributor' => auth()->user()?->name,
            'distributor_id' => auth()->user()?->id,
        ]);

        try {
            // Validate quantity
            if ($quantity <= 0) {
                return [
                    'success' => false,
                    'reason' => 'Quantity must be greater than 0',
                    'error_code' => 'INVALID_QUANTITY',
                ];
            }

            // Check if item is active
            if (!$item->is_active) {
                return [
                    'success' => false,
                    'reason' => 'Item is not active for distribution',
                    'error_code' => 'ITEM_INACTIVE',
                ];
            }

            // Check max per person limit if set
            if ($item->max_per_person !== null && $item->max_per_person > 0) {
                $existingDistributions = \Engelsystem\Models\GoodiesV2Distribution::where('user_id', $user->id)
                    ->where('item_id', $item->id)
                    ->sum('quantity');

                if (($existingDistributions + $quantity) > $item->max_per_person) {
                    return [
                        'success' => false,
                        'reason' => 'Distribution would exceed maximum per person limit ('
                            . $item->max_per_person . ')',
                        'error_code' => 'EXCEEDS_LIMIT',
                        'current_count' => $existingDistributions,
                        'max_allowed' => $item->max_per_person,
                        'requested' => $quantity,
                    ];
                }
            }

            // Get user's current hours for audit trail
            $userCurrentHours = 0;
            try {
                // Check if HoursCalculationService is available via container
                $hoursService = app(\Engelsystem\Services\HoursCalculationService::class);
                $hoursData = $hoursService->calculateGoodiesHours($user);
                $userCurrentHours = (int) round($hoursData['total_hours']);
            } catch (\Exception $e) {
                $this->log->warning('Could not calculate user hours for distribution audit', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Create distribution record
            $distribution = \Engelsystem\Models\GoodiesV2Distribution::create([
                'user_id' => $user->id,
                'item_id' => $item->id,
                'quantity' => $quantity,
                'hours_at_distribution' => $userCurrentHours,
                'distributed_by' => auth()->user()?->id ?? 1, // Fallback for system
                'distributed_at' => Carbon::now(),
                'notes' => $notes,
            ]);

            $this->log->info('Goodie item distributed successfully', [
                'distribution_id' => $distribution->id,
                'user' => $user->name,
                'user_id' => $user->id,
                'item_id' => $item->id,
                'item_name' => $item->name,
                'quantity' => $quantity,
                'distributor' => auth()->user()?->name,
                'distributor_id' => auth()->user()?->id,
            ]);

            return [
                'success' => true,
                'distribution_id' => $distribution->id,
                'item_name' => $item->name,
                'quantity' => $quantity,
                'distributed_at' => $distribution->distributed_at->toDateTimeString(),
                'distributor' => auth()->user()?->name ?? 'System',
                'notes' => $notes,
            ];
        } catch (\Exception $e) {
            $this->log->error('Error distributing goodie item', [
                'user' => $user->name,
                'user_id' => $user->id,
                'item_id' => $item->id,
                'item_name' => $item->name,
                'quantity' => $quantity,
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'reason' => 'Distribution failed due to system error',
                'error_code' => 'SYSTEM_ERROR',
                'error_message' => $e->getMessage(),
            ];
        }
    }
}
