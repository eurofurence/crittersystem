<?php

declare(strict_types=1);

namespace Engelsystem\Controllers;

use Engelsystem\Controllers\BaseController;
use Engelsystem\Controllers\HasUserNotifications;
use Engelsystem\Controllers\NotificationType;
use Engelsystem\Helpers\BackstagePermissionHelper;
use Engelsystem\Http\Exceptions\HttpForbidden;
use Engelsystem\Http\Exceptions\ValidationException;
use Engelsystem\Http\Redirector;
use Engelsystem\Http\Request;
use Engelsystem\Http\Response;
use Engelsystem\Http\Validation\Validator;
use Engelsystem\Models\User\User;
use Engelsystem\Services\GoodiesService;
use Engelsystem\Services\GoodiesDistributionService;
use Engelsystem\Services\UserSearchService;
use Psr\Log\LoggerInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;

class GoodiesDistributionController extends BaseController
{
    use HasUserNotifications;

    /** @var array<string> */
    protected array $permissions = [
        'backstage.goodies.view',
        'backstage.goodies.agent',
        'backstage.goodies.admin',
    ];

    public function __construct(
        protected LoggerInterface $log,
        protected GoodiesService $goodiesService,
        protected GoodiesDistributionService $distributionService,
        protected UserSearchService $userSearchService,
        protected Response $response,
        protected Redirector $redirect
    ) {
    }

