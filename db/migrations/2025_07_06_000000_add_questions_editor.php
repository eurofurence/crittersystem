<?php

declare(strict_types=1);

namespace Engelsystem\Migrations;

use Engelsystem\Database\Migration\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddQuestionsEditor extends Migration
{
    use Reference;

    public function up(): void
    {
        $this->schema->table('questions', function (Blueprint $table): void {
            $table->dateTime('editing_started_at')->default(null)->nullable();
            $this->references($table, 'users', 'editor_id')->nullable();
        });
    }

    public function down(): void
    {
        $this->schema->table('questions', function (Blueprint $table): void {
            $table->dropForeign('questions_editor_id_foreign');
            $table->dropColumn('editing_started_at');
            $table->dropColumn('editor_id');
        });
    }
}
