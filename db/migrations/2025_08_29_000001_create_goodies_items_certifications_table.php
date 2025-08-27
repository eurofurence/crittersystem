<?php

declare(strict_types=1);

namespace Engelsystem\Migrations;

use Engelsystem\Database\Migration\Migration;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder as SchemaBuilder;

class CreateGoodiesItemsCertificationsTable extends Migration
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
        $this->schema->create('goodiesv2_items_certifications', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('item_id')->unsigned(); // Match goodiesv2_items.id (INT UNSIGNED)
            $table->unsignedBigInteger('certification_id'); // Match certifications.id (BIGINT UNSIGNED)
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('item_id')
                ->references('id')->on('goodiesv2_items')
                ->onDelete('cascade');

            $table->foreign('certification_id')
                ->references('id')->on('certifications')
                ->onDelete('cascade');

            // Prevent duplicate relationships
            $table->unique(['item_id', 'certification_id'], 'goodies_item_certification_unique');

            // Indexes for performance
            $table->index(['item_id']);
            $table->index(['certification_id']);
        });
    }

    /**
     * Reverse the migration
     */
    public function down(): void
    {
        $this->schema->dropIfExists('goodiesv2_items_certifications');
    }
}
