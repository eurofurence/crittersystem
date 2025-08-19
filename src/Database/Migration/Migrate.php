<?php

declare(strict_types=1);

namespace Engelsystem\Database\Migration;

use Engelsystem\Application;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder as SchemaBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

class Migrate
{
    /** @var callable */
    protected $output;

    protected string $table = 'migrations';

    protected string $fileMigrationFlagOk = 'migration.ok';
    protected string $fileMigrationFlagFail = 'migration.fail';
    protected string $fileMigrationFlagRunning = 'migration.running';
    protected string $fileMigrationFlagBasePath = '';

    private function removeFile(string $file): void
    {
        if ($this->fileMigrationFlagBasePath !== '') {
            if (file_exists(filename: $this->fileMigrationFlagBasePath . $file)) {
                unlink(filename: $this->fileMigrationFlagBasePath . $file);
            }
        }
    }

    private function createFile(string $file): void
    {
        if ($this->fileMigrationFlagBasePath !== '') {
            if (!file_exists(filename:$this->fileMigrationFlagBasePath . $file)) {
                fopen(filename:$this->fileMigrationFlagBasePath . $file, mode: 'w');
            }
        }
    }

    private function removeAllMigrationFlagFiles(): void
    {
        if ($this->fileMigrationFlagBasePath !== '') {
            $this->removeFile(file: $this->fileMigrationFlagOk);
            $this->removeFile(file: $this->fileMigrationFlagFail);
            $this->removeFile(file: $this->fileMigrationFlagRunning);
        }
    }

    /**
     * Migrate constructor
     */
    public function __construct(protected SchemaBuilder $schema, protected Application $app)
    {
        $this->output = function (): void {
        };
    }

