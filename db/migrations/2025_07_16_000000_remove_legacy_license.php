<?php

declare(strict_types=1);

namespace Engelsystem\Migrations;

use Engelsystem\Database\Migration\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Builder as SchemaBuilder;

class RemoveLegacyLicense extends Migration {

    protected Connection $db;

    public function __construct(SchemaBuilder $schema)
    {
        parent::__construct($schema);
        $this->db = $this->schema->getConnection();
    }

    public function up(): void
    {
        $this->schema->dropIfExists('user_licenses');
        $this->schema->table('angel_types', function (Blueprint $table): void {
            $table->dropColumn('requires_driver_license');
            $table->dropColumn('requires_ifsg_certificate');
        });
    }

    public function down(): void
    {
        $this->schema->create('user_licenses', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('user_id')->unsigned();
            $table->boolean('has_car')->default(false);
            $table->boolean('car')->default(false);
            $table->boolean('forklift')->default(false);
            $table->boolean('ifsg')->default(false);
            $table->boolean('ifsg_light')->default(false);
            $table->boolean('three_and_half_t')->default(false);
            $table->boolean('seven_and_half_t')->default(false);
            $table->boolean('twelve_t')->default(false);
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });

        $this->schema->table('angel_types', function (Blueprint $table): void {
            $table->boolean('requires_driver_license')->default(false);
            $table->boolean('requires_ifsg_certificate')->default(false);
        });
    }
};
