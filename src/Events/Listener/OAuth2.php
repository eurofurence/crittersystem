<?php

declare(strict_types=1);

namespace Engelsystem\Events\Listener;

use Engelsystem\Config\Config;
use Engelsystem\Database\Db;
use Engelsystem\Helpers\Authenticator;
use Engelsystem\Models\AngelType;
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

        $this->log->info('User ({user}) login via OAuth: {provider}', ['provider' => $provider, 'user' => $user->name]);

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
        $matchedAny = false;
        foreach ($userGroups as $groupName) {
            if (!isset($departments[$groupName])) {
                continue;
            }

            $matchedAny = true;
            $departmentConfig = $departments[$groupName];
            $this->processDepartment($provider, $user, $groupName, $departmentConfig);

            // Process critter types (AngelTypes) if configured for this department
            $this->processCritterTypes($provider, $user, $departmentConfig['critter_type'] ?? []);
        }

        // If no IDP group matched any configured department, add default critter type
        if (!$matchedAny) {
            $this->addAllroundCritterIfNoMatch($provider, $user);
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
                    $user->groups()->syncWithoutDetaching($group);
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

        // Final cleanup: ensure no duplicate group assignments for this user
        try {
            $connection = Db::connection();

            $duplicates = $connection->table('users_groups')
                ->select('group_id')
                ->selectRaw('MIN(id) as keep_id')
                ->where('user_id', $user->id)
                ->groupBy('group_id')
                ->havingRaw('COUNT(*) > 1')
                ->get();

            foreach ($duplicates as $dup) {
                $deleted = $connection->table('users_groups')
                    ->where('user_id', $user->id)
                    ->where('group_id', $dup->group_id)
                    ->where('id', '<>', $dup->keep_id)
                    ->delete();

                if ($deleted > 0) {
                    $this->log->info(
                        'OAuth {provider}: Removed {count} duplicate group rows' .
                        ' for user {user} in group {groupId}',
                        [
                            'provider' => $provider,
                            'count' => $deleted,
                            'user' => $user->name,
                            'groupId' => $dup->group_id,
                        ]
                    );
                }
            }
        } catch (\Exception $e) {
            $this->log->error(
                'OAuth {provider}: Error during duplicate group assignment cleanup for user {user}: {error}',
                [
                    'provider' => $provider,
                    'user' => $user->name,
                    'error' => $e->getMessage(),
                ]
            );
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

    /**
     * Add user to default critter type "Allround Critter" when no IDP groups matched
     *
     * - Silently ignores if the critter type does not exist
     * - Confirms membership automatically if the critter type is restricted
     */
    protected function addAllroundCritterIfNoMatch(string $provider, User $user): void
    {
        try {
            $angelType = AngelType::where('name', 'Allround Critter')->first();
            if (!$angelType) {
                // silently ignore if not found
                return;
            }

            $exists = $user->userAngelTypes()->where('angel_type_id', $angelType->id)->exists();
            if (!$exists) {
                $pivot = ['supporter' => false];
                if ($angelType->restricted) {
                    $pivot['confirm_user_id'] = $user->id;
                }

                $user->userAngelTypes()->attach($angelType, $pivot);

                $this->log->info(
                    'OAuth {provider}: Added user {user} to critter type {type}' .
                    ($angelType->restricted ? ' and confirmed' : ''),
                    [
                        'provider' => $provider,
                        'user' => $user->name,
                        'type' => $angelType->name,
                    ]
                );
            } else {
                // If already attached but restricted and not confirmed, confirm now
                if ($angelType->restricted) {
                    $pivotRow = $user->userAngelTypes()
                        ->where('angel_type_id', $angelType->id)
                        ->first()?->pivot;

                    if ($pivotRow && empty($pivotRow->confirm_user_id)) {
                        $user->userAngelTypes()
                            ->updateExistingPivot($angelType->id, ['confirm_user_id' => $user->id]);

                        $this->log->info(
                            'OAuth {provider}: Confirmed user {user} for critter type {type}',
                            [
                                'provider' => $provider,
                                'user' => $user->name,
                                'type' => $angelType->name,
                            ]
                        );
                    }
                }
            }
        } catch (\Exception $e) {
            $this->log->error(
                'OAuth {provider}: Error adding default critter type for user {user}: {error}',
                [
                    'provider' => $provider,
                    'user' => $user->name,
                    'error' => $e->getMessage(),
                ]
            );
        }
    }

    /**
     * Process critter types (AngelTypes) for a user based on department config
     *
     * @param string $provider OAuth provider name
     * @param User   $user     User to process
     * @param array  $critterTypes Array of AngelType names
     */
    protected function processCritterTypes(string $provider, User $user, array $critterTypes): void
    {
        if (!$user) {
            $this->log->warning(
                'OAuth {provider}: Cannot process critter types for null user',
                [
                    'provider' => $provider,
                ]
            );
            return;
        }

        if (empty($critterTypes)) {
            return;
        }

        foreach ($critterTypes as $typeName) {
            $typeName = is_string($typeName) ? trim($typeName) : '';
            if ($typeName === '') {
                continue;
            }

            try {
                $angelType = AngelType::where('name', $typeName)->first();

                if (!$angelType) {
                    // Skip silently if not found
                    $this->log->info(
                        'OAuth {provider}: Critter type {type} not found, skipping',
                        [
                            'provider' => $provider,
                            'type' => $typeName,
                        ]
                    );
                    continue;
                }

                // Check if already attached
                $exists = $user->userAngelTypes()->where('angel_type_id', $angelType->id)->exists();

                if (!$exists) {
                    $pivot = ['supporter' => false];
                    if ($angelType->restricted) {
                        // Auto-confirm if needed
                        $pivot['confirm_user_id'] = $user->id;
                    }

                    $user->userAngelTypes()->attach($angelType, $pivot);

                    $this->log->info(
                        'OAuth {provider}: Added user {user} to critter type {type}' .
                        ($angelType->restricted ? ' and confirmed' : ''),
                        [
                            'provider' => $provider,
                            'user' => $user->name,
                            'type' => $angelType->name,
                        ]
                    );
                } else {
                    // If already attached but restricted and not confirmed, confirm now
                    if ($angelType->restricted) {
                        $pivotRow = $user->userAngelTypes()
                            ->where('angel_type_id', $angelType->id)
                            ->first()?->pivot;

                        if ($pivotRow && empty($pivotRow->confirm_user_id)) {
                            $user->userAngelTypes()
                                ->updateExistingPivot($angelType->id, ['confirm_user_id' => $user->id]);

                            $this->log->info(
                                'OAuth {provider}: Confirmed user {user} for critter type {type}',
                                [
                                    'provider' => $provider,
                                    'user' => $user->name,
                                    'type' => $angelType->name,
                                ]
                            );
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->log->error(
                    'OAuth {provider}: Error processing critter type {type} for user {user}: {error}',
                    [
                        'provider' => $provider,
                        'type' => $typeName,
                        'user' => $user->name,
                        'error' => $e->getMessage(),
                    ]
                );
            }
        }
    }
}
