<?php

declare(strict_types=1);

namespace Engelsystem\Migrations;

use Engelsystem\Database\Migration\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Builder as SchemaBuilder;

class AddDepartmentSupport extends Migration {

    protected Connection $db;

    public function __construct(SchemaBuilder $schema)
    {
        parent::__construct($schema);
        $this->db = $this->schema->getConnection();
    }

    public function up(): void
    {
        $this->schema->create('departments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('staff_only')->default(false);
            $table->timestamps();

            $table->index('uuid');
        });

        $this->schema->create('department_responsibles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('department_id')->constrained(table: 'departments')->onDelete('cascade');
//            $table->foreignId('user_id')->constrained(table: 'users')->onDelete('cascade');
            $table->unsignedInteger('user_id');
            $table->foreign('user_id')->references(columns: 'id')->on(table: 'users')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['department_id', 'user_id']);
            $table->index('uuid');
        });

        $this->schema->create('department_users', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('department_id')->constrained()->onDelete('cascade');
//            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('user_id');
            $table->foreign('user_id')->references(columns: 'id')->on(table: 'users')->onDelete('cascade');
            $table->enum('status', ['pending', 'approved', 'denied'])->default('pending');
            $table->timestamps();

            $table->unique(['department_id', 'user_id']);
            $table->index('uuid');
        });

        $this->schema->create('department_applications_log', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('department_id')->constrained();
//            $table->foreignId('user_id')->constrained();
            $table->unsignedInteger('user_id')->nullable();
            $table->foreign('user_id')->references(columns: 'id')->on(table: 'users')->onDelete('set null');
//            $table->foreignId('processed_by')->nullable()->constrained('users');
            $table->unsignedInteger('processed_by')->nullable();
            $table->foreign('processed_by')->references(columns: 'id')->on(table: 'users')->onDelete('set null');
            $table->enum('status', ['pending', 'approved', 'denied']);
            $table->timestamps();

            $table->index('uuid');
        });

        $this->schema->create('department_locations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('department_id')->constrained()->onDelete('cascade');
//            $table->foreignId('location_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('location_id');
            $table->foreign('location_id')->references(columns: 'id')->on(table: 'locations')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['department_id', 'location_id']);
            $table->index('uuid');
        });

        $this->schema->create('department_shifts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('department_id')->constrained()->onDelete('cascade');
//            $table->foreignId('shift_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('shift_id');
            $table->foreign('shift_id')->references(columns: 'id')->on(table: 'shifts')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['department_id', 'shift_id']);
            $table->index('uuid');
        });

        $this->schema->create('department_angel_types', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('department_id')->constrained()->onDelete('cascade');
//            $table->foreignId('angel_type_id')->constrained('angel_types')->onDelete('cascade');
            $table->unsignedInteger('angel_type_id');
            $table->foreign('angel_type_id')->references(columns: 'id')->on(table: 'angel_types')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['department_id', 'angel_type_id']);
            $table->index('uuid');
        });
    }

    public function down(): void
    {

        $this->schema->dropIfExists('department_angel_types');
        $this->schema->dropIfExists('department_shifts');
        $this->schema->dropIfExists('department_locations');
        $this->schema->dropIfExists('department_applications_log');
        $this->schema->dropIfExists('department_users');
        $this->schema->dropIfExists('department_responsibles');
        $this->schema->dropIfExists('departments');

    }
};