    /**
     * Run a migration
     * @throws Throwable
     */
    public function run(
        string $path,
        Direction $direction = Direction::UP,
        bool $oneStep = false,
        bool $forceMigration = false,
        string $migrationFlagPath = ''
    ): void {

        // Set the migration flag path
        $this->fileMigrationFlagBasePath = $migrationFlagPath;
        // Clean the migration flags
        $this->removeAllMigrationFlagFiles();

        $this->initMigration();

        $this->lockTable($forceMigration);
        $migrations = $this->mergeMigrations(
            $this->getMigrations($path),
            $this->getMigrated()
        );

        if ($direction === Direction::DOWN) {
            $migrations = $migrations->reverse();
        }

        // Create running migration flag
        $this->createFile(file: $this->fileMigrationFlagRunning);

        try {
            foreach ($migrations as $migration) {
                /** @var array $migration */
                $name = $migration['migration'];

                if (
                    ($direction === Direction::UP && isset($migration['id']))
                    || ($direction === Direction::DOWN && !isset($migration['id']))
                ) {
                    ($this->output)('Skipping ' . $name);
                    continue;
                }

                ($this->output)('Migrating ' . $name . ' (' . $direction->value . ')');

                if (isset($migration['path'])) {
                    $this->migrate($migration['path'], $name, $direction);
                }
                $this->setMigrated($name, $direction);

                if ($oneStep) {
                    break;
                }
            }
        } catch (Throwable $e) {
            $this->unlockTable();

            // Clean the migration flags
            $this->removeAllMigrationFlagFiles();
            // Create fail flag
            $this->createFile(file: $this->fileMigrationFlagFail);

            printf(PHP_EOL);
            printf(str_repeat('*', 100) . PHP_EOL);
            printf('!! ERROR !!' . PHP_EOL);
            printf(str_repeat('*', 100) . PHP_EOL . PHP_EOL);
            // dump($e);
            print_r($e);
            printf(PHP_EOL . str_repeat('*', 100) . PHP_EOL . PHP_EOL);

            if (PHP_SAPI === 'cli') {
                throw new Exception(message:'Migration failed', code: $e->getCode(), previous: $e);
            } else {
                die('Migration fail');
            }
        }

        $this->unlockTable();

        // Clean the migration flags
        $this->removeAllMigrationFlagFiles();
        // Create fail flag
        $this->createFile(file: $this->fileMigrationFlagOk);

        // Keep 'admin' group's privileges in sync with all privileges
        try {
            // Only proceed if required tables exist
            if (
                $this->schema->hasTable('groups') &&
                $this->schema->hasTable('privileges') &&
                $this->schema->hasTable('group_privileges')
            ) {
                $db = $this->schema->getConnection();

                // Detect admin group (prefer slug if available, fallback to ID=1)
                $adminId = null;
                if ($this->schema->hasColumn('groups', 'slug')) {
                    $admin = $db->table('groups')->where('slug', 'admin')->first();
                    if ($admin) {
                        $adminId = (int) ($admin->id ?? 0);
                    }
                }
                if (!$adminId) {
                    $admin = $db->table('groups')->where('id', 1)->first();
                    if ($admin) {
                        $adminId = (int) ($admin->id ?? 0);
                    }
                }

                // If admin group does not exist (e.g., downgrade), skip
                if ($adminId) {
                    // Get all privilege IDs
                    $allPrivilegeIds = $db->table('privileges')->pluck('id')->all();

                    if (!empty($allPrivilegeIds)) {
                        // Get existing privilege IDs for admin
                        $existing = $db->table('group_privileges')
                            ->where('group_id', $adminId)
                            ->pluck('privilege_id')
                            ->all();

                        // Compute missing privilege IDs
                        $missing = array_values(
                            array_diff(
                                array_map(
                                    'intval',
                                    $allPrivilegeIds
                                ),
                                array_map('intval', $existing)
                            )
                        );

                        if (!empty($missing)) {
                            $rows = [];
                            foreach ($missing as $pid) {
                                $rows[] = ['group_id' => $adminId, 'privilege_id' => (int) $pid];
                            }
                            // Insert missing rows in one go
                            $db->table('group_privileges')->insert($rows);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // Skip silently; this logic must never fail the migration run
        }

        // Inform the user
        printf(PHP_EOL . str_repeat('*', 100) . PHP_EOL);
        printf('Migration finished' . PHP_EOL);
        printf(str_repeat('*', 100) . PHP_EOL . PHP_EOL);
    }

    /**
     * Setup migration tables
     */
    public function initMigration(): void
    {
        if ($this->schema->hasTable($this->table)) {
            return;
        }

        $this->schema->create($this->table, function (Blueprint $table): void {
            $table->increments('id');
            $table->string('migration');
        });
    }

    /**
     * Merge file migrations with already migrated tables
     */
    protected function mergeMigrations(Collection $migrations, Collection $migrated): Collection
    {
        $return = $migrated;
        $return->transform(function ($migration) use ($migrations) {
            $migration = (array) $migration;
            if ($migrations->contains('migration', $migration['migration'])) {
                $migration += $migrations
                    ->where('migration', $migration['migration'])
                    ->first();
            }

            return $migration;
        });

        $migrations->each(function ($migration) use ($return): void {
            if ($return->contains('migration', $migration['migration'])) {
                return;
            }

            $return->add($migration);
        });

        return $return;
    }

    /**
     * Get all migrated migrations
     */
    protected function getMigrated(): Collection
    {
        return $this->getTableQuery()
            ->orderBy('id')
            ->where('migration', '!=', 'lock')
            ->get();
    }

    /**
     * Migrate a migration
     */
    protected function migrate(string $file, string $migration, Direction $direction = Direction::UP): void
    {
        require_once $file;

        $className = Str::studly(preg_replace('/\d+_/', '', $migration));
        /** @var Migration $class */
        $class = $this->app->make('Engelsystem\\Migrations\\' . $className);

        if (method_exists($class, $direction->value)) {
            $class->{$direction->value}();
        }
    }

    /**
     * Set a migration to migrated
     */
    protected function setMigrated(string $migration, Direction $direction = Direction::UP): void
    {
        $table = $this->getTableQuery();

        if ($direction === Direction::DOWN) {
            $table->where(['migration' => $migration])->delete();
            return;
        }

        $table->insert(['migration' => $migration]);
    }

    /**
     * Lock the migrations table
     *
     *
     * @throws Throwable
     */
    protected function lockTable(bool $forceMigration = false): void
    {
        $this->schema->getConnection()->transaction(function () use ($forceMigration): void {
            $lock = $this->getTableQuery()
                ->where('migration', 'lock')
                ->lockForUpdate()
                ->first();

            if ($lock && !$forceMigration) {
                // Clean the migration flags
                $this->removeAllMigrationFlagFiles();
                // Create fail flag
                $this->createFile(file: $this->fileMigrationFlagFail);

                printf(PHP_EOL);
                printf(str_repeat('*', 100) . PHP_EOL);
                printf('!! ERROR !!' . PHP_EOL);
                printf(str_repeat('*', 100) . PHP_EOL . PHP_EOL);
                printf('Table LOCK detected - You can force the lock bypass with --force' . PHP_EOL);
                printf(PHP_EOL . str_repeat('*', 100) . PHP_EOL . PHP_EOL);

                if (PHP_SAPI === 'cli') {
                    throw new Exception(message:'Unable to acquire migration table lock', code: 0, previous: null);
                } else {
                    die('Unable to acquire migration table lock');
                }
            }

            $this->getTableQuery()
                 ->insert(['migration' => 'lock']);
        });
    }

    /**
     * Unlock a previously locked table
     */
    protected function unlockTable(): void
    {
        $this->getTableQuery()
            ->where('migration', 'lock')
            ->delete();
    }

    /**
     * Get a list of migration files
     */
    protected function getMigrations(string $dir): Collection
    {
        $files = $this->getMigrationFiles($dir);

        $migrations = new Collection();
        foreach ($files as $dir) {
            $name = str_replace('.php', '', basename($dir));
            $migrations[] = [
                'migration' => $name,
                'path'      => $dir,
            ];
        }

        return $migrations->sortBy(function ($value) {
            return $value['migration'];
        });
    }

    /**
     * List all migration files from the given directory
     */
    protected function getMigrationFiles(string $dir): array
    {
        return glob($dir . '/*_*.php');
    }

    /**
     * Init a table query
     */
    protected function getTableQuery(): Builder
    {
        return $this->schema->getConnection()->table($this->table);
    }

    /**
     * Set the output function
     */
    public function setOutput(callable $output): void
    {
        $this->output = $output;
    }
}
