<?php

declare(strict_types=1);

namespace Engelsystem\Helpers;

use Engelsystem\Models\User\User;

class BackstagePermissionHelper
{
    /**
     * Check if user has backstage view access.
     */
    public static function hasViewAccess(?User $user = null): bool
    {
        $user = $user ?: auth()->user();

        if (!$user) {
            return false;
        }

        return $user->hasAnyPermission([
            'user.type.admin',      // System administrators have full access
            'backstage.admin',
            'backstage.view',
        ]);
    }

    /**
     * Check if user has full backstage admin access.
     */
    public static function hasAdminAccess(?User $user = null): bool
    {
        $user = $user ?: auth()->user();

        if (!$user) {
            return false;
        }

        return $user->hasAnyPermission([
            'user.type.admin',      // System administrators have full access
            'backstage.admin',
        ]);
    }

    /**
     * Check if user can view goodies information.
     */
    public static function canViewGoodies(?User $user = null): bool
    {
        $user = $user ?: auth()->user();

        if (!$user) {
            return false;
        }

        return $user->hasAnyPermission([
            'user.type.admin',      // System administrators have full access
            'backstage.admin',
            'backstage.goodies.admin',
            'backstage.goodies.agent',
            'backstage.goodies.view',
        ]);
    }

    /**
     * Check if user can distribute goodies.
     */
    public static function canDistributeGoodies(?User $user = null): bool
    {
        $user = $user ?: auth()->user();

        if (!$user) {
            return false;
        }

        return $user->hasAnyPermission([
            'user.type.admin',      // System administrators have full access
            'backstage.admin',
            'backstage.goodies.admin',
            'backstage.goodies.agent',
        ]);
    }

    /**
     * Check if user can manage goodies (create, edit, delete).
     */
    public static function canManageGoodies(?User $user = null): bool
    {
        $user = $user ?: auth()->user();

        if (!$user) {
            return false;
        }

        return $user->hasAnyPermission([
            'user.type.admin',      // System administrators have full access
            'backstage.admin',
            'backstage.goodies.admin',
        ]);
    }

    /**
     * Check if user has goodies admin permissions.
     * Alias for canManageGoodies() for consistency with template usage.
     */
    public static function canAdminGoodies(?User $user = null): bool
    {
        return self::canManageGoodies($user);
    }

    /**
     * Get the highest permission level the user has for backstage.
     * Returns: 'admin', 'view', or null
     */
    public static function getBackstagePermissionLevel(?User $user = null): ?string
    {
        $user = $user ?: auth()->user();

        if (!$user) {
            return null;
        }

        if ($user->hasAnyPermission(['user.type.admin', 'backstage.admin'])) {
            return 'admin';
        }

        if ($user->hasPermission('backstage.view')) {
            return 'view';
        }

        return null;
    }

    /**
     * Get the highest permission level the user has for goodies.
     * Returns: 'admin', 'agent', 'view', or null
     */
    public static function getGoodiesPermissionLevel(?User $user = null): ?string
    {
        $user = $user ?: auth()->user();

        if (!$user) {
            return null;
        }

        // Check system admin and backstage admin first (inherits all permissions)
        if ($user->hasAnyPermission(['user.type.admin', 'backstage.admin'])) {
            return 'admin';
        }

        if ($user->hasPermission('backstage.goodies.admin')) {
            return 'admin';
        }

        if ($user->hasPermission('backstage.goodies.agent')) {
            return 'agent';
        }

        if ($user->hasPermission('backstage.goodies.view')) {
            return 'view';
        }

        return null;
    }

    /**
     * Get all backstage permissions the user has.
     */
    public static function getUserBackstagePermissions(?User $user = null): array
    {
        $user = $user ?: auth()->user();

        if (!$user) {
            return [];
        }

        $backstagePermissions = [
            'user.type.admin',      // System administrators have full access
            'backstage.admin',
            'backstage.view',
            'backstage.goodies.admin',
            'backstage.goodies.agent',
            'backstage.goodies.view',
        ];

        // If user is system admin, return all backstage permissions
        if ($user->hasPermission('user.type.admin')) {
            return [
                'backstage.admin',
                'backstage.view',
                'backstage.goodies.admin',
                'backstage.goodies.agent',
                'backstage.goodies.view',
            ];
        }

        return $user->privileges()
            ->whereIn('name', $backstagePermissions)
            ->pluck('name')
            ->toArray();
    }

    /**
     * Check if user has any backstage-related permissions.
     */
    public static function hasAnyBackstageAccess(?User $user = null): bool
    {
        return !empty(self::getUserBackstagePermissions($user));
    }
}
