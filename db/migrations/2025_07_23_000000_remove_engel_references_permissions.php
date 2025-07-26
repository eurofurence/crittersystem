<?php

declare(strict_types=1);

namespace Engelsystem\Migrations;

use Engelsystem\Database\Migration\Migration;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Builder as SchemaBuilder;

class RemoveEngelReferencesPermissions extends Migration
{
    protected Connection $db;

    public function __construct(SchemaBuilder $schema)
    {
        parent::__construct($schema);
        $this->db = $this->schema->getConnection();
    }

    /**
     * Run the migration
     */
    public function up(): void
    {
        //ID Name
        //20 Angel
        $this->db->table('groups')
            ->where(column: 'id', operator: '=', value: 20)
            ->update([
                'name' => 'Critter',
            ]);

        //ID Name
        //30 Welcome Angel
        $this->db->table('groups')
            ->where(column: 'id', operator: '=', value: 30)
            ->update([
                'name' => 'Welcome Critter',
            ]);

        //ID Name
        //35 Voucher Angel
        $this->db->table('groups')
            ->where(column: 'id', operator: '=', value: 35)
            ->update([
                'name' => 'Voucher Critter',
            ]);
    }

    /**
     * Reverse the migration
     */
    public function down(): void
    {
        //ID Name
        //20 Critter
        $this->db->table('groups')
            ->where(column: 'id', operator: '=', value: 20)
            ->update([
                'name' => 'Angel',
            ]);

        //ID Name
        //30 Welcome Critter
        $this->db->table('groups')
            ->where(column: 'id', operator: '=', value: 30)
            ->update([
                'name' => 'Welcome Angel',
            ]);

        //ID Name
        //35 Voucher Critter
        $this->db->table('groups')
            ->where(column: 'id', operator: '=', value: 35)
            ->update([
                'name' => 'Voucher Angel',
            ]);
    }
}
