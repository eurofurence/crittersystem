<?php

declare(strict_types=1);

namespace Engelsystem\Migrations;

use Engelsystem\Database\Migration\Migration;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Builder as SchemaBuilder;

class AddBackstagePermissions extends Migration
{
    protected Connection $db;

    public $timestamps = true; // phpcs:ignore

    // New backstage management permissions
    protected array $backstage_permissions = [
        'backstage.admin' => 'Full backstage administration access (inherits all backstage permissions)',
        'backstage.view' => 'Access backstage interface and view content',
        'backstage.goodies.admin' => 'Full goodies management (create, edit, delete, distribute)',
        'backstage.goodies.agent' => 'Distribute goodies to users',
        'backstage.goodies.view' => 'View goodies status and quantities',
    ];

    public function __construct(SchemaBuilder $schema)
    {
        parent::__construct($schema);
        $this->db = $this->schema->getConnection();
    }

    public function up(): void
    {
        // Get the Administrative category ID for backstage permissions
        $administrativeCategoryId = $this->getCategoryId('Administrative');

        // Get the Goodie category ID for goodies-specific permissions
        $goodieCategoryId = $this->getCategoryId('Goodie');

        // Add new backstage permissions
        $this->insertPrivilege(
            'backstage.admin',
            $this->backstage_permissions['backstage.admin'],
            $administrativeCategoryId
        );
        $this->insertPrivilege(
            'backstage.view',
            $this->backstage_permissions['backstage.view'],
            $administrativeCategoryId
        );
        // Add goodies permissions to the Goodie category
        $this->insertPrivilege(
            'backstage.goodies.admin',
            $this->backstage_permissions['backstage.goodies.admin'],
            $goodieCategoryId
        );
        $this->insertPrivilege(
            'backstage.goodies.agent',
            $this->backstage_permissions['backstage.goodies.agent'],
            $goodieCategoryId
        );
        $this->insertPrivilege(
            'backstage.goodies.view',
            $this->backstage_permissions['backstage.goodies.view'],
            $goodieCategoryId
        );

        // Create Backstage Admin group
        $this->createBackstageAdminGroup();

        // Assign permissions to the group
        $this->assignPermissionsToBackstageAdmin();
    }

    public function down(): void
    {
        // Remove group-privilege assignments
        $groupId = $this->getGroupIdByName('Backstage Admin');
        if ($groupId) {
            $this->db->table('group_privileges')
                ->where('group_id', $groupId)
                ->delete();

            // Remove the group
            $this->db->table('groups')->where('id', $groupId)->delete();
        }

        // Remove the permissions
        $permissionNames = array_keys($this->backstage_permissions);
        $this->db->table('privileges')
            ->whereIn('name', $permissionNames)
            ->delete();
    }

    /**
     * Get the category ID by name.
     */
    protected function getCategoryId(string $categoryName): ?int
    {
        $category = $this->db->table('privileges_category')
            ->where('name', $categoryName)
            ->first();

        return $category ? $category->id : null;
    }

    /**
     * Insert a new privilege.
     */
    protected function insertPrivilege(string $name, string $description, ?int $categoryId): void
    {
        // Check if privilege already exists
        $exists = $this->db->table('privileges')->where('name', $name)->exists();

        if (!$exists) {
            $this->db->table('privileges')->insert([
                'fk_privileges_category_id' => $categoryId,
                'name' => $name,
                'description' => $description,
            ]);
        }
    }

    /**
     * Create the Backstage Admin group.
     */
    protected function createBackstageAdminGroup(): void
    {
        // Check if group already exists
        $exists = $this->db->table('groups')->where('name', 'Backstage Admin')->exists();

        if (!$exists) {
            $this->db->table('groups')->insert([
                'id' => 101,
                'name' => 'Backstage Admin',
                'slug' => 'backstage-admin',
            ]);
        }
    }

    /**
     * Assign permissions to the Backstage Admin group.
     */
    protected function assignPermissionsToBackstageAdmin(): void
    {
        $groupId = $this->getGroupIdByName('Backstage Admin');

        if (!$groupId) {
            throw new \Exception('Backstage Admin group not found');
        }

        // Permissions to assign to Backstage Admin
        $permissionsToAssign = [
            // All backstage permissions
            'backstage.admin',
            'backstage.view',
            'backstage.goodies.admin',
            'backstage.goodies.agent',
            'backstage.goodies.view',
        ];

        foreach ($permissionsToAssign as $permissionName) {
            $privilegeId = $this->getPrivilegeIdByName($permissionName);

            if ($privilegeId) {
                // Check if assignment already exists
                $exists = $this->db->table('group_privileges')
                    ->where('group_id', $groupId)
                    ->where('privilege_id', $privilegeId)
                    ->exists();

                if (!$exists) {
                    $this->db->table('group_privileges')->insert([
                        'group_id' => $groupId,
                        'privilege_id' => $privilegeId,
                    ]);
                }
            }
        }
    }

    /**
     * Get group ID by name.
     */
    protected function getGroupIdByName(string $name): ?int
    {
        $group = $this->db->table('groups')->where('name', $name)->first();
        return $group ? $group->id : null;
    }

    /**
     * Get privilege ID by name.
     */
    protected function getPrivilegeIdByName(string $name): ?int
    {
        $privilege = $this->db->table('privileges')->where('name', $name)->first();
        return $privilege ? $privilege->id : null;
    }
}
