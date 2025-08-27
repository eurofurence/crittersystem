<?php

declare(strict_types=1);

namespace Engelsystem\Migrations;

use Engelsystem\Database\Migration\Migration;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder as SchemaBuilder;

class RenameSortOrderToDisplayOrderInGoodiesTables extends Migration
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
        // Rename sort_order to display_order in goodiesv2_categories
        $this->schema->table('goodiesv2_categories', function (Blueprint $table): void {
            $table->dropIndex(['sort_order']);
        });

        $this->schema->table('goodiesv2_categories', function (Blueprint $table): void {
            $table->renameColumn('sort_order', 'display_order');
        });

        $this->schema->table('goodiesv2_categories', function (Blueprint $table): void {
            $table->index(['display_order']);
        });

        // Rename sort_order to display_order in goodiesv2_items
        $this->schema->table('goodiesv2_items', function (Blueprint $table): void {
            $table->dropIndex(['sort_order']);
        });

        $this->schema->table('goodiesv2_items', function (Blueprint $table): void {
            $table->renameColumn('sort_order', 'display_order');
        });

        $this->schema->table('goodiesv2_items', function (Blueprint $table): void {
            $table->index(['display_order']);
        });
    }

    /**
     * Reverse the migration
     */
    public function down(): void
    {
        // Rename display_order back to sort_order in goodiesv2_categories
        $this->schema->table('goodiesv2_categories', function (Blueprint $table): void {
            $table->dropIndex(['display_order']);
        });

        $this->schema->table('goodiesv2_categories', function (Blueprint $table): void {
            $table->renameColumn('display_order', 'sort_order');
        });

        $this->schema->table('goodiesv2_categories', function (Blueprint $table): void {
            $table->index(['sort_order']);
        });

        // Rename display_order back to sort_order in goodiesv2_items
        $this->schema->table('goodiesv2_items', function (Blueprint $table): void {
            $table->dropIndex(['display_order']);
        });

        $this->schema->table('goodiesv2_items', function (Blueprint $table): void {
            $table->renameColumn('display_order', 'sort_order');
        });

        $this->schema->table('goodiesv2_items', function (Blueprint $table): void {
            $table->index(['sort_order']);
        });
    }
}
