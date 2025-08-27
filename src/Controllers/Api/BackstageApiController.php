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
use Psr\Log\LoggerInterface;
use Carbon\Carbon;

class BackstageApiController extends BaseController
{
    use UsesAuth;

    public function __construct(
        protected LoggerInterface $log,
        protected GoodiesService $goodiesService,
        protected HoursCalculationService $hoursService,
        protected GoodiesDistributionService $distributionService,
        protected Response $response
    ) {
    }

    /**
     * Get system KPIs for the backstage dashboard.
     */
    public function getKpis(Request $request): Response
    {
        $this->checkApiPermission();

        try {
            $kpis = [
                'eligible_users' => $this->goodiesService->getEligibleUsersCount(),
                'qualified_users' => $this->distributionService->getQualifiedUsersCount(),
                'total_distributions' => $this->distributionService->getTotalDistributionsCount(),
                'hours_calculated' => round($this->hoursService->getTotalHoursCalculated(), 1),
                'distributions_today' => $this->distributionService->getDistributionsCount(
                    Carbon::today(),
                    Carbon::tomorrow()
                ),
                'distributions_week' => $this->distributionService->getDistributionsCount(
                    Carbon::now()->startOfWeek(),
                    Carbon::now()->endOfWeek()
                ),
                'pending_recalculations' => $this->hoursService->getPendingRecalculationsCount(),
            ];

            $this->log->info('API: KPIs requested', [
                'user' => auth()->user()->name,
                'user_id' => auth()->user()->id,
                'kpis_count' => count($kpis),
            ]);

            return $this->response->withJson([
                'success' => true,
                'data' => $kpis,
                'meta' => [
                    'timestamp' => Carbon::now()->toISOString(),
                    'cache_expires' => Carbon::now()->addMinutes(5)->toISOString(),
                ],
            ]);
        } catch (\Exception $e) {
            $this->log->error('API: Error fetching KPIs', [
                'user' => auth()->user()->name,
                'user_id' => auth()->user()->id,
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
     * Get list of eligible users with pagination.
     */
    public function getEligibleUsers(Request $request): Response
    {
        $this->checkApiPermission();

        try {
            // Get query parameters
            $page = max(1, (int) $request->get('page', 1));
            $perPage = min(100, max(10, (int) $request->get('per_page', 25)));
            $search = $request->get('search', '');
            $minHours = (float) $request->get('min_hours', 12.0);

            // Get eligible users with pagination
            $result = $this->goodiesService->getEligibleUsersPaginated(
                $page,
                $perPage,
                [
                    'search' => $search,
                    'min_hours' => $minHours,
                ]
            );

            $this->log->info('API: Eligible users requested', [
                'user' => auth()->user()->name,
                'user_id' => auth()->user()->id,
                'page' => $page,
                'per_page' => $perPage,
                'search' => $search,
                'min_hours' => $minHours,
                'total_found' => $result['total'] ?? 0,
            ]);

            return $this->response->withJson([
                'success' => true,
                'data' => $result['data'] ?? [],
                'meta' => [
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $perPage,
                        'total' => $result['total'] ?? 0,
                        'total_pages' => $result['total_pages'] ?? 1,
                    ],
                    'filters' => [
                        'search' => $search,
                        'min_hours' => $minHours,
                    ],
                    'timestamp' => Carbon::now()->toISOString(),
                ],
            ]);
        } catch (\Exception $e) {
            $this->log->error('API: Error fetching eligible users', [
                'user' => auth()->user()->name,
                'user_id' => auth()->user()->id,
                'error' => $e->getMessage(),
            ]);

            return $this->response->withJson([
                'success' => false,
                'error' => 'Failed to fetch eligible users',
                'meta' => [
                    'timestamp' => Carbon::now()->toISOString(),
                ],
            ], 500);
        }
    }

    /**
     * Get distribution statistics.
     */
    public function getDistributionStats(Request $request): Response
    {
        $this->checkApiPermission();

        try {
            // Get optional date range
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');

            $filters = [];
            if ($startDate) {
                $filters['start_date'] = Carbon::parse($startDate);
            }
            if ($endDate) {
                $filters['end_date'] = Carbon::parse($endDate);
            }

            $stats = $this->distributionService->getDistributionStatistics($filters);

            $this->log->info('API: Distribution stats requested', [
                'user' => auth()->user()->name,
                'user_id' => auth()->user()->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);

            return $this->response->withJson([
                'success' => true,
                'data' => $stats,
                'meta' => [
                    'filters' => $filters,
                    'timestamp' => Carbon::now()->toISOString(),
                    'cache_expires' => Carbon::now()->addMinutes(10)->toISOString(),
                ],
            ]);
        } catch (\Exception $e) {
            $this->log->error('API: Error fetching distribution stats', [
                'user' => auth()->user()->name,
                'user_id' => auth()->user()->id,
                'error' => $e->getMessage(),
            ]);

            return $this->response->withJson([
                'success' => false,
                'error' => 'Failed to fetch distribution statistics',
                'meta' => [
                    'timestamp' => Carbon::now()->toISOString(),
                ],
            ], 500);
        }
    }

    /**
     * Get system status information.
     */
    public function getSystemStatus(Request $request): Response
    {
        $this->checkApiPermission();

        try {
            $status = [
                'overall_status' => 'operational',
                'services' => [
                    'hours_service' => $this->checkServiceStatus('hours'),
                    'distribution_service' => $this->checkServiceStatus('distribution'),
                    'goodies_service' => $this->checkServiceStatus('goodies'),
                    'database' => $this->checkServiceStatus('database'),
                ],
                'last_updated' => Carbon::now()->toISOString(),
            ];

            // Determine overall status
            $serviceStatuses = array_values($status['services']);
            if (in_array('error', $serviceStatuses)) {
                $status['overall_status'] = 'error';
            } elseif (in_array('degraded', $serviceStatuses)) {
                $status['overall_status'] = 'degraded';
            }

            return $this->response->withJson([
                'success' => true,
                'data' => $status,
                'meta' => [
                    'timestamp' => Carbon::now()->toISOString(),
                    'cache_expires' => Carbon::now()->addMinutes(1)->toISOString(),
                ],
            ]);
        } catch (\Exception $e) {
            $this->log->error('API: Error fetching system status', [
                'user' => auth()->user()->name,
                'user_id' => auth()->user()->id,
                'error' => $e->getMessage(),
            ]);

            return $this->response->withJson([
                'success' => false,
                'error' => 'Failed to fetch system status',
                'data' => [
                    'overall_status' => 'error',
                    'services' => [],
                    'last_updated' => Carbon::now()->toISOString(),
                ],
                'meta' => [
                    'timestamp' => Carbon::now()->toISOString(),
                ],
            ], 500);
        }
    }

    /**
     * Check the status of individual services.
     */
    protected function checkServiceStatus(string $service): string
    {
        try {
            switch ($service) {
                case 'hours':
                    $this->hoursService->getCalculationSummary();
                    return 'operational';

                case 'distribution':
                    $this->distributionService->getDistributionStatistics();
                    return 'operational';

                case 'goodies':
                    $this->goodiesService->getSystemKpis();
                    return 'operational';

                case 'database':
                    \Engelsystem\Models\User\User::count();
                    return 'operational';

                default:
                    return 'unknown';
            }
        } catch (\Exception $e) {
            $this->log->warning('Service ' . $service . ' status check failed', [
                'service' => $service,
                'error' => $e->getMessage(),
            ]);
            return 'degraded';
        }
    }

    /**
     * Check API access permissions.
     */
    protected function checkApiPermission(): void
    {
        // Ensure user is authenticated (from UsesAuth trait)
        $user = auth()->user();

        if (!$user) {
            $this->log->warning('Unauthenticated API request to backstage endpoint', [
                'ip_address' => request()->getClientIp(),
                'user_agent' => request()->getHeaderLine('User-Agent'),
                'endpoint' => request()->getUri()->getPath(),
            ]);
            throw new HttpForbidden('Authentication required');
        }

        // Check backstage permissions
        if (!BackstagePermissionHelper::hasViewAccess($user)) {
            $this->log->warning('Insufficient permissions for backstage API access', [
                'user' => $user->name,
                'user_id' => $user->id,
                'endpoint' => request()->getUri()->getPath(),
                'user_permissions' => $user->privileges->pluck('name')->toArray(),
                'ip_address' => request()->getClientIp(),
            ]);
            throw new HttpForbidden('Insufficient permissions');
        }

        // Log successful API access
        $this->log->debug('Backstage API access granted', [
            'user' => $user->name,
            'user_id' => $user->id,
            'endpoint' => request()->getUri()->getPath(),
            'timestamp' => Carbon::now()->toISOString(),
        ]);
    }
}
