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
}
