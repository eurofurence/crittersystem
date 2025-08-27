<?php

declare(strict_types=1);

namespace Engelsystem\Migrations;

use Engelsystem\Database\Migration\Migration;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder as SchemaBuilder;

class AddQuantityLimitAndUuidToGoodiesItems extends Migration
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
        $this->schema->table('goodiesv2_items', function (Blueprint $table): void {
            // Add UUID field if it doesn't exist
            if (!$this->schema->hasColumn('goodiesv2_items', 'uuid')) {
                $table->uuid('uuid')->after('id');
                $table->unique('uuid');
            }

            // Add quantity limit per person field
            $table->integer('max_per_person')->nullable()->after('current_quantity')
                ->comment('Maximum quantity one person can receive. NULL means no limit.');

            // Add index for performance
            $table->index(['max_per_person']);
        });

        // Generate UUIDs for existing records that don't have them
        $items = $this->db->table('goodiesv2_items')->whereNull('uuid')->get();
        foreach ($items as $item) {
            $this->db->table('goodiesv2_items')
                ->where('id', $item->id)
                ->update(['uuid' => $this->generateUuid()]);
        }
    }

    /**
     * Reverse the migration
     */
    public function down(): void
    {
        $this->schema->table('goodiesv2_items', function (Blueprint $table): void {
            $table->dropIndex(['max_per_person']);
            $table->dropColumn('max_per_person');

            // Only drop UUID if it exists (in case this migration is rolled back)
            if ($this->schema->hasColumn('goodiesv2_items', 'uuid')) {
                $table->dropUnique(['uuid']);
                $table->dropColumn('uuid');
            }
        });
    }

    /**
     * Generate a UUID v4
     */
    private function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }
}
