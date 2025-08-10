<?php

declare(strict_types=1);

namespace Engelsystem\Controllers;

use Engelsystem\Http\Response;

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
