<?php

declare(strict_types=1);

namespace Engelsystem\Migrations;

use Carbon\Carbon;
use Engelsystem\Database\Migration\Migration;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder as SchemaBuilder;

class CreateDigitalIdSystem extends Migration
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
        $this->schema->create('digital_id_tokens', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->string('token', 255)->unique();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onDelete('cascade');

            $table->index(['token']);
            $table->index(['expires_at']);
            $table->index(['user_id']);
        });

        $this->schema->create('digital_id_config', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('key', 255)->unique();
            $table->text('value');
            $table->timestamps();

            $table->index(['key']);
        });

        // Insert default configuration values
        $this->schema->getConnection()->table('digital_id_config')->insert([
            [
                'key' => 'digital_id_enabled',
                'value' => 'true',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'key' => 'digital_id_refresh_interval',
                'value' => '120',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'key' => 'digital_id_token_overlap',
                'value' => '30',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ]);
    }

    /**
     * Reverse the migration
     */
    public function down(): void
    {
        $this->schema->dropIfExists('digital_id_tokens');
        $this->schema->dropIfExists('digital_id_config');
    }
}
