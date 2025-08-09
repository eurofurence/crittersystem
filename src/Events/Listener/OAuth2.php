<?php

declare(strict_types=1);

namespace Engelsystem\Events\Listener;

use Engelsystem\Config\Config;
use Engelsystem\Helpers\Authenticator;
use Engelsystem\Models\Department\Department;
use Engelsystem\Models\Group;
use Engelsystem\Models\User\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;

class OAuth2
{
    protected array $config;

    public function __construct(Config $config, protected LoggerInterface $log, protected Authenticator $auth)
    {
        $this->config = $config->get('oauth');
    }

    /**
     * Handle OAuth login event
     *
     * @param string     $event    Event name
     * @param string     $provider OAuth provider name
     * @param Collection $data     OAuth userdata
     */
    public function login(string $event, string $provider, Collection $data): void
    {
        // Get user info
        $user = $this->auth->user();
        if (!$user) {
            $this->log->warning('OAuth {provider}: No authenticated user found', ['provider' => $provider]);
            return;
        }

        // Get departments configuration
        $departments = $this->config[$provider]['departments'] ?? [];
        if (empty($departments)) {
            $this->log->info('OAuth {provider}: No departments configured', ['provider' => $provider]);
            return;
        }

        // Get user groups from OAuth data
        $groupsKey = ($this->config[$provider] ?? [])['groups'] ?? 'groups';
        $userGroups = $data->get($groupsKey, []);
        if (empty($userGroups)) {
            $this->log->info(
                'OAuth {provider}: User {user} has no groups',
                ['provider' => $provider, 'user' => $user->name]
            );
        }

        // Process departments
        foreach ($userGroups as $groupName) {
            if (!isset($departments[$groupName])) {
                continue;
            }

            $departmentConfig = $departments[$groupName];
            $this->processDepartment($provider, $user, $groupName, $departmentConfig);
        }

        // Process user promotion
        $this->processUserPromotion($provider, $user, $departments['PROMOTE'] ?? []);
    }

    /**
     * Process a department for a user
     *
     * @param string $provider OAuth provider name
     * @param User $user User to process
     * @param string $departmentId Department ID from the IDP/SSO
     * @param array $departmentConfig Department configuration
     */
    protected function processDepartment(
        string $provider,
        User $user,
        string $departmentId,
        array $departmentConfig
    ): void {
        if (!$user) {
            $this->log->warning(
                'OAuth {provider}: Cannot process department {id} for null user',
                [
                    'provider' => $provider,
                    'id' => $departmentId,
                ]
            );
            return;
        }

        // Check if department exists
        $department = $this->findOrCreateDepartment($provider, $departmentId, $departmentConfig);

        if (!$department) {
            return;
        }

        try {
            // Add user to department if not already a member
            if (!$user->departments()->where('department_id', $department->id)->exists()) {
                $user->departments()->attach($department, [
                    'uuid' => (string) Str::uuid(),
                    'status' => 'approved',
                ]);
                $this->log->info(
                    'OAuth {provider}: Added user {user} to department {department}',
                    [
                        'provider' => $provider,
                        'user' => $user->name,
                        'department' => $department->name,
                    ]
                );
            }

            // Assign permissions
            $this->assignPermissions($provider, $user, $departmentConfig['permission_slugs'] ?? []);
        } catch (\Exception $e) {
            $this->log->error(
                'OAuth {provider}: Error processing department {department} for user {user}: {error}',
                [
                    'provider' => $provider,
                    'department' => $department->name,
                    'user' => $user->name,
                    'error' => $e->getMessage(),
                ]
            );
        }
    }

