<?php

declare(strict_types=1);

namespace Engelsystem\Middleware;

use Engelsystem\Http\Exceptions\HttpForbidden;
use Engelsystem\Http\Request;
use Engelsystem\Models\User\User;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

class BackstagePermissions implements MiddlewareInterface
{
    public function __construct(
        protected LoggerInterface $log
    ) {
    }

    /**
     * Process the middleware to check backstage-specific permissions.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var Request $request */
        $route = $request->getAttribute('route-info');
        $routeName = $route['handler'] ?? '';

        // Only apply to backstage-related routes
        if (!$this->isBackstageRoute($routeName)) {
            return $handler->handle($request);
        }

        /** @var User $user */
        $user = auth()->user();

        if (!$user) {
            $this->log->warning('Backstage access attempted without authentication', [
                'route' => $routeName,
                'ip' => $request->getClientIp(),
                'user_agent' => $request->getHeaderLine('User-Agent'),
            ]);
            throw new HttpForbidden('Authentication required');
        }

        // Check backstage-specific permissions
        $this->checkBackstagePermissions($user, $routeName, $request);

        return $handler->handle($request);
    }

    /**
     * Check if this is a backstage-related route.
     */
    protected function isBackstageRoute(string $routeName): bool
    {
        $backstageRoutes = [
            'BackstageController',
            'Admin\\BackstageController',
            'BackstageGoodiesController',
            'Admin\\BackstageGoodiesController',
        ];

        foreach ($backstageRoutes as $controller) {
            if (str_contains($routeName, $controller)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check backstage-specific permissions based on route and user.
     */
    protected function checkBackstagePermissions(User $user, string $routeName, Request $request): void
    {
        // Extract controller and method from route
        [$controller, $method] = $this->parseRoute($routeName);

        // All backstage access requires at least backstage.view permission
        if (!$this->hasBackstageViewAccess($user)) {
            $this->log->warning('Backstage view access denied', [
                'user' => $user->name,
                'user_id' => $user->id,
                'route' => $routeName,
                'has_backstage_view' => $user->hasPermission('backstage.view'),
                'has_backstage_admin' => $user->hasPermission('backstage.admin'),
                'user_permissions' => $user->privileges()->pluck('name')->toArray(),
            ]);

            throw new HttpForbidden('Backstage access denied. Contact an administrator for access.');
        }

        // Check specific controller permissions
        if (str_contains($controller, 'BackstageGoodies')) {
            $this->checkBackstageGoodiesPermissions($user, $method, $request);
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
     * Check if user has backstage view access (either direct or through admin).
     */
    protected function hasBackstageViewAccess(User $user): bool
    {
        // Check for backstage permissions in order of priority
        $requiredPermissions = [
            'user.type.admin',  // System administrators have full access
            'backstage.admin',  // Full backstage admin (inherits all)
            'backstage.view',   // Basic backstage view access
        ];

        return $user->hasAnyPermission($requiredPermissions);
    }

    /**
     * Check if user has full backstage admin access.
     */
    protected function hasFullBackstageAdminAccess(User $user): bool
    {
        return $user->hasAnyPermission([
            'user.type.admin',  // System administrators have full access
            'backstage.admin',
        ]);
    }

    /**
     * Check permissions for backstage goodies operations.
     */
    protected function checkBackstageGoodiesPermissions(User $user, string $method, Request $request): void
    {
        // Determine required permission level based on method
        $requiredPermissions = $this->getRequiredGoodiesPermissions($method);

        if (empty($requiredPermissions)) {
            // Unknown method - require admin permissions as safety measure
            $this->log->warning('Unknown backstage goodies method, requiring admin access', [
                'user' => $user->name,
                'user_id' => $user->id,
                'method' => $method,
                'route' => request()->getAttribute('route-info')['handler'] ?? 'unknown',
            ]);

            if (!$this->hasFullBackstageAdminAccess($user)) {
                throw new HttpForbidden('Administrative access required for this operation.');
            }
            return;
        }

        // Check if user has any of the required permissions
        $hasPermission = $user->hasAnyPermission($requiredPermissions);

        if (!$hasPermission) {
            $this->log->warning('Backstage goodies access denied', [
                'user' => $user->name,
                'user_id' => $user->id,
                'method' => $method,
                'required_permissions' => $requiredPermissions,
                'user_permissions' => $user->privileges()->pluck('name')->toArray(),
                'route' => request()->getAttribute('route-info')['handler'] ?? 'unknown',
            ]);

            $permissionLevel = $this->getPermissionLevelDescription($requiredPermissions);
            throw new HttpForbidden('Goodies ' . $permissionLevel . ' access required for this operation.');
        }

        // Log successful access
        $grantedPermission = collect($requiredPermissions)->first(fn($perm) => $user->hasPermission($perm));
        $isSystemAdmin = $user->hasPermission('user.type.admin');

        $this->log->info('Backstage goodies access granted', [
            'user' => $user->name,
            'user_id' => $user->id,
            'method' => $method,
            'granted_via' => $grantedPermission,
            'is_system_admin' => $isSystemAdmin,
            'route' => request()->getAttribute('route-info')['handler'] ?? 'unknown',
        ]);
    }

    /**
     * Get required permissions for specific goodies methods.
     */
    protected function getRequiredGoodiesPermissions(string $method): array
    {
        // Map methods to required permission levels (in order of priority)
        $methodPermissions = [
            // View operations - can view goodies status/quantities
            'index' => [
                'user.type.admin',      // System administrators have full access
                'backstage.admin',
                'backstage.goodies.admin',
                'backstage.goodies.agent',
                'backstage.goodies.view',
            ],
            'show' => [
                'user.type.admin',      // System administrators have full access
                'backstage.admin',
                'backstage.goodies.admin',
                'backstage.goodies.agent',
                'backstage.goodies.view',
            ],
            'export' => [
                'user.type.admin',      // System administrators have full access
                'backstage.admin',
                'backstage.goodies.admin',
                'backstage.goodies.view',
            ],

            // Agent operations - can distribute goodies
            'distribute' => [
                'user.type.admin',      // System administrators have full access
                'backstage.admin',
                'backstage.goodies.admin',
                'backstage.goodies.agent',
            ],
            'scan' => [
                'user.type.admin',      // System administrators have full access
                'backstage.admin',
                'backstage.goodies.admin',
                'backstage.goodies.agent',
            ],
            'give' => [
                'user.type.admin',      // System administrators have full access
                'backstage.admin',
                'backstage.goodies.admin',
                'backstage.goodies.agent',
            ],

            // Admin operations - can create/edit/delete goodies
            'create' => [
                'user.type.admin',      // System administrators have full access
                'backstage.admin',
                'backstage.goodies.admin',
            ],
            'store' => [
                'user.type.admin',      // System administrators have full access
                'backstage.admin',
                'backstage.goodies.admin',
            ],
            'edit' => [
                'user.type.admin',      // System administrators have full access
                'backstage.admin',
                'backstage.goodies.admin',
            ],
            'update' => [
                'user.type.admin',      // System administrators have full access
                'backstage.admin',
                'backstage.goodies.admin',
            ],
            'destroy' => [
                'user.type.admin',      // System administrators have full access
                'backstage.admin',
                'backstage.goodies.admin',
            ],
            'delete' => [
                'user.type.admin',      // System administrators have full access
                'backstage.admin',
                'backstage.goodies.admin',
            ],
        ];

        return $methodPermissions[$method] ?? [];
    }

    /**
     * Get human-readable permission level description.
     */
    protected function getPermissionLevelDescription(array $permissions): string
    {
        // Determine the most permissive level from the required permissions
        if (in_array('backstage.goodies.view', $permissions)) {
            return 'view';
        }
        if (in_array('backstage.goodies.agent', $permissions)) {
            return 'agent';
        }
        if (in_array('backstage.goodies.admin', $permissions)) {
            return 'administration';
        }

        return 'administrative';
    }
}
