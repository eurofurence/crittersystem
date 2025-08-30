<?php

declare(strict_types=1);

namespace Engelsystem\Migrations;

use Engelsystem\Database\Migration\Migration;
use Illuminate\Database\Schema\Blueprint;

class AllowNullCategoryIdInGoodiesItems extends Migration
{
    /**
     * Run the migration
     */
    public function up(): void
    {
        $this->schema->table('goodiesv2_items', function (Blueprint $table): void {
            // Drop the existing foreign key constraint first
            $table->dropForeign(['category_id']);

            // Modify the column to allow NULL
            $table->integer('category_id')->unsigned()->nullable()->change();

            // Re-add the foreign key constraint with nullable support
            $table->foreign('category_id')
                ->references('id')->on('goodiesv2_categories')
                ->onDelete('set null'); // Set to null instead of cascade when category is deleted
        });
    }

    /**
     * Reverse the migration
     */
    public function down(): void
    {
        $this->schema->table('goodiesv2_items', function (Blueprint $table): void {
            // Drop the foreign key constraint
            $table->dropForeign(['category_id']);

            // Change the column back to NOT NULL (need to handle existing NULL values first)
            // First set any NULL values to a default category or remove those rows
            $this->schema->getConnection()->statement(
                'UPDATE goodiesv2_items SET category_id = 1 WHERE category_id IS NULL'
            );

            // Modify the column back to NOT NULL
            $table->integer('category_id')->unsigned()->change();

            // Re-add the original foreign key constraint with cascade
            $table->foreign('category_id')
                ->references('id')->on('goodiesv2_categories')
                ->onDelete('cascade');
        });
    }
}
