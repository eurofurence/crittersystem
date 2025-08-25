<?php

declare(strict_types=1);

namespace Engelsystem\Migrations;

use Engelsystem\Database\Migration\Migration;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Builder as SchemaBuilder;

class AddDirectorPermission extends Migration
{
    protected Connection $db;

    protected array $new_bod = [
        'group_id' => '110',
        'group_name' => 'BoD',
        'group_slug' => 'bod',
        'privilege_category_name' => 'Staff',
        'privilege_name' => 'user.type.bod',
        'privilege_description' => 'BoD user level',
    ];

    protected array $new_director = [
        'group_id' => '120',
        'group_name' => 'Director',
        'group_slug' => 'director',
        'privilege_category_name' => 'Staff',
        'privilege_name' => 'user.type.director',
        'privilege_description' => 'Director user level',
    ];

    public function __construct(SchemaBuilder $schema)
    {
        parent::__construct($schema);
        $this->db = $this->schema->getConnection();
    }

    public function up(): void
    {
        $this->addPermission($this->new_bod);
        $this->addPermission($this->new_director);
    }

    public function down(): void
    {
        $this->removePermission($this->new_bod);
        $this->removePermission($this->new_director);
    }

    // #################################################################
    // Support Functions
    // #################################################################
    protected function addPermission(array $permission): void
    {
        // get the privileges_category ID
        $privilegesCategoryId = $this->db->table('privileges_category')
            ->where('name', $permission['privilege_category_name'])
            ->get(['id'])
            ->first()->id;

        // Add the privilege with the id
        $this->db->table('privileges')
            ->insert([
                'fk_privileges_category_id' => $privilegesCategoryId,
                'name' => $permission['privilege_name'],
                'description' => $permission['privilege_description'],
            ]);

        // Get the ID from the permission added
        $privilegesId = $this->db->table('privileges')
            ->where('name', $permission['privilege_name'])
            ->get(['id'])
            ->first()->id;

        // Add a new group
        $this->db->table('groups')
            ->insert([
                'id' => $permission['group_id'],
                'name' => $permission['group_name'],
                'slug' => $permission['group_slug'],
            ]);

        // Now, add to the group_privileges
        $this->db->table(table: 'group_privileges')
            ->insert([
                'group_id' => $permission['group_id'],
                'privilege_id' => $privilegesId,
            ]);
    }

    protected function removePermission(array $permission): void
    {

        // Remove from the group_privileges
        $this->db->table(table: 'group_privileges')
            ->where(column: ['group_id' => $permission['group_id']])
            ->delete();

        // Remove the group
        $this->db->table(table: 'groups')
            ->where(column: ['id' => $permission['group_id']])
            ->delete();

        // Remove the privilege
        $this->db->table(table: 'privileges')
            ->where(column: ['name' => $permission['privilege_name']])
            ->delete();
    }
}
