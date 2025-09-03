<?php

declare(strict_types=1);

namespace Engelsystem\Controllers;

use Engelsystem\Controllers\BaseController;
use Engelsystem\Controllers\HasUserNotifications;
use Engelsystem\Controllers\NotificationType;
use Engelsystem\Helpers\BackstagePermissionHelper;
use Engelsystem\Http\Exceptions\HttpForbidden;
use Engelsystem\Http\Redirector;
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
    use HasUserNotifications;

    /** @var array<string> */
    protected array $permissions = [
        'user.type.admin',
//        'backstage.view',
//        'backstage.admin',
    ];

    public function __construct(
        protected LoggerInterface $log,
        protected GoodiesService $goodiesService,
        protected HoursCalculationService $hoursService,
        protected GoodiesDistributionService $distributionService,
        protected UserSearchService $userSearchService,
        protected Response $response,
        protected Redirector $redirect
    ) {
    }

    /**
     * Display the main backstage dashboard with real-time KPIs.
     */
    public function dashboard(Request $request): Response
    {
        $this->checkBackstagePermission('view');

        try {
            // Get system KPIs
            $kpis = $this->collectSystemKpis();

            // Get recent activity
            $recentActivity = $this->distributionService->getRecentDistributionActivity(10);

            // Get hours calculation summary
            $hoursStats = $this->hoursService->getCalculationSummary();

            // Get distribution statistics
            $distributionStats = $this->distributionService->getDistributionStatistics();

            // System status indicators
            $systemStatus = $this->getSystemStatus();

            $this->log->info('Backstage dashboard accessed', [
                'user' => auth()->user()->name,
                'user_id' => auth()->user()->id,
                'kpis' => array_keys($kpis),
                'timestamp' => Carbon::now()->toISOString(),
            ]);

            return $this->response->withView(
                'backstage/dashboard',
                [
                    'user' => auth()->user(),
                    'kpis' => $kpis,
                    'recent_activity' => $recentActivity,
                    'hours_stats' => $hoursStats,
                    'distribution_stats' => $distributionStats,
                    'system_status' => $systemStatus,
                    'refresh_interval' => 30000, // 30 seconds for real-time updates
                    'can_admin' => BackstagePermissionHelper::hasAdminAccess(),
                    'can_manage_goodies' => BackstagePermissionHelper::canManageGoodies(),
                    'can_distribute' => BackstagePermissionHelper::canDistributeGoodies(),
                ]
            );
        } catch (\Exception $e) {
            $this->log->error('Error loading backstage dashboard - {error} - stack trace: {trace}', [
                'user' => auth()->user()->name,
                'user_id' => auth()->user()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addNotification('backstage.dashboard.error', NotificationType::ERROR);

            return $this->response->withView(
                'backstage/dashboard',
                [
                    'user' => auth()->user(),
                    'error' => true,
                    'kpis' => [],
                    'can_admin' => BackstagePermissionHelper::hasAdminAccess(),
                ]
            );
        }
    }

    /**
     * API endpoint for real-time KPI data (used by dashboard auto-refresh).
     */
    public function getKpis(Request $request): Response
    {
        $this->checkBackstagePermission('view');

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
            $this->log->error('Error fetching backstage KPIs - {error} - stack trace: {trace}', [
                'user' => auth()->user()->name,
                'user_id' => auth()->user()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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
     * API endpoint for refreshing dashboard data.
     */
    public function refreshData(Request $request): Response
    {
        $this->checkBackstagePermission('view');

        try {
            // Get fresh data
            $kpis = $this->collectSystemKpis();
            $recentActivity = $this->distributionService->getRecentDistributionActivity(10);
            $systemStatus = $this->getSystemStatus();

            return $this->response->withJson([
                'success' => true,
                'data' => [
                    'kpis' => $kpis,
                    'recent_activity' => $recentActivity,
                    'system_status' => $systemStatus,
                ],
                'meta' => [
                    'timestamp' => Carbon::now()->toISOString(),
                    'next_refresh' => Carbon::now()->addSeconds(30)->toISOString(),
                ],
            ]);
        } catch (\Exception $e) {
            $this->log->error('Error refreshing backstage data - {error} - stack trace: {trace}', [
                'user' => auth()->user()->name,
                'user_id' => auth()->user()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->response->withJson([
                'success' => false,
                'error' => 'Failed to refresh data',
                'meta' => [
                    'timestamp' => Carbon::now()->toISOString(),
                ],
            ], 500);
        }
    }

    /**
     * Collect system-wide KPIs for the dashboard.
     */
    protected function collectSystemKpis(): array
    {
        // Get eligible users count
        $eligibleUsers = $this->goodiesService->getEligibleUsersCount();

        // Get qualified users awaiting distribution
        $qualifiedUsers = $this->distributionService->getQualifiedUsersCount();

        // Get total distributions today
        $distributionsToday = $this->distributionService->getDistributionsCount(
            Carbon::today(),
            Carbon::tomorrow()
        );

        // Get total distributions this week
        $distributionsWeek = $this->distributionService->getDistributionsCount(
            Carbon::now()->startOfWeek(),
            Carbon::now()->endOfWeek()
        );

        // Get hours calculation statistics
        $totalHoursCalculated = $this->hoursService->getTotalHoursCalculated();

        // Get pending hours recalculations
        $pendingRecalculations = $this->hoursService->getPendingRecalculationsCount();

        return [
            'eligible_users' => $eligibleUsers,
            'qualified_users' => $qualifiedUsers,
            'distributions_today' => $distributionsToday,
            'distributions_week' => $distributionsWeek,
            'total_hours_calculated' => round($totalHoursCalculated, 1),
            'pending_recalculations' => $pendingRecalculations,
        ];
    }

    /**
     * Get system status indicators.
     */
    protected function getSystemStatus(): array
    {
        $status = [
            'hours_service' => 'operational',
            'distribution_service' => 'operational',
            'goodies_service' => 'operational',
            'database' => 'operational',
        ];

        // Test each service
        try {
            $this->hoursService->getCalculationSummary();
        } catch (\Exception $e) {
            $status['hours_service'] = 'degraded';
            $this->log->warning('Hours service status check failed - {error} - stack trace: {trace}', [
                'user' => auth()->user()->name,
                'user_id' => auth()->user()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        try {
            $this->distributionService->getDistributionStatistics();
        } catch (\Exception $e) {
            $status['distribution_service'] = 'degraded';
            $this->log->warning('Distribution service status check failed - {error} - stack trace: {trace}', [
                'user' => auth()->user()->name,
                'user_id' => auth()->user()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        try {
            $this->goodiesService->getSystemKpis();
        } catch (\Exception $e) {
            $status['goodies_service'] = 'degraded';
            $this->log->warning('Goodies service status check failed - {error} - stack trace: {trace}', [
                'user' => auth()->user()->name,
                'user_id' => auth()->user()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        // Check database connectivity
        try {
            \Engelsystem\Models\User\User::count();
        } catch (\Exception $e) {
            $status['database'] = 'error';
            $this->log->error('Database connectivity check failed - {error} - stack trace: {trace}', [
                'user' => auth()->user()->name,
                'user_id' => auth()->user()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return $status;
    }

    /**
     * Check backstage permissions with detailed logging.
     */
    protected function checkBackstagePermission(string $level): void
    {
        $user = auth()->user();

        if (!$user) {
            $this->log->warning(
                'Unauthenticated access attempt to backstage - ' .
                '{ip_address} - {user_agent} - {requested_level}',
                [
                'ip_address' => request()->getClientIp(),
                'user_agent' => request()->getHeaderLine('User-Agent'),
                'requested_level' => $level,
                ]
            );
            throw new HttpForbidden('Authentication required');
        }

        $hasAccess = match ($level) {
            'view' => BackstagePermissionHelper::hasViewAccess($user),
            'admin' => BackstagePermissionHelper::hasAdminAccess($user),
            default => false,
        };

        if (!$hasAccess) {
            $this->log->warning(
                'Insufficient permissions for backstage access - ' .
                '{user} ({user_id}) - {user_permissions} - {ip_address}',
                [
                'user' => $user->name,
                'user_id' => $user->id,
                'requested_level' => $level,
                'user_permissions' => $user->privileges->pluck('name')->toArray(),
                'ip_address' => request()->getClientIp(),
                ]
            );
            throw new HttpForbidden('Insufficient permissions');
        }

        // Log successful access for audit trail
        $this->log->info('Backstage access granted - {user} ({user_id}) - {level}', [
            'user' => $user->name,
            'user_id' => $user->id,
            'level' => $level,
            'timestamp' => Carbon::now()->toISOString(),
        ]);
    }

    /**
     * Display the user search interface.
     */
    public function userSearch(Request $request): Response
    {
        $this->checkBackstagePermission('view');

        return $this->response->withView(
            'backstage/user-search',
            [
                'user' => auth()->user(),
                'can_qualify' => BackstagePermissionHelper::canDistributeGoodies(),
                'can_admin' => BackstagePermissionHelper::hasAdminAccess(),
            ]
        );
    }

    /**
     * Process user search request and return results.
     */
    public function processUserSearch(Request $request): Response
    {
        $this->checkBackstagePermission('view');

        $searchTerm = trim($request->get('search', ''));

        // Basic validation
        if (empty($searchTerm)) {
            $this->addNotification('backstage.search.empty_criteria', NotificationType::WARNING);
            return $this->redirect->to('/admin/backstage/users/search');
        }

        try {
            $this->log->info('Starting user search - {search_term}', [
                'search_term' => $searchTerm,
                'user' => auth()->user()->name,
            ]);

            // Use the UserSearchService to find users
            $users = $this->userSearchService->searchUsersBackstage($searchTerm, 50);

            $this->log->info('Search completed, formatting results', [
                'user_count' => $users->count(),
                'search_term' => $searchTerm,
            ]);

            $results = $this->userSearchService->formatSearchResults($users);

            $this->log->info('User search performed successfully', [
                'user' => auth()->user()->name,
                'user_id' => auth()->user()->id,
                'search_term' => $searchTerm,
                'results_count' => count($results),
            ]);

            return $this->response->withView(
                'backstage/user-search',
                [
                    'user' => auth()->user(),
                    'search_term' => $searchTerm,
                    'results' => $results,
                    'can_qualify' => BackstagePermissionHelper::canDistributeGoodies(),
                    'can_admin' => BackstagePermissionHelper::hasAdminAccess(),
                ]
            );
        } catch (\Exception $e) {
            $this->log->error(
                'Error performing user search with {search_term}: ' .
                '{error} - {error_line} - {error_file} - stack trace: {stack_trace}',
                [
                'user' => auth()->user()->name,
                'user_id' => auth()->user()->id,
                'error' => $e->getMessage(),
                'error_line' => $e->getLine(),
                'error_file' => $e->getFile(),
                'search_term' => $searchTerm,
                'stack_trace' => $e->getTraceAsString(),
                ]
            );

            $this->addNotification('backstage.search.error', NotificationType::ERROR);
            return $this->redirect->to('/admin/backstage/users/search');
        }
    }

    /**
     * Display user qualification interface.
     */
    public function qualifyUser(Request $request): Response
    {
        $this->checkBackstagePermission('view');

        $userId = (int) $request->getAttribute('user_id');

        try {
            // Get user details with all required relationships
            $user = \Engelsystem\Models\User\User::with([
                'state',
                'personalData',
                'contact',
                'certifications',
                'shiftEntries.shift',
            ])->findOrFail($userId);

            // Calculate user hours using the HoursCalculationService
            $hoursData = $this->hoursService->calculateUserHours($user);
            $userHours = $hoursData['total_hours'] ?? 0;

            // Get available goodies for user
            try {
                $eligibilityCheck = $this->goodiesService->checkUserEligibility($user);
                $availableGoodies = $eligibilityCheck['eligible_items'] ?? [];
            } catch (\Exception $e) {
                $this->log->warning('Could not load goodies for user qualification', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
                $availableGoodies = [];
            }

            // Get user's past distributions
            $pastDistributions = [];
            try {
                $distributions = \Engelsystem\Models\GoodiesV2Distribution::where('user_id', $userId)
                    ->with(['item'])
                    ->orderBy('created_at', 'desc')
                    ->limit(20)
                    ->get();

                $pastDistributions = $distributions->map(function ($dist) {
                    return [
                        'item_name' => $dist->item?->name ?? 'Unknown Item',
                        'distributed_at' => $dist->created_at?->format('M j'),
                    ];
                })->toArray();
            } catch (\Exception $e) {
                $this->log->warning('Could not load past distributions for user qualification', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
            }

            return $this->response->withView(
                'backstage/user-qualification',
                [
                    'current_user' => auth()->user(),
                    'target_user' => $user,
                    'user_hours' => $userHours,
                    'available_goodies' => $availableGoodies,
                    'past_distributions' => $pastDistributions,
                    'can_distribute' => BackstagePermissionHelper::canDistributeGoodies(),
                    'can_admin' => BackstagePermissionHelper::hasAdminAccess(),
                ]
            );
        } catch (\Exception $e) {
            $this->log->error(
                'Error loading user qualification for {target_user_id} - {error} - stack trace: {trace}',
                [
                'user' => auth()->user()->name,
                'user_id' => auth()->user()->id,
                'target_user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                ]
            );

            $this->addNotification('backstage.qualification.error', NotificationType::ERROR);
            return $this->redirect->to('/admin/backstage/users/search');
        }
    }

    /**
     * Process user qualification and goodie distribution.
     */
    public function processUserQualification(Request $request): Response
    {
        $this->checkBackstagePermission('view');

        // Must have distribution permissions to qualify users
        if (!BackstagePermissionHelper::canDistributeGoodies()) {
            $this->addNotification('backstage.qualification.insufficient_permissions', NotificationType::ERROR);
            return $this->redirect->back();
        }

        $userId = (int) $request->getAttribute('user_id');
        $goodieIds = $request->get('goodies', []);
        $notes = $request->get('notes', '');

        try {
            // TODO: Implement actual distribution logic when GoodiesDistributionService is ready
            // For now, just log the action

            $this->log->info('User qualification processed', [
                'user' => auth()->user()->name,
                'user_id' => auth()->user()->id,
                'target_user_id' => $userId,
                'goodie_ids' => $goodieIds,
                'notes' => $notes,
            ]);

            $this->addNotification('backstage.qualification.success', NotificationType::MESSAGE);
            return $this->redirect->to('/admin/backstage/users/search');
        } catch (\Exception $e) {
            $this->log->error('Error processing user qualification - {error} - stack trace: {trace}', [
                'user' => auth()->user()->name,
                'user_id' => auth()->user()->id,
                'target_user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),

            ]);

            $this->addNotification('backstage.qualification.error', NotificationType::ERROR);
            return $this->redirect->back();
        }
    }

    /**
     * Display comprehensive user goodies profile page.
     * 
     * This is the main goodies distribution interface that shows:
     * - User information (name, ID, badge, staff status)
     * - Complete shift history until current time
     * - Real-time hours calculation with rule breakdown
     * - All goodies ordered by required hours
     * - Distribution history and delivery status
     * - Distribution forms with quantity management
     */
    public function userGoodiesProfile(Request $request): Response
    {
        $this->checkGoodiesPermission('view');

        $userId = (int) $request->getAttribute('user_id');

        try {
            // Get user with all required relationships
            $user = \Engelsystem\Models\User\User::with([
                'state',
                'personalData', 
                'contact',
                'certifications',
                'shiftEntries.shift',
                'worklogs'
            ])->findOrFail($userId);

            // Get user's shift data until current time
            $currentTime = Carbon::now();
            $userShifts = $user->shiftEntries()
                ->with(['shift'])
                ->whereHas('shift', function ($query) use ($currentTime) {
                    $query->where('end', '<=', $currentTime);
                })
                ->join('shifts', 'shift_entries.shift_id', '=', 'shifts.id')
                ->orderBy('shifts.end', 'desc')
                ->select('shift_entries.*')
                ->get();

            // Calculate hours using enhanced HoursCalculationService with caching
            $hoursData = $this->hoursService->calculateGoodiesHoursWithCache($user);

            // Get ALL goodies ordered by required hours (ascending)
            $allGoodies = $this->goodiesService->getGoodiesItemsPaginated(1, 1000, ['active_only' => true]);
            $goodiesItems = collect($allGoodies['data'])->sortBy('required_hours');

            // Check eligibility and get distribution info for each goodie
            $goodiesWithEligibility = [];
            foreach ($goodiesItems as $goodie) {
                $eligibilityCheck = $this->goodiesService->checkItemEligibility($user, 
                    \Engelsystem\Models\GoodiesV2Item::find($goodie['id']));
                
                // Get distribution summary for this user and goodie
                $distributionSummary = $this->getDistributionSummary($userId, $goodie['id']);

                $goodiesWithEligibility[] = [
                    'goodie' => $goodie,
                    'eligible' => $eligibilityCheck['eligible'],
                    'reasons' => $eligibilityCheck['reasons'] ?? [],
                    'distribution_summary' => $distributionSummary,
                    'max_per_person' => $goodie['max_per_person'],
                    'pre_filled_quantity' => $this->calculatePreFilledQuantity($goodie),
                ];
            }

            // Get complete distribution history
            $distributionHistory = $this->getCompleteDistributionHistory($userId);

            // Determine user badges (staff/critter status)
            $userBadges = $this->getUserBadges($user);

            $this->log->info('User goodies profile accessed', [
                'user' => auth()->user()->name,
                'user_id' => auth()->user()->id,
                'target_user_id' => $userId,
                'target_user_name' => $user->name,
                'total_hours' => $hoursData['total_hours'],
                'total_goodies' => count($goodiesWithEligibility),
            ]);

            return $this->response->withView(
                'backstage/users/goodies-profile',
                [
                    'current_user' => auth()->user(),
                    'target_user' => $user,
                    'user_badges' => $userBadges,
                    'shifts_data' => [
                        'shifts' => $userShifts,
                        'total_shifts' => $userShifts->count(),
                    ],
                    'hours_data' => $hoursData,
                    'goodies_with_eligibility' => $goodiesWithEligibility,
                    'distribution_history' => $distributionHistory,
                    'can_distribute' => BackstagePermissionHelper::canDistributeGoodies(),
                    'can_admin' => BackstagePermissionHelper::hasAdminAccess(),
                ]
            );
        } catch (\Exception $e) {
            $this->log->error('Error loading user goodies profile - {error} - stack trace: {trace}', [
                'user' => auth()->user()->name,
                'user_id' => auth()->user()->id,
                'target_user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addNotification('backstage.goodies.profile.error', NotificationType::ERROR);
            return $this->redirect->to('/admin/backstage/users/search');
        }
    }

    /**
     * Process goodie distribution from user profile page.
     */
    public function processGoodieDistribution(Request $request): Response
    {
        $this->checkGoodiesPermission('view');

        // Must have distribution permissions
        if (!BackstagePermissionHelper::canDistributeGoodies()) {
            $this->addNotification('backstage.goodies.distribute.insufficient_permissions', NotificationType::ERROR);
            return $this->redirect->back();
        }

        $userId = (int) $request->getAttribute('user_id');
        $goodieId = (int) $request->get('goodie_id');
        $quantity = (int) $request->get('quantity', 1);
        $notes = trim($request->get('notes', ''));

        try {
            $user = \Engelsystem\Models\User\User::findOrFail($userId);
            $goodie = \Engelsystem\Models\GoodiesV2Item::findOrFail($goodieId);

            // Validate distribution
            $eligibilityCheck = $this->goodiesService->checkItemEligibility($user, $goodie);
            if (!$eligibilityCheck['eligible']) {
                $this->addNotification(
                    'backstage.goodies.distribute.not_eligible: ' . implode(', ', $eligibilityCheck['reasons']),
                    NotificationType::WARNING
                );
                return $this->redirect->back();
            }

            // Process distribution through service
            $distributionResult = $this->distributionService->distributeItem($user, $goodie, $quantity, $notes);

            if ($distributionResult['success']) {
                $this->log->info('Goodie distributed successfully', [
                    'user' => auth()->user()->name,
                    'user_id' => auth()->user()->id,
                    'target_user_id' => $userId,
                    'target_user_name' => $user->name,
                    'goodie_id' => $goodieId,
                    'goodie_name' => $goodie->name,
                    'quantity' => $quantity,
                    'notes' => $notes,
                ]);

                $this->addNotification('backstage.goodies.distribute.success', NotificationType::MESSAGE);
            } else {
                $this->addNotification(
                    'backstage.goodies.distribute.error: ' . $distributionResult['reason'],
                    NotificationType::ERROR
                );
            }

            // Redirect back to same user profile (requirement: refresh and stay on same page)
            return $this->redirect->to("/admin/backstage/users/{$userId}/goodies");
        } catch (\Exception $e) {
            $this->log->error('Error processing goodie distribution - {error} - stack trace: {trace}', [
                'user' => auth()->user()->name,
                'user_id' => auth()->user()->id,
                'target_user_id' => $userId,
                'goodie_id' => $goodieId,
                'quantity' => $quantity,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addNotification('backstage.goodies.distribute.error', NotificationType::ERROR);
            return $this->redirect->back();
        }
    }


    /**
     * Get distribution summary for a user and goodie (e.g., "Delivered 3x").
     */
    protected function getDistributionSummary(int $userId, int $goodieId): array
    {
        try {
            $distributions = \Engelsystem\Models\GoodiesV2Distribution::where('user_id', $userId)
                ->where('item_id', $goodieId)
                ->get();

            $totalQuantity = $distributions->sum('quantity');
            $lastDistribution = $distributions->sortByDesc('created_at')->first();

            return [
                'total_delivered' => $totalQuantity,
                'delivery_count' => $distributions->count(),
                'last_delivered_at' => $lastDistribution ? $lastDistribution->created_at : null,
                'display_text' => $totalQuantity > 0 ? "Delivered {$totalQuantity}x" : null,
            ];
        } catch (\Exception $e) {
            $this->log->warning('Could not get distribution summary', [
                'user_id' => $userId,
                'goodie_id' => $goodieId,
                'error' => $e->getMessage(),
            ]);

            return [
                'total_delivered' => 0,
                'delivery_count' => 0,
                'last_delivered_at' => null,
                'display_text' => null,
            ];
        }
    }

    /**
     * Calculate pre-filled quantity using max_per_person field.
     */
    protected function calculatePreFilledQuantity(array $goodie): int
    {
        $maxPerPerson = $goodie['max_per_person'] ?? null;
        
        // If no limit set, default to 1
        if ($maxPerPerson === null || $maxPerPerson <= 0) {
            return 1;
        }

        // Use max_per_person as the default quantity
        return (int) $maxPerPerson;
    }

    /**
     * Get complete distribution history for a user.
     */
    protected function getCompleteDistributionHistory(int $userId): array
    {
        try {
            $distributions = \Engelsystem\Models\GoodiesV2Distribution::where('user_id', $userId)
                ->with(['item', 'distributedBy'])
                ->orderBy('created_at', 'desc')
                ->get();

            return $distributions->map(function ($distribution) {
                return [
                    'item_name' => $distribution->item?->name ?? 'Unknown Item',
                    'quantity' => $distribution->quantity,
                    'distributed_at' => $distribution->created_at,
                    'distributed_by' => $distribution->distributedBy?->name ?? 'Unknown Staff',
                    'notes' => $distribution->notes,
                ];
            })->toArray();
        } catch (\Exception $e) {
            $this->log->warning('Could not load distribution history', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get user badges for staff/critter status display.
     */
    protected function getUserBadges($user): array
    {
        $badges = [];

        // Check if user is staff
        if (BackstagePermissionHelper::hasStaffPrivilege($user)) {
            $badges[] = [
                'type' => 'staff',
                'text' => 'Staff',
                'class' => 'bg-primary',
            ];
        } else {
            $badges[] = [
                'type' => 'critter',
                'text' => 'Critter',
                'class' => 'bg-secondary',
            ];
        }

        return $badges;
    }

    /**
     * Check goodies permissions (uses backstage.goodies.view as clarified).
     */
    protected function checkGoodiesPermission(string $level): void
    {
        $user = auth()->user();

        if (!$user) {
            $this->log->warning('Unauthenticated access attempt to goodies profile', [
                'ip_address' => request()->getClientIp(),
                'user_agent' => request()->getHeaderLine('User-Agent'),
                'requested_level' => $level,
            ]);
            throw new HttpForbidden('Authentication required');
        }

        $hasAccess = match ($level) {
            'view' => BackstagePermissionHelper::hasViewAccess($user) ||
                     BackstagePermissionHelper::canViewGoodies($user),
            'admin' => BackstagePermissionHelper::canAdminGoodies($user) ||
                      BackstagePermissionHelper::hasAdminAccess($user),
            default => false,
        };

        if (!$hasAccess) {
            $this->log->warning('Insufficient permissions for goodies profile access', [
                'user' => $user->name,
                'user_id' => $user->id,
                'requested_level' => $level,
                'user_permissions' => $user->privileges->pluck('name')->toArray(),
                'ip_address' => request()->getClientIp(),
            ]);
            throw new HttpForbidden('Insufficient permissions');
        }

        // Log successful access for audit trail
        $this->log->info('Goodies profile access granted', [
            'user' => $user->name,
            'user_id' => $user->id,
            'level' => $level,
            'timestamp' => Carbon::now()->toISOString(),
        ]);
    }
}
