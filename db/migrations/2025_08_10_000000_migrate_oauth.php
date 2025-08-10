<?php

declare(strict_types=1);

namespace Engelsystem\Migrations;

use Engelsystem\Database\Migration\Migration;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Builder as SchemaBuilder;

class MigrateOauth extends Migration
{
    protected Connection $db;

    protected string $new_oath_provider = 'ef';
    protected string $old_oath_provider = 'Identity';

    public function __construct(SchemaBuilder $schema)
    {
        parent::__construct($schema);
        $this->db = $this->schema->getConnection();
    }

    public function up(): void
    {
        $this->updateOauthProvider(oldName: $this->old_oath_provider, newName: $this->new_oath_provider);
    }

    public function down(): void
    {
        $this->updateOauthProvider(oldName: $this->new_oath_provider, newName: $this->old_oath_provider);
    }

    // #################################################################
    // Support Functions
    // #################################################################
    protected function updateOauthProvider(string $oldName, string $newName): void
    {
        $this->db->table('oauth')
            ->where('provider', $oldName)
            ->update([
                'provider' => $newName,
            ]);
    }
}
