<?php

declare(strict_types=1);

namespace Engelsystem\Migrations;

use Engelsystem\Database\Migration\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Builder as SchemaBuilder;

class AddCertification extends Migration {

    protected Connection $db;

    public function __construct(SchemaBuilder $schema)
    {
        parent::__construct($schema);
        $this->db = $this->schema->getConnection();
    }

    public function up(): void
    {
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
        });

        $this->schema->create('certifications_user', function (Blueprint $table): void {
            $table->id();
//            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('user_id');
            $table->foreign('user_id')->references(columns: 'id')->on(table: 'users')->onDelete('cascade');
            $table->foreignId('certification_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['pending', 'approved', 'revoked', 'expired']);
            $table->timestamp('date_completed')->nullable();
            $table->timestamp('date_expires')->nullable();
            $table->boolean('self_confirmed')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'certification_id']);
        });

        $this->schema->create('certifications_shift_type', function (Blueprint $table): void {
            $table->id();
//            $table->foreignId('shift_type_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('shift_type_id');
            $table->foreign('shift_type_id')->references(columns: 'id')->on(table: 'shift_types')->onDelete('cascade');
            $table->foreignId('certification_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['shift_type_id', 'certification_id']);
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('certifications_shift_type');
        $this->schema->dropIfExists('certifications_user');
        $this->schema->dropIfExists('certifications');
    }
};
