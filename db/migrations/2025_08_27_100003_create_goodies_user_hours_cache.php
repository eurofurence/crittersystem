<?php

declare(strict_types=1);

namespace Engelsystem\Migrations;

use Engelsystem\Database\Migration\Migration;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder as SchemaBuilder;

class CreateGoodiesUserHoursCache extends Migration
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
        $this->schema->create('goodiesv2_user_hours_cache', function (Blueprint $table): void {
            $table->integer('user_id')->unsigned()->primary();
            $table->decimal('total_hours', 8, 2)->default(0);
            $table->integer('completed_shifts')->default(0);
            $table->integer('freeloader_shifts')->default(0);
            $table->integer('overnight_shifts')->default(0);
            $table->timestamp('last_calculated_at')->useCurrent();
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onDelete('cascade');

            $table->index(['total_hours']);
            $table->index(['last_calculated_at']);
        });
    }

    /**
     * Reverse the migration
     */
    public function down(): void
    {
        $this->schema->dropIfExists('goodiesv2_user_hours_cache');
    }
}