    /**
     * Display the goodies distribution interface.
     */
    public function index(Request $request): Response
    {
        $this->checkDistributionPermission('view');

        try {
            // Get filter parameters
            $status = $request->get('status', 'qualified');
            $search = $request->get('search', '');
            $perPage = max(10, min(50, (int) $request->get('per_page', 20)));
            $page = max(1, (int) $request->get('page', 1));

            $filters = [
                'status' => $status,
                'search' => $search,
            ];

            // Get users based on status filter
            $usersResult = match ($status) {
                'qualified' => $this->distributionService->getQualifiedUsersPaginated($page, $perPage, $filters),
                'distributed' => $this->distributionService->getDistributedUsersPaginated($page, $perPage, $filters),
                'eligible' => $this->goodiesService->getEligibleUsersPaginated($page, $perPage, $filters),
                default => ['data' => [], 'total' => 0, 'total_pages' => 1],
            };

            // Get available goodies items for distribution
            $goodiesItems = $this->goodiesService->getAvailableGoodiesItems();

            // Get distribution statistics
            $statistics = [
                'eligible_count' => $this->goodiesService->getEligibleUsersCount(),
                'qualified_count' => $this->distributionService->getQualifiedUsersCount(),
                'distributed_today' => $this->distributionService->getDistributionsCount(
                    Carbon::today(),
                    Carbon::tomorrow()
                ),
                'total_distributed' => $this->distributionService->getTotalDistributionsCount(),
            ];

            $this->log->info('Distribution interface accessed', [
                'user' => auth()->user()->name,
                'user_id' => auth()->user()->id,
                'filters' => $filters,
                'page' => $page,
                'per_page' => $perPage,
            ]);

            return $this->response->withView(
                'goodies/distribution/index',
                [
                    'users' => $usersResult['data'] ?? [],
                    'goodies_items' => $goodiesItems,
                    'statistics' => $statistics,
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $perPage,
                        'total' => $usersResult['total'] ?? 0,
                        'total_pages' => $usersResult['total_pages'] ?? 1,
                    ],
                    'filters' => $filters,
                    'current_status' => $status,
                    'user' => auth()->user(),
                    'can_distribute' => BackstagePermissionHelper::canDistributeGoodies(),
                    'can_admin' => BackstagePermissionHelper::canManageGoodies(),
                ]
            );
        } catch (\Exception $e) {
            $this->log->error('Error loading distribution interface', [
                'user' => auth()->user()->name,
                'user_id' => auth()->user()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addNotification('goodies.distribution.load.error', NotificationType::ERROR);

            return $this->response->withView(
                'goodies/distribution/index',
                [
                    'error' => true,
                    'users' => [],
                    'goodies_items' => [],
                    'statistics' => [],
                    'user' => auth()->user(),
                ]
            );
        }
    }

    /**
     * Qualify a user for goodies distribution.
     */
    public function qualifyUser(Request $request): Response
    {
        $this->checkDistributionPermission('distribute');

        $data = $this->validate($request, [
            'user_id' => 'required|integer|min:1',
            'notes' => 'optional|max:500',
        ]);

        try {
            /** @var User $user */
            $user = User::findOrFail($data['user_id']);

            // Check if user is eligible
            $eligibility = $this->goodiesService->checkUserEligibility($user);
            if (!$eligibility['is_eligible']) {
                throw new ValidationException(
                    (new Validator())->addErrors(['user_id' => ['User is not eligible for goodies']])
                );
            }

            // Qualify the user
            $qualification = $this->distributionService->qualifyUser(
                $user,
                auth()->user(),
                $data['notes'] ?? null
            );

            $this->log->info('User qualified for goodies distribution', [
                'distributor' => auth()->user()->name,
                'distributor_id' => auth()->user()->id,
                'qualified_user' => $user->name,
                'qualified_user_id' => $user->id,
                'notes' => $data['notes'] ?? null,
                'qualification_id' => $qualification->id ?? null,
            ]);

            $this->addNotification('goodies.qualify.success');

            // Return JSON for AJAX requests
            if ($this->isAjaxRequest($request)) {
                return $this->response->withJson([
                    'success' => true,
                    'message' => 'User qualified successfully',
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'qualified_at' => Carbon::now()->toISOString(),
                    ],
                ]);
            }

            return $this->redirect->to('/goodies/distribution');
        } catch (ModelNotFoundException) {
            $this->addNotification('goodies.qualify.user_not_found', NotificationType::ERROR);

            if ($this->isAjaxRequest($request)) {
                return $this->response->withJson(['error' => 'User not found'], 404);
            }

            return $this->redirect->to('/goodies/distribution');
        } catch (ValidationException $e) {
            if ($this->isAjaxRequest($request)) {
                return $this->response->withJson(['error' => $e->getMessage()], 400);
            }
            throw $e;
        } catch (\Exception $e) {
            $this->log->error('Error qualifying user for goodies', [
                'distributor' => auth()->user()->name,
                'distributor_id' => auth()->user()->id,
                'user_id' => $data['user_id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            $this->addNotification('goodies.qualify.error', NotificationType::ERROR);

            if ($this->isAjaxRequest($request)) {
                return $this->response->withJson(['error' => 'Failed to qualify user'], 500);
            }

            return $this->redirect->to('/goodies/distribution');
        }
    }

    /**
     * Distribute goodies to a qualified user.
     */
    public function distributeToUser(Request $request): Response
    {
        $this->checkDistributionPermission('distribute');

        $data = $this->validateDistributionData($request);

        try {
            /** @var User $user */
            $user = User::findOrFail($data['user_id']);

            // Check if user is qualified
            if (!$this->distributionService->isUserQualified($user)) {
                throw new ValidationException(
                    (new Validator())->addErrors(['user_id' => ['User is not qualified for distribution']])
                );
            }

            // Process the distribution
            $distribution = $this->distributionService->distributeToUser(
                $user,
                $data['goodies_items'],
                auth()->user(),
                $data['notes'] ?? null
            );

            $this->log->info('Goodies distributed to user', [
                'distributor' => auth()->user()->name,
                'distributor_id' => auth()->user()->id,
                'recipient' => $user->name,
                'recipient_id' => $user->id,
                'goodies_items' => $data['goodies_items'],
                'notes' => $data['notes'] ?? null,
                'distribution_id' => $distribution->id ?? null,
            ]);

            $this->addNotification('goodies.distribute.success');

            // Return JSON for AJAX requests
            if ($this->isAjaxRequest($request)) {
                return $this->response->withJson([
                    'success' => true,
                    'message' => 'Goodies distributed successfully',
                    'distribution' => [
                        'id' => $distribution->id ?? null,
                        'user' => $user->name,
                        'items_count' => count($data['goodies_items']),
                        'distributed_at' => Carbon::now()->toISOString(),
                    ],
                ]);
            }

            return $this->redirect->to('/goodies/distribution');
        } catch (ModelNotFoundException) {
            $this->addNotification('goodies.distribute.user_not_found', NotificationType::ERROR);

            if ($this->isAjaxRequest($request)) {
                return $this->response->withJson(['error' => 'User not found'], 404);
            }

            return $this->redirect->to('/goodies/distribution');
        } catch (ValidationException $e) {
            if ($this->isAjaxRequest($request)) {
                return $this->response->withJson(['error' => $e->getMessage()], 400);
            }
            throw $e;
        } catch (\Exception $e) {
            $this->log->error('Error distributing goodies to user', [
                'distributor' => auth()->user()->name,
                'distributor_id' => auth()->user()->id,
                'user_id' => $data['user_id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            $this->addNotification('goodies.distribute.error', NotificationType::ERROR);

            if ($this->isAjaxRequest($request)) {
                return $this->response->withJson(['error' => 'Failed to distribute goodies'], 500);
            }

            return $this->redirect->to('/goodies/distribution');
        }
    }

    /**
     * Bulk qualify multiple users.
     */
    public function bulkQualify(Request $request): Response
    {
        $this->checkDistributionPermission('distribute');

        // Manual validation for array data
        $requestData = $request->getParsedBody();

        if (!isset($requestData['user_ids']) || !is_array($requestData['user_ids'])) {
            throw new ValidationException(
                (new Validator())->addErrors(['user_ids' => ['User IDs must be provided as an array']])
            );
        }

        $userIds = array_map('intval', $requestData['user_ids']);
        $notes = $requestData['bulk_notes'] ?? null;

        if (empty($userIds)) {
            throw new ValidationException(
                (new Validator())->addErrors(['user_ids' => ['At least one user must be selected']])
            );
        }

        $results = [];
        $errors = [];

        try {
            foreach ($userIds as $userId) {
                try {
                    /** @var User $user */
                    $user = User::findOrFail($userId);

                    // Check eligibility
                    $eligibility = $this->goodiesService->checkUserEligibility($user);
                    if (!$eligibility['is_eligible']) {
                        $errors[] = 'User ' . $user->name . ': Not eligible for goodies';
                        continue;
                    }

                    // Qualify the user
                    $this->distributionService->qualifyUser(
                        $user,
                        auth()->user(),
                        $notes
                    );

                    $results[] = $user->id;

                    $this->log->info('Bulk qualified user for goodies', [
                        'distributor' => auth()->user()->name,
                        'distributor_id' => auth()->user()->id,
                        'qualified_user' => $user->name,
                        'qualified_user_id' => $user->id,
                        'bulk_operation' => true,
                    ]);
                } catch (ModelNotFoundException) {
                    $errors[] = 'User ID ' . $userId . ': Not found';
                } catch (\Exception $e) {
                    $errors[] = 'User ID ' . $userId . ': ' . $e->getMessage();
                }
            }

            $successCount = count($results);
            $errorCount = count($errors);

            if ($successCount > 0 && $errorCount === 0) {
                $this->addNotification(
                    'Successfully qualified ' . $successCount . ' users for goodies distribution.',
                    NotificationType::INFORMATION
                );
            } elseif ($successCount > 0 && $errorCount > 0) {
                $this->addNotification(
                    'Qualified ' . $successCount . ' users successfully, ' . $errorCount . ' failed.',
                    NotificationType::WARNING
                );
            } else {
                $this->addNotification(
                    'Failed to qualify any users.',
                    NotificationType::ERROR
                );
            }

            if ($this->isAjaxRequest($request)) {
                return $this->response->withJson([
                    'success' => $successCount > 0,
                    'qualified' => $successCount,
                    'errors' => $errorCount,
                    'error_details' => $errors,
                ]);
            }

            return $this->redirect->to('/goodies/distribution');
        } catch (\Exception $e) {
            $this->log->error('Error in bulk qualification', [
                'distributor' => auth()->user()->name,
                'distributor_id' => auth()->user()->id,
                'user_ids' => $userIds,
                'error' => $e->getMessage(),
            ]);

            $this->addNotification('goodies.bulk_qualify.error', NotificationType::ERROR);

            if ($this->isAjaxRequest($request)) {
                return $this->response->withJson(['error' => 'Bulk qualification failed'], 500);
            }

            return $this->redirect->to('/goodies/distribution');
        }
    }

    /**
     * View distribution history.
     */
    public function distributionHistory(Request $request): Response
    {
        $this->checkDistributionPermission('view');

        try {
            $page = max(1, (int) $request->get('page', 1));
            $perPage = max(10, min(50, (int) $request->get('per_page', 25)));
            $search = $request->get('search', '');
            $dateRange = $request->get('date_range', '');

            $filters = [
                'search' => $search,
                'date_range' => $dateRange,
            ];

            $historyResult = $this->distributionService->getDistributionHistoryPaginated(
                $page,
                $perPage,
                $filters
            );

            return $this->response->withView(
                'goodies/distribution/history',
                [
                    'distributions' => $historyResult['data'] ?? [],
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $perPage,
                        'total' => $historyResult['total'] ?? 0,
                        'total_pages' => $historyResult['total_pages'] ?? 1,
                    ],
                    'filters' => $filters,
                    'user' => auth()->user(),
                ]
            );
        } catch (\Exception $e) {
            $this->log->error('Error loading distribution history', [
                'user' => auth()->user()->name,
                'user_id' => auth()->user()->id,
                'error' => $e->getMessage(),
            ]);

            $this->addNotification('goodies.history.load.error', NotificationType::ERROR);

            return $this->response->withView(
                'goodies/distribution/history',
                [
                    'error' => true,
                    'distributions' => [],
                    'user' => auth()->user(),
                ]
            );
        }
    }

    /**
     * AJAX endpoint for user lookup/search.
     */
    public function userLookup(Request $request): Response
    {
        $this->checkDistributionPermission('view');

        $query = $request->get('q', '');
        $status = $request->get('status', 'eligible'); // eligible, qualified, all

        if (strlen($query) < 2) {
            return $this->response->withJson([
                'success' => false,
                'error' => 'Search query must be at least 2 characters',
                'results' => [],
            ]);
        }

        try {
            $filters = [
                'search' => $query,
                'limit' => 20,
            ];

            $users = match ($status) {
                'eligible' => $this->goodiesService->searchEligibleUsers($filters),
                'qualified' => $this->distributionService->searchQualifiedUsers($filters),
                'all' => $this->userSearchService->searchUsers($filters),
                default => [],
            };

            // Format results for frontend
            $formattedUsers = [];
            foreach ($users as $user) {
                $formattedUsers[] = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_eligible' => $this->goodiesService->checkUserEligibility($user)['is_eligible'],
                    'is_qualified' => $this->distributionService->isUserQualified($user),
                    'has_distribution' => $this->distributionService->hasUserReceivedDistribution($user),
                ];
            }

            return $this->response->withJson([
                'success' => true,
                'results' => $formattedUsers,
                'query' => $query,
                'status_filter' => $status,
            ]);
        } catch (\Exception $e) {
            $this->log->error('Error in user lookup', [
                'user' => auth()->user()->name,
                'user_id' => auth()->user()->id,
                'query' => $query,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);

            return $this->response->withJson([
                'success' => false,
                'error' => 'Search failed',
                'results' => [],
            ], 500);
        }
    }

    /**
     * Validate distribution form data.
     */
    protected function validateDistributionData(Request $request): array
    {
        return $this->validate($request, [
            'user_id' => 'required|integer|min:1',
            'goodies_items' => 'required|array|min:1',
            'goodies_items.*' => 'integer|min:1',
            'notes' => 'optional|max:500',
        ]);
    }

    /**
     * Check if request is AJAX.
     */
    protected function isAjaxRequest(Request $request): bool
    {
        return $request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest';
    }

    /**
     * Check distribution permissions.
     */
    protected function checkDistributionPermission(string $level): void
    {
        $user = auth()->user();

        if (!$user) {
            throw new HttpForbidden('Authentication required');
        }

        $hasPermission = match ($level) {
            'view' => BackstagePermissionHelper::canViewGoodies($user),
            'distribute' => BackstagePermissionHelper::canDistributeGoodies($user),
            'admin' => BackstagePermissionHelper::canManageGoodies($user),
            default => false,
        };

        if (!$hasPermission) {
            $this->log->warning('Insufficient permissions for distribution access', [
                'user' => $user->name,
                'user_id' => $user->id,
                'requested_level' => $level,
                'user_permissions' => $user->privileges->pluck('name')->toArray(),
            ]);
            throw new HttpForbidden('Insufficient permissions');
        }
    }
}
