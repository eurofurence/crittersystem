<?php

declare(strict_types=1);

namespace Engelsystem\Migrations;

use Engelsystem\Database\Migration\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Builder as SchemaBuilder;

class FixCertificationTables extends Migration
{
    protected Connection $db;

    public function __construct(SchemaBuilder $schema)
    {
        parent::__construct($schema);
        $this->db = $this->schema->getConnection();
    }

    public function up(): void
    {
        // First Drop tables before re-creating them
        $this->schema->dropIfExists('certifications_shift_type');
        $this->schema->dropIfExists('certifications_user');
        $this->schema->dropIfExists('certifications');

        // Recreate tables
        $this->schema->create('certifications', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique()->index();
            $table->string('title');
            $table->text('description');
            $table->string('contact_person')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('location')->nullable();
            $table->boolean('is_perpetual')->default(false);
            $table->integer('validity_period_days')->nullable();
            $table->boolean('allow_self_confirmation')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Performance indexes
            $table->index('is_active');
            $table->index('is_perpetual');
            $table->index('allow_self_confirmation');
        });

        $this->schema->create('certifications_user', function (Blueprint $table): void {
            $table->id();
//            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('user_id');
            $table->foreign('user_id')->references(columns: 'id')->on(table: 'users')->onDelete('cascade');
            $table->foreignId('certification_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['pending', 'approved', 'self_confirmed', 'revoked', 'expired']);
            $table->timestamp('date_certified')->nullable();
            $table->timestamp('date_expires')->nullable();
            $table->unsignedInteger('certified_by')->nullable();
            $table->foreign('certified_by')->references('id')->on('users')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'certification_id']);

            // Performance indexes
            $table->index('status');
            $table->index('date_expires');
            $table->index(['user_id', 'status']);
        });

        $this->schema->create('certifications_angel_type', function (Blueprint $table): void {
            $table->id();
//            $table->foreignId('angel_type_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('angel_type_id');
            $table->foreign('angel_type_id')->references(columns: 'id')->on(table: 'angel_types')->onDelete('cascade');
            $table->foreignId('certification_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['angel_type_id', 'certification_id']);
        });
    }

    public function down(): void
    {
        // Drop tables in reverse order to handle foreign key constraints
        $this->schema->dropIfExists('certifications_angel_type');
        $this->schema->dropIfExists('certifications_user');
        $this->schema->dropIfExists('certifications');
    }
}
