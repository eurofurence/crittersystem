<?php

declare(strict_types=1);

namespace Engelsystem\Controllers;

use Engelsystem\Http\Response;
use Throwable;
use Engelsystem\Database\Db;

class MigrationController extends BaseController
{
    public function __construct(protected Response $response)
    {
    }

    /**
     * Checks migration state marker files and returns the appropriate response
     */
    public function index(): Response
    {
        $baseDir = __DIR__ . '/../..';
        $fileOk = $baseDir . '/storage/migration.ok';
        $fileFail = $baseDir . '/storage/migration.fail';
        $fileRunning = $baseDir . '/storage/migration.running';

        if (!file_exists($fileOk)) {
            // Missing OK file, evaluate other markers
            if (!file_exists($fileRunning) && !file_exists($fileFail)) {
                // Soft-check: try to verify DB connectivity and migration counts silently
                try {
                    // Count migration files in db/migrations, excluding specific files
                    $migrationsDir = $baseDir . '/db/migrations';
                    $files = glob($migrationsDir . '/*.php') ?: [];
                    $ignore = ['ChangesReferences.php', 'Reference.php'];
                    $fileCount = 0;
                    foreach ($files as $file) {
                        $name = basename($file);
                        if (in_array($name, $ignore, true)) {
                            continue;
                        }
                        // Count only files that look like proper migrations (e.g., 2025_07_30_000001_*.php)
                        if (preg_match('/^\d+_.*\.php$/', $name) === 1) {
                            $fileCount++;
                        }
                    }

                    // Try DB connection and count migrated entries
                    $dbCount = 0;
                    try {
                        $db = Db::connection();
                        // Ensure the connection is alive (will throw if not)
                        $db->getPdo();
                        $dbCount = (int) $db->table('migrations')->count();
                    } catch (Throwable $e) {
                        // Swallow silently: proceed to show migration_missing page below
                        $dbCount = -1; // mark as invalid to avoid creating ok file
                    }

                    if ($fileCount > 0 && $dbCount >= 0 && $fileCount === $dbCount) {
                        // Create migration.ok and redirect to main site
                        @fopen($fileOk, 'w');
                        return $this->response->redirectTo('/');
                    }
                } catch (Throwable $e) {
                    // Swallow any error and proceed as normal
                }

                // Nothing indicates a migration state -> treat as setup required
                return $this->staticPage('migration_missing.html', 503);
            }

            if (file_exists($fileRunning) && file_exists($fileFail)) {
                // Both present -> migration failed mid-run
                return $this->staticPage('migration_fail.html', 500);
            }

            if (file_exists($fileRunning)) {
                // Currently running
                return $this->staticPage('migration_run.html', 200);
            }

            if (file_exists($fileFail)) {
                // Failed
                return $this->staticPage('migration_fail.html', 503);
            }
        }

        // OK: return a no-op 204, middleware will continue the pipeline
        return $this->response->withStatus(204);
    }

    protected function staticPage(string $file, int $status): Response
    {
        $path = __DIR__ . '/../../resources/views/static/' . $file;
        if (!file_exists($path)) {
            // Fallback safety
            return $this->response->withStatus($status)->withContent('');
        }

        $content = file_get_contents($path);
        return $this->response->withStatus($status)->withContent($content);
    }
}
