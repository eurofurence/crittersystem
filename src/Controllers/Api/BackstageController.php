<?php

declare(strict_types=1);

namespace Engelsystem\Controllers\Api;

use Engelsystem\Controllers\BaseController;
use Engelsystem\Controllers\Api\UsesAuth;
use Engelsystem\Helpers\BackstagePermissionHelper;
use Engelsystem\Http\Exceptions\HttpForbidden;
use Engelsystem\Http\Request;
use Engelsystem\Http\Response;
use Engelsystem\Services\GoodiesService;
use Engelsystem\Services\HoursCalculationService;
use Engelsystem\Services\GoodiesDistributionService;
use Engelsystem\Services\UserSearchService;
use Psr\Log\LoggerInterface;
use Carbon\Carbon;

class BackstageController extends BaseController
{
    use UsesAuth;

    public function __construct(
        protected LoggerInterface $log,
        protected GoodiesService $goodiesService,
        protected HoursCalculationService $hoursService,
        protected GoodiesDistributionService $distributionService,
        protected UserSearchService $userSearchService,
        protected Response $response
    ) {
    }

    /**
     * Get real-time KPIs for dashboard.
     */
    public function kpis(Request $request): Response
    {
        $this->checkBackstageApiAccess();

        try {
            $kpis = $this->collectSystemKpis();

            return $this->response->withJson([
                'success' => true,
                'data' => $kpis,
                'meta' => [
                    'timestamp' => Carbon::now()->toISOString(),
                    'cache_expires' => Carbon::now()->addMinutes(5)->toISOString(),
                ],
            ]);
        } catch (\Exception $e) {
            $this->log->error('Error fetching backstage KPIs via API', [
                'user' => $this->auth->user()?->name,
                'user_id' => $this->auth->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return $this->response->withJson([
                'success' => false,
                'error' => 'Failed to fetch KPIs',
                'meta' => [
                    'timestamp' => Carbon::now()->toISOString(),
                ],
            ], 500);
        }
    }

    /**
     * Get system statistics.
     */
    public function stats(Request $request): Response
    {
        $this->checkBackstageApiAccess();

        try {
            // TODO: Implement when services are ready
            $stats = [
                'users_total' => 0,
                'users_active' => 0,
                'goodies_categories' => 0,
                'goodies_items' => 0,
                'distributions_total' => 0,
            ];

            return $this->response->withJson([
                'success' => true,
                'data' => $stats,
                'meta' => [
                    'timestamp' => Carbon::now()->toISOString(),
                ],
            ]);
        } catch (\Exception $e) {
            $this->log->error('Error fetching backstage stats via API', [
                'user' => $this->auth->user()?->name,
                'user_id' => $this->auth->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return $this->response->withJson([
                'success' => false,
                'error' => 'Failed to fetch statistics',
            ], 500);
        }
    }

    /**
     * Search users via API.
     */
    public function searchUsers(Request $request): Response
    {
        $this->checkBackstageApiAccess();

        $searchTerm = $request->get('q', '');
        $hoursMin = (int) $request->get('hours_min', 0);
        $hoursMax = (int) $request->get('hours_max', 999999);
        $limit = min((int) $request->get('limit', 20), 100);

        try {
            // TODO: Implement when UserSearchService is ready
            $results = [];

            return $this->response->withJson([
                'success' => true,
                'data' => $results,
                'meta' => [
                    'total' => count($results),
                    'limit' => $limit,
                    'search_term' => $searchTerm,
                    'hours_range' => [$hoursMin, $hoursMax],
                    'timestamp' => Carbon::now()->toISOString(),
                ],
            ]);
        } catch (\Exception $e) {
            $this->log->error('Error searching users via API', [
                'user' => $this->auth->user()?->name,
                'user_id' => $this->auth->user()?->id,
                'search_term' => $searchTerm,
                'error' => $e->getMessage(),
            ]);

            return $this->response->withJson([
                'success' => false,
                'error' => 'Search failed',
            ], 500);
        }
    }

    /**
     * Get user hours breakdown.
     */
    public function userHours(Request $request): Response
    {
        $this->checkBackstageApiAccess();

        $userId = (int) $request->getAttribute('user_id');

        try {
            // Find the user
            $user = \Engelsystem\Models\User\User::findOrFail($userId);

            // Get detailed user information
            $userDetails = $this->userSearchService->getUserDetails($user);

            // Calculate user hours with detailed breakdown
            $hoursData = $this->hoursService->calculateUserHours($user);

            // Get recent shift entries for display
            $recentShifts = $user->shiftEntries()
                ->with(['shift', 'angelType'])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($entry) {
                    $shift = $entry->shift;
                    return [
                        'id' => $entry->id,
                        'shift_title' => $shift->title ?? 'Unknown Shift',
                        'angel_type' => $entry->angelType->name ?? 'Unknown Type',
                        'start' => $shift->start?->format('Y-m-d H:i'),
                        'end' => $shift->end?->format('Y-m-d H:i'),
                        'duration' => $shift->start && $shift->end ?
                            $shift->start->diffInHours($shift->end, true) : 0,
                        'is_completed' => $shift->end ? Carbon::now()->gt($shift->end) : false,
                        'is_night_shift' => $shift->isNightShift ?? false,
                    ];
                });

            // Combine all data
            $responseData = array_merge($userDetails, [
                'hours_calculation' => $hoursData,
                'recent_shifts' => $recentShifts,
            ]);

            $this->log->info('User hours fetched successfully via API', [
                'user' => $this->auth->user()?->name,
                'user_id' => $this->auth->user()?->id,
                'target_user_id' => $userId,
                'total_hours' => $hoursData['total_hours'],
            ]);

            return $this->response->withJson([
                'success' => true,
                'data' => $responseData,
                'meta' => [
                    'timestamp' => Carbon::now()->toISOString(),
                ],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $this->log->warning('User not found via hours API', [
                'user' => $this->auth->user()?->name,
                'user_id' => $this->auth->user()?->id,
                'target_user_id' => $userId,
            ]);

            return $this->response->withJson([
                'success' => false,
                'error' => 'User not found',
            ], 404);
        } catch (\Exception $e) {
            $this->log->error('Error fetching user hours via API', [
                'user' => $this->auth->user()?->name,
                'user_id' => $this->auth->user()?->id,
                'target_user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->response->withJson([
                'success' => false,
                'error' => 'Failed to fetch user hours',
            ], 500);
        }
    }

    /**
     * Get user eligibility for goodies.
     */
    public function userEligibility(Request $request): Response
    {
        $this->checkBackstageApiAccess();

        $userId = (int) $request->getAttribute('user_id');

        try {
            // Find the user
            $user = \Engelsystem\Models\User\User::findOrFail($userId);

            // Check user eligibility for goodies
            $eligibilityCheck = $this->goodiesService->checkUserEligibility($user);

            // Get available goodies items that user might be eligible for
            $availableItems = [];
            try {
                // Get all active goodies items
                $allItems = \Engelsystem\Models\GoodiesV2Item::where('is_active', true)
                    ->with(['category'])
                    ->orderBy('hours_required', 'asc')
                    ->get();

                foreach ($allItems as $item) {
                    $itemEligibility = $this->goodiesService->checkItemEligibility($user, $item);
                    $availableItems[] = [
                        'id' => $item->id,
                        'name' => $item->name,
                        'description' => $item->description,
                        'category' => $item->category?->name ?? 'Uncategorized',
                        'hours_required' => $item->hours_required,
                        'is_eligible' => $itemEligibility['is_eligible'],
                        'reason' => $itemEligibility['reason'] ?? null,
                        'user_hours' => $itemEligibility['user_hours'] ?? 0,
                        'hours_deficit' => $itemEligibility['hours_deficit'] ?? 0,
                    ];
                }
            } catch (\Exception $e) {
                $this->log->warning('Error fetching goodies items for eligibility check', [
                    'error' => $e->getMessage(),
                ]);
                // Continue with empty items array
            }

            // Get user's past distributions
            $pastDistributions = [];
            try {
                $distributions = \Engelsystem\Models\GoodiesV2Distribution::where('user_id', $userId)
                    ->with(['item', 'distributedBy'])
                    ->orderBy('distributed_at', 'desc')
                    ->limit(20)
                    ->get();

                $pastDistributions = $distributions->map(function ($dist) {
                    return [
                        'id' => $dist->id,
                        'item_name' => $dist->item?->name ?? 'Unknown Item',
                        'distributed_at' => $dist->distributed_at?->format('Y-m-d H:i'),
                        'distributed_by' => $dist->distributedBy?->name ?? 'Unknown',
                        'notes' => $dist->notes,
                    ];
                })->toArray();
            } catch (\Exception $e) {
                $this->log->warning('Error fetching past distributions', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
                // Continue with empty distributions
            }

            $eligibilityData = [
                'user_id' => $userId,
                'is_eligible' => $eligibilityCheck['is_eligible'] ?? false,
                'total_hours' => $eligibilityCheck['total_hours'] ?? 0,
                'minimum_hours_required' => $eligibilityCheck['minimum_hours_required'] ?? 12.0,
                'hours_deficit' => $eligibilityCheck['hours_deficit'] ?? 0,
                'reason' => $eligibilityCheck['reason'] ?? 'Unknown',
                'available_items' => $availableItems,
                'past_distributions' => $pastDistributions,
                'can_distribute' => \Engelsystem\Helpers\BackstagePermissionHelper::canDistributeGoodies(),
            ];

            $this->log->info('User eligibility fetched successfully via API', [
                'user' => $this->auth->user()?->name,
                'user_id' => $this->auth->user()?->id,
                'target_user_id' => $userId,
                'is_eligible' => $eligibilityData['is_eligible'],
                'total_hours' => $eligibilityData['total_hours'],
            ]);

            return $this->response->withJson([
                'success' => true,
                'data' => $eligibilityData,
                'meta' => [
                    'timestamp' => Carbon::now()->toISOString(),
                ],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $this->log->warning('User not found via eligibility API', [
                'user' => $this->auth->user()?->name,
                'user_id' => $this->auth->user()?->id,
                'target_user_id' => $userId,
            ]);

            return $this->response->withJson([
                'success' => false,
                'error' => 'User not found',
            ], 404);
        } catch (\Exception $e) {
            $this->log->error('Error fetching user eligibility via API', [
                'user' => $this->auth->user()?->name,
                'user_id' => $this->auth->user()?->id,
                'target_user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->response->withJson([
                'success' => false,
                'error' => 'Failed to fetch user eligibility',
            ], 500);
        }
    }

    /**
     * Get goodies categories via API.
     */
    public function goodiesCategories(Request $request): Response
    {
        $this->checkBackstageApiAccess();

        try {
            // TODO: Implement when GoodiesService is ready
            $categories = [];

            return $this->response->withJson([
                'success' => true,
                'data' => $categories,
                'meta' => [
                    'total' => count($categories),
                    'timestamp' => Carbon::now()->toISOString(),
                ],
            ]);
        } catch (\Exception $e) {
            $this->log->error('Error fetching categories via API', [
                'user' => $this->auth->user()?->name,
                'user_id' => $this->auth->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return $this->response->withJson([
                'success' => false,
                'error' => 'Failed to fetch categories',
            ], 500);
        }
    }

    /**
     * Get goodies items via API.
     */
    public function goodiesItems(Request $request): Response
    {
        $this->checkBackstageApiAccess();

        $categoryId = $request->get('category_id');

        try {
            // TODO: Implement when GoodiesService is ready
            $items = [];

            return $this->response->withJson([
                'success' => true,
                'data' => $items,
                'meta' => [
                    'total' => count($items),
                    'category_id' => $categoryId,
                    'timestamp' => Carbon::now()->toISOString(),
                ],
            ]);
        } catch (\Exception $e) {
            $this->log->error('Error fetching items via API', [
                'user' => $this->auth->user()?->name,
                'user_id' => $this->auth->user()?->id,
                'category_id' => $categoryId,
                'error' => $e->getMessage(),
            ]);

            return $this->response->withJson([
                'success' => false,
                'error' => 'Failed to fetch items',
            ], 500);
        }
    }

    /**
     * Get distribution history via API.
     */
    public function distributions(Request $request): Response
    {
        $this->checkBackstageApiAccess();

        $limit = min((int) $request->get('limit', 50), 200);
        $offset = max((int) $request->get('offset', 0), 0);

        try {
            // TODO: Implement when GoodiesDistributionService is ready
            $distributions = [];

            return $this->response->withJson([
                'success' => true,
                'data' => $distributions,
                'meta' => [
                    'total' => count($distributions),
                    'limit' => $limit,
                    'offset' => $offset,
                    'timestamp' => Carbon::now()->toISOString(),
                ],
            ]);
        } catch (\Exception $e) {
            $this->log->error('Error fetching distributions via API', [
                'user' => $this->auth->user()?->name,
                'user_id' => $this->auth->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return $this->response->withJson([
                'success' => false,
                'error' => 'Failed to fetch distributions',
            ], 500);
        }
    }

    /**
     * Get live statistics for real-time updates.
     */
    public function liveStats(Request $request): Response
    {
        $this->checkBackstageApiAccess();

        try {
            $stats = [
                'kpis' => $this->collectSystemKpis(),
                'activity' => [], // Recent activity
                'system_status' => $this->getSystemStatus(),
                'timestamp' => Carbon::now()->toISOString(),
            ];

            return $this->response->withJson([
                'success' => true,
                'data' => $stats,
                'meta' => [
                    'refresh_interval' => 30,
                    'next_update' => Carbon::now()->addSeconds(30)->toISOString(),
                ],
            ]);
        } catch (\Exception $e) {
            $this->log->error('Error fetching live stats via API', [
                'user' => $this->auth->user()?->name,
                'user_id' => $this->auth->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return $this->response->withJson([
                'success' => false,
                'error' => 'Failed to fetch live statistics',
            ], 500);
        }
    }

    /**
     * Collect system KPIs (shared with main controller).
     */
    protected function collectSystemKpis(): array
    {
        // TODO: Implement when services are ready
        return [
            'eligible_users' => 0,
            'qualified_users' => 0,
            'distributions_today' => 0,
            'distributions_week' => 0,
            'total_hours_calculated' => 0,
            'pending_recalculations' => 0,
        ];
    }

    /**
     * Get system status (shared with main controller).
     */
    protected function getSystemStatus(): array
    {
        return [
            'hours_service' => 'pending', // Services not yet implemented
            'distribution_service' => 'pending',
            'goodies_service' => 'pending',
            'database' => 'operational',
        ];
    }

    /**
     * Check API access permissions.
     */
    protected function checkBackstageApiAccess(): void
    {
        $user = $this->auth->user();

        if (!$user) {
            throw new HttpForbidden('Authentication required');
        }

        if (!BackstagePermissionHelper::hasViewAccess($user)) {
            $this->log->warning('Insufficient permissions for backstage API access', [
                'user' => $user->name,
                'user_id' => $user->id,
                'user_permissions' => $user->privileges->pluck('name')->toArray(),
                'ip_address' => request()->getClientIp(),
            ]);
            throw new HttpForbidden('Insufficient permissions');
        }
    }
}