    /**
     * Find or create a department
     *
     * @param string $provider OAuth provider name
     * @param string $departmentId Department ID from the IDP/SSO
     * @param array $departmentConfig Department configuration
     * @return Department|null Department or null if not found and not created
     */
    protected function findOrCreateDepartment(
        string $provider,
        string $departmentId,
        array $departmentConfig
    ): ?Department {
        // Check if department exists by slug
        $slug = $departmentConfig['slug'] ?? null;
        if (!$slug) {
            $this->log->warning(
                'OAuth {provider}: Department {id} has no slug defined',
                [
                    'provider' => $provider,
                    'id' => $departmentId,
                ]
            );
            return null;
        }

        // Try to find department by slug or name
        $department = Department::where('slug', $slug)
            ->orWhere('name', $departmentConfig['name'] ?? $slug)
            ->first();

        // If department doesn't exist and auto-creation is enabled, create it
        if (!$department && ($this->config[$provider]['departments']['policy_department_create'] ?? '') === 'auto') {
            $department = new Department();
            $department->name = $departmentConfig['name'] ?? $slug;
            $department->description = $departmentConfig['description'] ?? '';
            $department->staff_only = $departmentConfig['default_hidden'] ?? false;

            // Generate slug from name
            $baseSlug = Str::slug($department->name);
            $generatedSlug = $baseSlug;
            $counter = 1;

            // Check for conflicts and add counter if needed
            while (Department::where('slug', $generatedSlug)->exists()) {
                $generatedSlug = $baseSlug . '-' . $counter;
                $counter++;
            }

            $department->slug = $generatedSlug;
            $department->save();

            $this->log->info(
                'OAuth {provider}: Created department {name} with ID {id}',
                [
                    'provider' => $provider,
                    'name' => $department->name,
                    'id' => $department->id,
                ]
            );
        } elseif (!$department) {
            $this->log->info(
                'OAuth {provider}: Department {name} not found and auto-creation disabled',
                [
                    'provider' => $provider,
                    'name' => $departmentConfig['name'] ?? $slug,
                ]
            );
            return null;
        }

        return $department;
    }

    /**
     * Assign permissions to a user
     *
     * @param string $provider OAuth provider name
     * @param User $user User to assign permissions to
     * @param array $permissionSlugs Permission slugs to assign
     */
    protected function assignPermissions(string $provider, User $user, array $permissionSlugs): void
    {
        if (!$user) {
            $this->log->warning(
                'OAuth {provider}: Cannot assign permissions to null user',
                [
                    'provider' => $provider,
                ]
            );
            return;
        }

        if (empty($permissionSlugs)) {
            return;
        }

        foreach ($permissionSlugs as $slug) {
            if (empty($slug)) {
                continue;
            }

            try {
                // Find group by name (since there's no slug field)
                $group = Group::where('slug', $slug)->first();

                if (!$group) {
                    $this->log->info(
                        'OAuth {provider}: Permission {slug} not found, skipping',
                        [
                            'provider' => $provider,
                            'slug' => $slug,
                        ]
                    );
                    continue;
                }

                // Add user to group if not already a member
                if (!$user->groups->contains($group->id)) {
                    $user->groups()->attach($group);
                    $this->log->info(
                        'OAuth {provider}: Added user {user} to group {group}',
                        [
                            'provider' => $provider,
                            'user' => $user->name,
                            'group' => $group->name,
                        ]
                    );
                }
            } catch (\Exception $e) {
                $this->log->error(
                    'OAuth {provider}: Error assigning permission {slug} to user {user}: {error}',
                    [
                        'provider' => $provider,
                        'slug' => $slug,
                        'user' => $user->name,
                        'error' => $e->getMessage(),
                    ]
                );
            }
        }
    }

    /**
     * Process user promotion
     *
     * @param string $provider OAuth provider name
     * @param User $user User to process
     * @param array $promoteConfig Promotion configuration
     */
    protected function processUserPromotion(string $provider, User $user, array $promoteConfig): void
    {
        if (!$user) {
            $this->log->warning(
                'OAuth {provider}: Cannot process promotion for null user',
                [
                    'provider' => $provider,
                ]
            );
            return;
        }

        if (empty($promoteConfig)) {
            return;
        }

        try {
            // Get user ID from OAuth provider
            $userId = $user->oauth->where('provider', $provider)->first()?->identifier;

            if (!$userId || !isset($promoteConfig[$userId])) {
                return;
            }

            $permissionSlugs = $promoteConfig[$userId] ?? [];
            if (empty($permissionSlugs)) {
                return;
            }

            $this->assignPermissions($provider, $user, $permissionSlugs);

            $this->log->info(
                'OAuth {provider}: Promoted user {user} with permissions {permissions}',
                [
                    'provider' => $provider,
                    'user' => $user->name,
                    'permissions' => implode(', ', $permissionSlugs),
                ]
            );
        } catch (\Exception $e) {
            $this->log->error(
                'OAuth {provider}: Error promoting user {user}: {error}',
                [
                    'provider' => $provider,
                    'user' => $user->name,
                    'error' => $e->getMessage(),
                ]
            );
        }
    }
}
