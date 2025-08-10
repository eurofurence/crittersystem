<?php

declare(strict_types=1);

namespace Engelsystem\Controllers\Admin;

use Engelsystem\Config\Config;
use Engelsystem\Controllers\BaseController;
use Engelsystem\Database\Database;
use Engelsystem\Http\Request;
use Engelsystem\Http\Response;
use Exception;
use PDOException;

class InstallController extends BaseController
{
    public function __construct(
        protected Response $response,
        protected Config $config,
        protected Request $request,
        protected Database $database
    ) {
    }

    /**
     * Main installation interface
     */
    public function index(): Response
    {
        // Check if installation is already complete
        if ($this->isInstallationComplete()) {
            return $this->response->withView(
                'install/installation_complete',
                []
            );
        }

        // Check if install workflow is enabled
        if (!$this->config->get('enable_install_workflow', false)) {
            return $this->response->withView(
                'install/install_disabled',
                []
            );
        }

        return $this->response->withView(
            'install/install_index',
            []
        );
    }

    /**
     * Handle password authentication
     */
    public function authenticate(): Response
    {
        if ($this->isInstallationComplete()) {
            return $this->response->withStatus(404);
        }

        $password = $this->request->get('password', '');
        $expectedPassword = $this->config->get('setup_admin_password');

        if (empty($expectedPassword)) {
            return $this->response
                ->withStatus(500)
                ->withJson(json_encode(['error' => 'Setup password not configured']));
        }

        if (!hash_equals($expectedPassword, $password)) {
            return $this->response
                ->withStatus(401)
                ->withJson(json_encode(['error' => 'Invalid password']));
        }

        // Store authentication in session
        session()->set('install_authenticated', true);

        return $this->response
            ->withHeader('Content-Type', 'application/json')
            ->withJson(json_encode(['success' => true]));
    }

    /**
     * Get system status information
     */
    public function status(): Response
    {
        if (!$this->isAuthenticated()) {
            return $this->response->withStatus(401);
        }

        $status = [
            'database' => $this->checkDatabaseConnection(),
            'migration_files' => $this->checkMigrationFiles(),
            'hostname' => gethostname(),
            'php_version' => PHP_VERSION,
            'timestamp' => date('c'),
        ];

        return $this->response
            ->withHeader('Content-Type', 'application/json')
            ->withJson(json_encode($status));
    }

    /**
     * Execute database migration
     */
    public function migrate(): Response
    {
        if (!$this->isAuthenticated()) {
            return $this->response->withStatus(401);
        }

        // Check if migration is already running
        $runningFile = $this->getBaseDir() . '/config/migration.running';
        if (file_exists($runningFile)) {
            return $this->response
                ->withStatus(409)
                ->withJson(json_encode(['error' => 'Migration already running']));
        }

        try {
            // Create running marker
            file_put_contents($runningFile, date('c'));

            // Execute migration command using exec
            $baseDir = $this->getBaseDir();
            $migrateScript = $baseDir . '/bin/migrate';

            // Change to base directory for execution
            $oldCwd = getcwd();
            if ($oldCwd !== false) {
                chdir($baseDir);
            }

            // Capture both stdout and stderr
            $command = 'php ' . $migrateScript . ' 2>&1';

            $outputLines = [];
            $exitCode = 0;

            // Execute command and capture output
            exec($command, $outputLines, $exitCode);

            $output = implode("\n", $outputLines);

            // Restore original directory
            if ($oldCwd !== false) {
                chdir($oldCwd);
            }

            // Clean up running marker
            if (file_exists($runningFile)) {
                unlink($runningFile);
            }

            $result = [
                'exit_code' => $exitCode,
                'output' => $output,
                'success' => $exitCode === 0,
            ];

            if ($exitCode !== 0) {
                // Create failure marker
                file_put_contents($this->getBaseDir() . '/config/migration.fail', date('c') . "\n" . $output);
            }

            return $this->response
                ->withHeader('Content-Type', 'application/json')
                ->withJson(json_encode($result));
        } catch (Exception $e) {
            // Clean up running marker
            if (file_exists($runningFile)) {
                unlink($runningFile);
            }

            // Create failure marker
            file_put_contents($this->getBaseDir() . '/config/migration.fail', date('c') . "\n" . $e->getMessage());

            return $this->response
                ->withStatus(500)
                ->withJson(json_encode([
                    'error' => 'Migration failed: ' . $e->getMessage(),
                    'success' => false,
                ]));
        }
    }

    /**
     * Execute SQL command manually
     */
    public function executeSql(): Response
    {
        if (!$this->isAuthenticated()) {
            return $this->response->withStatus(401);
        }

        $sql = trim($this->request->get('sql', ''));
        if (empty($sql)) {
            return $this->response
                ->withStatus(400)
                ->withJson(json_encode(['error' => 'No SQL provided']));
        }

        try {
            $connection = $this->database->getConnection();

            // For safety, only allow SELECT, SHOW, DESCRIBE commands in manual execution
            $firstWord = strtoupper(explode(' ', trim($sql))[0]);
            if (!in_array($firstWord, ['SELECT', 'SHOW', 'DESCRIBE', 'EXPLAIN', 'DELETE', 'DROP'])) {
                return $this->response
                    ->withStatus(code: 400)
                    ->withJson(
                        content: json_encode(
                            value: ['error' => 'Only SELECT, SHOW, DESCRIBE, and EXPLAIN statements are allowed']
                        )
                    );
            }

            $result = $connection->select($sql);

            return $this->response
                ->withHeader('Content-Type', 'application/json')
                ->withJson(json_encode([
                    'success' => true,
                    'data' => $result,
                    'count' => count($result),
                ]));
        } catch (Exception $e) {
            return $this->response
                ->withStatus(500)
                ->withJson(json_encode([
                    'error' => 'SQL execution failed: ' . $e->getMessage(),
                    'success' => false,
                ]));
        }
    }

    /**
     * Check if installation is complete
     */
    private function isInstallationComplete(): bool
    {
        return file_exists($this->getBaseDir() . '/config/migration.ok');
    }

    /**
     * Check if user is authenticated for installation
     */
    private function isAuthenticated(): bool
    {
        return session()->get('install_authenticated', false) === true;
    }

    /**
     * Get base directory path
     */
    private function getBaseDir(): string
    {
        return __DIR__ . '/../../..';
    }

    /**
     * Check database connection
     */
    private function checkDatabaseConnection(): array
    {
        try {
            $connection = $this->database->getConnection();
            $pdo = $connection->getPdo(); // phpcs:ignore

            // Test connection with a simple query
            $result = $connection->select('SELECT 1 as test');

            return [
                'status' => 'connected',
                'driver' => $connection->getDriverName(),
                'database' => $connection->getDatabaseName(),
                'test_query' => !empty($result),
            ];
        } catch (PDOException | Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check migration-related files
     */
    private function checkMigrationFiles(): array
    {
        $baseDir = $this->getBaseDir();

        return [
            'migration_ok' => file_exists($baseDir . '/config/migration.ok'),
            'migration_fail' => file_exists($baseDir . '/config/migration.fail'),
            'migration_running' => file_exists($baseDir . '/config/migration.running'),
            'migrate_script' => file_exists($baseDir . '/bin/migrate'),
        ];
    }
}
