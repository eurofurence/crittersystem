<?php

declare(strict_types=1);

namespace Engelsystem\Migrations;

use Engelsystem\Database\Migration\Migration;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Builder as SchemaBuilder;

class FixStaffPermissions extends Migration
{
    protected Connection $db;

    protected string $departments_permission = 'dept.view';

    protected string $staff_permission = 'user.type.staff';

    protected string $newStaffGroupName = 'Staff';
    protected string $oldStaffGroupName = 'Staff - Internal';

    public function __construct(SchemaBuilder $schema)
    {
        parent::__construct($schema);
        $this->db = $this->schema->getConnection();
    }

    public function up(): void
    {
        // Change the group name
        $this->updateGroupName(oldName: $this->oldStaffGroupName, newName: $this->newStaffGroupName);

        // Add permissions to staff
        $this->insertPrivilegeToGroup(privilegeName: $this->staff_permission, GroupName: $this->newStaffGroupName);
        $this->insertPrivilegeToGroup(
            privilegeName: $this->departments_permission,
            GroupName: $this->newStaffGroupName
        );
    }

    public function down(): void
    {
        // Add permissions to staff
        $this->removePrivilegeFromGroup(privilegeName: $this->staff_permission, GroupName: $this->newStaffGroupName);
        $this->removePrivilegeFromGroup(
            privilegeName: $this->departments_permission,
            GroupName: $this->newStaffGroupName
        );

        // Change the group name
        $this->updateGroupName(oldName: $this->newStaffGroupName, newName: $this->oldStaffGroupName);
    }

    // #################################################################
    // Support Functions
    // #################################################################
    protected function updateGroupName(string $oldName, string $newName): void
    {
        $this->db->table('groups')
            ->where('name', $oldName)
            ->update([
                'name' => $newName,
            ]);
    }

    protected function insertPrivilegeToGroup(string $privilegeName, string $GroupName): void
    {
        // find the Privilege ID
        $privilegeId = $this->db->table('privileges')
            ->where('name', $privilegeName)
            ->get(['id'])
            ->first()->id;

        // get Group ID
        $groupId = $this->db->table('groups')
            ->where('name', $GroupName)
            ->get(['id'])
            ->first()->id;

        // Insert on the group_privileges table
        $this->db->table(table: 'group_privileges')
            ->insert([
                'group_id' => $groupId,
                'privilege_id' => $privilegeId,
            ]);
    }

    protected function removePrivilegeFromGroup(string $privilegeName, string $GroupName): void
    {
        // find the Privilege ID
        $privilegeId = $this->db->table('privileges')
            ->where('name', $privilegeName)
            ->get(['id'])
            ->first()->id;

        // get Group ID
        $groupId = $this->db->table('groups')
            ->where('name', $GroupName)
            ->get(['id'])
            ->first()->id;

        // Insert on the group_privileges table
        $this->db->table(table: 'group_privileges')
            ->where(column: [
                'group_id' => $groupId,
                'privilege_id' => $privilegeId,
            ])->delete();
    }
}
