<?php

declare(strict_types=1);

namespace Engelsystem\Migrations;

use Engelsystem\Database\Migration\Migration;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder as SchemaBuilder;

class AddStaffOnlyToLocations extends Migration
{
    protected Connection $db;

    public function __construct(SchemaBuilder $schema)
    {
        parent::__construct($schema);
        $this->db = $this->schema->getConnection();
    }

    public function up(): void
    {
        $this->schema->table('locations', function (Blueprint $table): void {
            // Place after the latest related flag for consistency
            $table->boolean('staff_only')->default(false)->after('dect');
        });
    }

    public function down(): void
    {
        $this->schema->table('locations', function (Blueprint $table): void {
            $table->dropColumn('staff_only');
        });
    }
}
