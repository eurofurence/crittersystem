<?php

declare(strict_types=1);

namespace Engelsystem\Migrations;

use Engelsystem\Database\Migration\Migration;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder as SchemaBuilder;

class CreateGoodiesDistributions extends Migration
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
        $this->schema->create('goodiesv2_distributions', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->integer('item_id')->unsigned();
            $table->integer('quantity')->default(1);
            $table->integer('distributed_by')->unsigned();
            $table->timestamp('distributed_at')->useCurrent();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onDelete('cascade');

            $table->foreign('item_id')
                ->references('id')->on('goodiesv2_items')
                ->onDelete('cascade');

            $table->foreign('distributed_by')
                ->references('id')->on('users')
                ->onDelete('cascade');

            $table->index(['user_id', 'item_id']);
            $table->index(['distributed_at']);
            $table->index(['item_id']);
            $table->index(['distributed_by']);
        });
    }

    /**
     * Reverse the migration
     */
    public function down(): void
    {
        $this->schema->dropIfExists('goodiesv2_distributions');
    }
}
