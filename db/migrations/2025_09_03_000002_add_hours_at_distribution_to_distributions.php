<?php

declare(strict_types=1);

namespace Engelsystem\Migrations;

use Engelsystem\Database\Migration\Migration;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder as SchemaBuilder;

class AddHoursAtDistributionToDistributions extends Migration
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
        $this->schema->table('goodiesv2_distributions', function (Blueprint $table): void {
            // Add hours_at_distribution field to track user hours at time of distribution
            $table->integer('hours_at_distribution')->default(0)->after('quantity');

            // Add index for reporting purposes
            $table->index(['hours_at_distribution']);
        });
    }

    /**
     * Reverse the migration
     */
    public function down(): void
    {
        $this->schema->table('goodiesv2_distributions', function (Blueprint $table): void {
            $table->dropIndex(['hours_at_distribution']);
            $table->dropColumn('hours_at_distribution');
        });
    }
}
