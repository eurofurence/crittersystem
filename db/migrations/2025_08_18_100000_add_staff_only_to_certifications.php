<?php

declare(strict_types=1);

namespace Engelsystem\Migrations;

use Engelsystem\Database\Migration\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Builder as SchemaBuilder;

class AddStaffOnlyToCertifications extends Migration
{
    protected Connection $db;

    public function __construct(SchemaBuilder $schema)
    {
        parent::__construct($schema);
        $this->db = $this->schema->getConnection();
    }

    public function up(): void
    {
        $this->schema->table('certifications', function (Blueprint $table): void {
            $table->boolean('staff_only')->default(false)->after('allow_self_confirmation');
            $table->index('staff_only');
        });
    }

    public function down(): void
    {
        $this->schema->table('certifications', function (Blueprint $table): void {
            $table->dropIndex(['staff_only']);
            $table->dropColumn('staff_only');
        });
    }
}
