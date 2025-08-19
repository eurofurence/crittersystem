<?php

declare(strict_types=1);

namespace Engelsystem\Migrations;

use Engelsystem\Database\Migration\Migration;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Builder as SchemaBuilder;

class AddCertificationManagerPermissions extends Migration
{
    protected Connection $db;

    public $timestamps = true; // phpcs:ignore

    // New certification management permissions
    protected array $certification_permissions = [
        'certificates.admin' => 'Full certification administration access',
        'certificates.manage' => 'Manage user certifications',
        'certificates.view' => 'View certification information',
        'certificates.assign' => 'Assign certifications to users',
        'certificates.revoke' => 'Revoke user certifications',
        'certificates.approve' => 'Approve pending certifications',
    ];

    public function __construct(SchemaBuilder $schema)
    {
        parent::__construct($schema);
        $this->db = $this->schema->getConnection();
    }

    public function up(): void
    {
        // Get the Certification category ID
        $certificationCategoryId = $this->getCertificationCategoryId();

        // Add new certification management permissions
        foreach ($this->certification_permissions as $name => $description) {
            $this->insertPrivilege($name, $description, $certificationCategoryId);
        }

        // Create Certification Manager group
        $this->createCertificationManagerGroup();

        // Assign permissions to the group
        $this->assignPermissionsToCertificationManager();
    }

    public function down(): void
    {
        // Remove group-privilege assignments
        $groupId = $this->getGroupIdByName('Certification Manager');
        if ($groupId) {
            $this->db->table('group_privileges')
                ->where('group_id', $groupId)
                ->delete();

            // Remove the group
            $this->db->table('groups')->where('id', $groupId)->delete();
        }

        // Remove the permissions
        $permissionNames = array_keys($this->certification_permissions);
        $this->db->table('privileges')
            ->whereIn('name', $permissionNames)
            ->delete();
    }

    /**
     * Get the Certification category ID.
     */
    protected function getCertificationCategoryId(): ?int
    {
        $category = $this->db->table('privileges_category')
            ->where('name', 'Certification')
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
     * Create the Certification Manager group.
     */
    protected function createCertificationManagerGroup(): void
    {
        // Check if group already exists
        $exists = $this->db->table('groups')->where('name', 'Certification Manager')->exists();

        if (!$exists) {
            $this->db->table('groups')->insert([
                'id' => 100,
                'name' => 'Certification Manager',
                'slug' => 'certification-manager',
            ]);
        }
    }

    /**
     * Assign permissions to the Certification Manager group.
     */
    protected function assignPermissionsToCertificationManager(): void
    {
        $groupId = $this->getGroupIdByName('Certification Manager');

        if (!$groupId) {
            throw new \Exception('Certification Manager group not found');
        }

        // Permissions to assign to Certification Manager
        $permissionsToAssign = [
            // Core certification permissions
            'certificates.admin',
            'certificates.manage',
            'certificates.view',
            'certificates.assign',
            'certificates.revoke',
            'certificates.approve',
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
