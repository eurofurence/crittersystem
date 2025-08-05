<?php

declare(strict_types=1);

namespace Engelsystem\Migrations;

use Engelsystem\Database\Migration\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Builder as SchemaBuilder;
use Illuminate\Support\Str;

class UpdateGroupsAndDepartments extends Migration
{
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
        // ********************************************************************
        // Table Groups (Security group)
        // ********************************************************************
        // Add slug
        $this->schema->table('groups', function (Blueprint $table): void {
            $table->string('slug')->nullable()->after('name');
            $table->index('slug');
        });

        // Get all groups that don't have slugs
        $groups = $this->db->table('groups')->whereNull('slug')->get();

        foreach ($groups as $group) {
            $baseSlug = Str::slug($group->name);
            $slug = $baseSlug;
            $counter = 1;

            // Check for conflicts and add counter if needed
            while ($this->db->table('groups')->where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }

            // Update the group with the generated slug
            $this->db->table('groups')
                ->where('id', $group->id)
                ->update(['slug' => $slug]);
        }

        // Make groups slug unique and non-nullable
        $this->schema->table('groups', function (Blueprint $table): void {
            $table->string('slug')->nullable(false)->change();
            $table->unique('slug');
        });

        // ********************************************************************
        // Table Departments
        // ********************************************************************
        // Add slug
        $this->schema->table('departments', function (Blueprint $table): void {
            $table->string('slug')->nullable()->after('name');
            $table->index('slug');
        });

        // Get all departments that don't have slugs
        $departments = $this->db->table('departments')->whereNull('slug')->get();

        foreach ($departments as $department) {
            $baseSlug = Str::slug($department->name);
            $slug = $baseSlug;
            $counter = 1;

            // Check for conflicts and add counter if needed
            while ($this->db->table('departments')->where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }

            // Update the department with the generated slug
            $this->db->table('departments')
                ->where('id', $department->id)
                ->update(['slug' => $slug]);
        }

        // Make departments slug unique and non-nullable
        $this->schema->table('departments', function (Blueprint $table): void {
            $table->string('slug')->nullable(false)->change();
            $table->unique('slug');
        });
    }

    /**
     * Reverse the migration
     */
    public function down(): void
    {
        // ********************************************************************
        // DROP slug column from Departments
        // ********************************************************************
        $this->schema->table('departments', function (Blueprint $table): void {
            $table->dropIndex(['slug']);
            $table->dropColumn('slug');
        });

        // ********************************************************************
        // DROP slug column from Groups (Security group)
        // ********************************************************************
        $this->schema->table('groups', function (Blueprint $table): void {
            $table->dropIndex(['slug']);
            $table->dropColumn('slug');
        });
    }
}
