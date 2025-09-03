<?php

declare(strict_types=1);

namespace Engelsystem\Migrations;

use Engelsystem\Database\Migration\Migration;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder as SchemaBuilder;

class EnhanceGoodiesUserHoursCache extends Migration
{
    use Reference;

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
        $this->schema->table('goodiesv2_user_hours_cache', function (Blueprint $table): void {
            // Add detailed hours breakdown fields
            $table->decimal('day_shifts_hours', 8, 2)->default(0)->after('total_hours');
            $table->decimal('night_shifts_hours', 8, 2)->default(0)->after('day_shifts_hours');
            $table->decimal('freeload_penalty_hours', 8, 2)->default(0)->after('night_shifts_hours');
            $table->decimal('worklog_hours', 8, 2)->default(0)->after('freeload_penalty_hours');

            // Rename existing shift count fields to match new naming
            $table->renameColumn('completed_shifts', 'completed_shifts_count');
            $table->renameColumn('freeloader_shifts', 'freeload_shifts_count');
            $table->renameColumn('overnight_shifts', 'night_shifts_count');

            // Add indexes for new fields
            $table->index(['day_shifts_hours']);
            $table->index(['night_shifts_hours']);
            $table->index(['worklog_hours']);
        });
    }

    /**
     * Reverse the migration
     */
    public function down(): void
    {
        $this->schema->table('goodiesv2_user_hours_cache', function (Blueprint $table): void {
            // Drop the new columns
            $table->dropColumn([
                'day_shifts_hours',
                'night_shifts_hours',
                'freeload_penalty_hours',
                'worklog_hours',
            ]);

            // Rename columns back to original names
            $table->renameColumn('completed_shifts_count', 'completed_shifts');
            $table->renameColumn('freeload_shifts_count', 'freeloader_shifts');
            $table->renameColumn('night_shifts_count', 'overnight_shifts');

            // Drop indexes (they will be automatically dropped with columns)
        });
    }
}