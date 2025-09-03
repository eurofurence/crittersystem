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
        // Add detailed hours breakdown fields first
        $this->schema->table('goodiesv2_user_hours_cache', function (Blueprint $table): void {
            $table->decimal('day_shifts_hours', 8, 2)->default(0)->after('total_hours');
            $table->decimal('night_shifts_hours', 8, 2)->default(0)->after('day_shifts_hours');
            $table->decimal('freeload_penalty_hours', 8, 2)->default(0)->after('night_shifts_hours');
            $table->decimal('worklog_hours', 8, 2)->default(0)->after('freeload_penalty_hours');
        });

        // Rename columns in separate operations for SQLite compatibility
        $this->schema->table('goodiesv2_user_hours_cache', function (Blueprint $table): void {
            $table->renameColumn('completed_shifts', 'completed_shifts_count');
        });

        $this->schema->table('goodiesv2_user_hours_cache', function (Blueprint $table): void {
            $table->renameColumn('freeloader_shifts', 'freeload_shifts_count');
        });

        $this->schema->table('goodiesv2_user_hours_cache', function (Blueprint $table): void {
            $table->renameColumn('overnight_shifts', 'night_shifts_count');
        });

        // Add indexes for new fields
        $this->schema->table('goodiesv2_user_hours_cache', function (Blueprint $table): void {
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
        // Drop indexes first
        $this->schema->table('goodiesv2_user_hours_cache', function (Blueprint $table): void {
            $table->dropIndex(['day_shifts_hours']);
            $table->dropIndex(['night_shifts_hours']);
            $table->dropIndex(['worklog_hours']);
        });

        // Rename columns back in separate operations for SQLite compatibility
        $this->schema->table('goodiesv2_user_hours_cache', function (Blueprint $table): void {
            $table->renameColumn('completed_shifts_count', 'completed_shifts');
        });

        $this->schema->table('goodiesv2_user_hours_cache', function (Blueprint $table): void {
            $table->renameColumn('freeload_shifts_count', 'freeloader_shifts');
        });

        $this->schema->table('goodiesv2_user_hours_cache', function (Blueprint $table): void {
            $table->renameColumn('night_shifts_count', 'overnight_shifts');
        });

        // Drop the new columns
        $this->schema->table('goodiesv2_user_hours_cache', function (Blueprint $table): void {
            $table->dropColumn([
                'day_shifts_hours',
                'night_shifts_hours',
                'freeload_penalty_hours',
                'worklog_hours',
            ]);
        });
    }
}
