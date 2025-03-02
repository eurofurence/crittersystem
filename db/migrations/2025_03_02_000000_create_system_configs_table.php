<?php

declare(strict_types=1);

namespace Engelsystem\Migrations;

use Engelsystem\Database\Migration\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateSystemConfigsTable extends Migration
{
    use ChangesReferences;
    use Reference;

    /**
     * Create system configs table
     */
    public function up(): void
    {
        $connection = $this->schema->getConnection();

        $this->schema->create('system_configs', function (Blueprint $table): void {
            $table->increments('id');
            $table->dateTimeTz('created_at')->useCurrent();
            $this->references($table, 'users', 'created_by');
            $table->json('json')->default('{}');
        });
    }

    /**
     * Drop system_configs table
     */
    public function down(): void
    {
        $this->schema->drop('system_configs');
    }
}
