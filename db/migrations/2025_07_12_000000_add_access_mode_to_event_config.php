<?php

declare(strict_types=1);

namespace Engelsystem\Migrations;

use Engelsystem\Database\Migration\Migration;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Builder as SchemaBuilder;

class AddAccessModeToEventConfig extends Migration {

    protected Connection $db;

    public function __construct(SchemaBuilder $schema)
    {
        parent::__construct($schema);
        $this->db = $this->schema->getConnection();
    }

    public function up(): void
    {
        // Add default access mode configuration
        $this->db->table('event_config')->insert([
            'name' => 'access_mode',
            'value' => json_encode('public'),
            'created_at' => date("Y-m-d H:i:s"),
            'updated_at' => date("Y-m-d H:i:s"),
        ]);
    }

    public function down(): void
    {
        $this->db->table('event_config')
            ->where('name', 'access_mode')
            ->delete();
    }
};
