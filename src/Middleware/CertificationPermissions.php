<?php

declare(strict_types=1);

namespace Engelsystem\Middleware;

use Engelsystem\Http\Exceptions\HttpForbidden;
use Engelsystem\Http\Request;
use Engelsystem\Models\Department\Department;
use Engelsystem\Models\User\User;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

class CertificationPermissions implements MiddlewareInterface
{
    public function __construct(
        protected LoggerInterface $log
    ) {
    }
    /**
     * Process the middleware to check certification-specific permissions.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var Request $request */
        $route = $request->getAttribute('route-info');
        $routeName = $route['handler'] ?? '';

        // Only apply to certification-related routes
        if (!$this->isCertificationRoute($routeName)) {
            return $handler->handle($request);
        }

        /** @var User $user */
        $user = auth()->user();

        if (!$user) {
            $this->log->warning('Certification access attempted without authentication', [
                'route' => $routeName,
                'ip' => $request->getClientIp(),
                'user_agent' => $request->getHeaderLine('User-Agent'),
            ]);
            throw new HttpForbidden('Authentication required');
        }

        // Check certification-specific permissions
        $this->checkCertificationPermissions($user, $routeName, $request);

        return $handler->handle($request);
    }

    /**
     * Check if this is a certification-related route.
     */
    protected function isCertificationRoute(string $routeName): bool
    {
        $certificationRoutes = [
            'Admin\\CertificationsController',
            'UserCertificationsController',
        ];

        foreach ($certificationRoutes as $controller) {
            if (str_contains($routeName, $controller)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check certification-specific permissions based on route and user.
     */
    protected function checkCertificationPermissions(User $user, string $routeName, Request $request): void
    {
        // Extract controller and method from route
        [$controller, $method] = $this->parseRoute($routeName);

        // Admin certification management requires certification manager role
        if ($controller === 'CertificationsController') {
            $this->checkCertificationManagerRole($user);

            // Additional department-based restrictions for certain operations
            if (in_array($method, ['userStore', 'userUpdate', 'userDestroy'])) {
                $this->checkDepartmentPermissions($user, $request);
            }
        }

        // User certification management has different permission levels
        if ($controller === 'UserCertificationsController') {
            $this->checkUserCertificationPermissions($user, $method, $request);
        }
    }

    /**
     * Parse route name to extract controller and method.
     */
    protected function parseRoute(string $routeName): array
    {
        // Handle both "Controller@method" and "Namespace\\Controller@method" formats
        if (str_contains($routeName, '@')) {
            [$controllerPart, $method] = explode('@', $routeName, 2);

            // Extract just the controller name from potential namespace
            $controller = class_basename($controllerPart);
        } else {
            $controller = class_basename($routeName);
            $method = 'index';
        }

        return [$controller, $method];
    }

    /**
     * Check if user has Certification Manager role.
     */
    protected function checkCertificationManagerRole(User $user): void
    {
        // Check for certification management permissions in order of priority
        $requiredPermissions = [
            'certificates.admin',    // Full certification admin (new)
            'certificates.manage',   // Certification manager (new)
            'admin_certificates',    // Legacy certification admin permission
        ];

        $hasPermission = $user->hasAnyPermission($requiredPermissions);

        if (!$hasPermission) {
            $this->log->warning('Certification manager role required but not granted', [
                'user' => $user->name,
                'user_id' => $user->id,
                'required_permissions' => $requiredPermissions,
                'user_permissions' => $user->privileges()->pluck('name')->toArray(),
                'route' => request()->getAttribute('route-info')['handler'] ?? 'unknown',
            ]);

            throw new HttpForbidden('Certification Manager role required');
        }
    }

    /**
     * Check department-based permissions for shift coordinators.
     */
    protected function checkDepartmentPermissions(User $user, Request $request): void
    {
        // Get target user from request if this is a user-specific operation
        $targetUserId = $request->getAttribute('user_id');

        if (!$targetUserId) {
            // No specific user target, allow for certification managers
            return;
        }

        // Check if user has full admin permissions (bypasses department restrictions)
        if ($this->hasFullCertificationAdminAccess($user)) {
            return;
        }

        // Check if user has certification management permissions
        if (!$this->hasDepartmentBasedAccess($user)) {
            $this->log->warning('Department-based certification access denied', [
                'user' => $user->name,
                'user_id' => $user->id,
                'target_user_id' => $targetUserId,
                'has_certificates_manage' => $user->hasPermission('certificates.manage'),
                'is_shift_coordinator' => $user->groups->contains('name', 'Shift Coordinator'),
                'responsible_departments' => $user->responsibleForDepartments()->pluck('name')->toArray(),
                'route' => request()->getAttribute('route-info')['handler'] ?? 'unknown',
            ]);

            throw new HttpForbidden('You must be a shift coordinator or department responsible with certification management permissions'); // phpcs:ignore
        }

        // Check department membership restrictions
        $this->checkDepartmentMembership($user, $targetUserId);
    }

    /**
     * Check if user has full certification admin access (bypasses department restrictions).
     */
    protected function hasFullCertificationAdminAccess(User $user): bool
    {
        return $user->hasPermission('certificates.admin')
            || $user->hasPermission('admin_certificates');
    }

    /**
     * Check if user has department-based certification management access.
     */
    protected function hasDepartmentBasedAccess(User $user): bool
    {
        // Must have certification management permission
        if (!$user->hasPermission('certificates.manage')) {
            return false;
        }

        // Must be either a shift coordinator or responsible for at least one department
        return $user->groups->contains('name', 'Shift Coordinator')
            || $user->responsibleForDepartments()->exists();
    }

    /**
     * Get all department IDs that a user has access to manage.
     */
    protected function getUserManageableDepartments(User $user): array
    {
        // Departments user is responsible for
        $responsibleDepartments = $user->responsibleForDepartments()->pluck('id')->toArray();

        // Departments user is an approved member of (shift coordinators can manage their own departments)
        $memberDepartments = [];
        if ($user->groups->contains('name', 'Shift Coordinator')) {
            $memberDepartments = $user->departments()
                ->wherePivot('status', 'approved')
                ->pluck('id')
                ->toArray();
        }

        return array_unique(array_merge($responsibleDepartments, $memberDepartments));
    }

    /**
     * Check if a user can perform a specific certification operation on a target user.
     */
    protected function canManageCertificationForUser(User $manager, User $targetUser, string $operation): bool
    {
        // Full admins can always manage
        if ($this->hasFullCertificationAdminAccess($manager)) {
            return true;
        }

        // Check department-based access
        if (!$this->hasDepartmentBasedAccess($manager)) {
            return false;
        }

        // Check specific operation permissions
        $operationPermissions = [
            'assign' => 'certificates.assign',
            'approve' => 'certificates.approve',
            'revoke' => 'certificates.revoke',
            'view' => 'certificates.view',
        ];

        if (isset($operationPermissions[$operation]) && !$manager->hasPermission($operationPermissions[$operation])) {
            return false;
        }

        // Check department membership
        $managerDepartments = $this->getUserManageableDepartments($manager);
        $targetDepartments = $targetUser->departments()->pluck('id')->toArray();

        return !empty(array_intersect($managerDepartments, $targetDepartments));
    }

    /**
     * Check if target user is in one of the coordinator's departments.
     */
    protected function checkDepartmentMembership(User $coordinator, int $targetUserId): void
    {
        /** @var User $targetUser */
        $targetUser = User::findOrFail($targetUserId);

        // Get all departments the coordinator has access to manage
        $coordinatorDepartments = $this->getUserManageableDepartments($coordinator);

        // Get departments the target user belongs to (any status - coordinator might need to manage pending users)
        $targetUserDepartments = $targetUser->departments()->pluck('id')->toArray();

        // Check for intersection - coordinator must share at least one department with target user
        $sharedDepartments = array_intersect($coordinatorDepartments, $targetUserDepartments);

        if (empty($sharedDepartments)) {
            // Get department names for better error message
            $coordinatorDepartmentNames = [];
            if (!empty($coordinatorDepartments)) {
                $coordinatorDepartmentNames = Department::whereIn('id', $coordinatorDepartments)
                    ->pluck('name')
                    ->toArray();
            }

            $targetDepartmentNames = [];
            if (!empty($targetUserDepartments)) {
                $targetDepartmentNames = Department::whereIn('id', $targetUserDepartments)
                    ->pluck('name')
                    ->toArray();
            }

            $this->log->warning('Department-based certification access denied - no shared departments', [
                'coordinator' => $coordinator->name,
                'coordinator_id' => $coordinator->id,
                'target_user_id' => $targetUserId,
                'target_user' => $targetUser->name,
                'coordinator_departments' => $coordinatorDepartmentNames,
                'target_departments' => $targetDepartmentNames,
                'route' => request()->getAttribute('route-info')['handler'] ?? 'unknown',
            ]);

            if (empty($coordinatorDepartmentNames)) {
                throw new HttpForbidden('You are not associated with any departments. Contact an administrator for access.'); // phpcs:ignore
            }

            throw new HttpForbidden(sprintf(
                'You can only manage certifications for critters in your departments (%s). This user is not in any of your departments.', // phpcs:ignore
                implode(', ', $coordinatorDepartmentNames)
            ));
        } else {
            // Log successful department access
            $sharedDepartmentNames = Department::whereIn('id', $sharedDepartments)
                ->pluck('name')
                ->toArray();

            $this->log->info('Department-based certification access granted', [
                'coordinator' => $coordinator->name,
                'coordinator_id' => $coordinator->id,
                'target_user_id' => $targetUserId,
                'target_user' => $targetUser->name,
                'shared_departments' => $sharedDepartmentNames,
                'route' => request()->getAttribute('route-info')['handler'] ?? 'unknown',
            ]);
        }
    }

    /**
     * Check permissions for user certification operations.
     */
    protected function checkUserCertificationPermissions(User $user, string $method, Request $request): void
    {
        switch ($method) {
            case 'userIndex':
                // Viewing certifications - either own or admin access
                $targetUserId = $request->getAttribute('user_id');

                if ($targetUserId && $targetUserId !== $user->id) {
                    // Viewing another user's certifications requires admin permissions
                    $this->checkCertificationManagerRole($user);
                    $this->checkDepartmentPermissions($user, $request);
                }
                break;

            case 'userStore':
            case 'userUpdate':
            case 'userDestroy':
                // Modifying user certifications requires admin permissions
                $this->checkCertificationManagerRole($user);
                $this->checkDepartmentPermissions($user, $request);
                break;

            case 'selfConfirm':
                // Self-confirmation only requires authentication (already checked)
                // Additional validation is handled in the controller
                break;

            default:
                // Unknown method - require admin permissions as safety measure
                $this->checkCertificationManagerRole($user);
                break;
        }
    }
}
