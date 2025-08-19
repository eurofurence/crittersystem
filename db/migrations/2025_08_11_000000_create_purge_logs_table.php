<?php

declare(strict_types=1);

namespace Engelsystem\Migrations;

use Engelsystem\Database\Migration\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Builder as SchemaBuilder;

/**
 * Migration to create purge_logs table for tracking purge operations
 */
class CreatePurgeLogsTable extends Migration
{
    protected Connection $db;

    public function __construct(SchemaBuilder $schema)
    {
        parent::__construct($schema);
        $this->db = $this->schema->getConnection();
    }

    public function up(): void
    {
        $this->schema->create('purge_logs', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->json('categories')->comment('Array of categories purged (users, shifts, news, logs)');
            $table->date('cutoff_date')->comment('Date cutoff used for purge operation');
            $table->json('affected_counts')->comment('Number of records affected per category');
            $table->enum('status', ['initiated', 'in_progress', 'completed', 'failed', 'cancelled'])
                  ->default('initiated')
                  ->comment('Current status of the purge operation');
            $table->text('error_message')->nullable()->comment('Error details if purge failed');
            $table->string('backup_file_path')->nullable()->comment('Path to backup file created');
            $table->timestamp('created_at')->useCurrent()->comment('When purge was initiated');
            $table->timestamp('completed_at')->nullable()->comment('When purge was completed');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            // Indexes for better query performance
            $table->index('user_id');
            $table->index('status');
            $table->index('created_at');
            $table->index(['status', 'created_at'], 'status_created_idx');

            // Foreign key constraint
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->schema->dropIfExists('purge_logs');
    }
}
