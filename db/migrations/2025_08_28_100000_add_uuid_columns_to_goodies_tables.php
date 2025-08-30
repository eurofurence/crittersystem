<?php

declare(strict_types=1);

namespace Engelsystem\Migrations;

use Engelsystem\Database\Migration\Migration;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder as SchemaBuilder;
use Illuminate\Support\Str;

class AddUuidColumnsToGoodiesTables extends Migration
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
        // Add UUID column to goodiesv2_categories
        $this->schema->table('goodiesv2_categories', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable()->after('id');
            $table->index(['uuid']);
        });

        // Add UUID column to goodiesv2_items
        $this->schema->table('goodiesv2_items', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable()->after('id');
            $table->index(['uuid']);
        });

        // Generate UUIDs for existing records
        $db = $this->schema->getConnection();

        // Generate UUIDs for categories
        $categories = $db->table('goodiesv2_categories')->whereNull('uuid')->get();
        foreach ($categories as $category) {
            $db->table('goodiesv2_categories')
                ->where('id', $category->id)
                ->update(['uuid' => Str::uuid()]);
        }

        // Generate UUIDs for items
        $items = $db->table('goodiesv2_items')->whereNull('uuid')->get();
        foreach ($items as $item) {
            $db->table('goodiesv2_items')
                ->where('id', $item->id)
                ->update(['uuid' => Str::uuid()]);
        }

        // Make UUID columns required and unique
        $this->schema->table('goodiesv2_categories', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable(false)->unique()->change();
        });

        $this->schema->table('goodiesv2_items', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable(false)->unique()->change();
        });
    }

    /**
     * Reverse the migration
     */
    public function down(): void
    {
        $this->schema->table('goodiesv2_categories', function (Blueprint $table): void {
            $table->dropIndex(['uuid']);
            $table->dropColumn('uuid');
        });

        $this->schema->table('goodiesv2_items', function (Blueprint $table): void {
            $table->dropIndex(['uuid']);
            $table->dropColumn('uuid');
        });
    }
}
