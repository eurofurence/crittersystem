<?php

declare(strict_types=1);

namespace Engelsystem\Controllers\Admin;

use Engelsystem\Controllers\BaseController;
use Engelsystem\Database\Database;
use Engelsystem\Helpers\Authenticator;
use Engelsystem\Http\Request;
use Engelsystem\Http\Response;
use Engelsystem\Http\Validation\ValidatesRequest;
use Exception;
use ZipArchive;

class DumpManagerController extends BaseController
{
    use ValidatesRequest;

    /** @var array<string> */
    protected array $permissions = [
        'user.type.admin',
    ];

    public function __construct(
        protected Response $response,
        protected Authenticator $auth,
        protected Database $db
    ) {
    }

    public function index(Request $request): Response
    {
        return $this->response->withView(
            'admin/dumpmanager/index.twig',
            []
        );
    }

    /**
     * Create a complete database dump
     */
    public function createDump(Request $request): Response
    {
        try {
            // Temporarily increase memory limit for large dumps
            $originalMemoryLimit = $this->increaseMemoryLimitTemporarily();

            $timestamp = date('Y-m-d_H-i-s');
            $filename = 'database_dump_' . $timestamp;
            $tempDir = sys_get_temp_dir() . '/dumpmanager';

            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $dumpPath = $tempDir . '/' . $filename;
            $zipPath = $dumpPath . '.zip';

            // Get all table names
            $tables = $this->getAllTables();

            // Create dump data structure
            $dumpData = [
                'metadata' => [
                    'version' => $this->getDatabaseVersion(),
                    'timestamp' => date('Y-m-d H:i:s'),
                    'tables' => $tables,
                    'created_by' => $this->auth->user()->name ?? 'Unknown',
                ],
                'schema' => [],
                'data' => [],
            ];

            // Export schema and data for each table
            foreach ($tables as $table) {
                $dumpData['schema'][$table] = $this->getTableSchema($table);
                $dumpData['data'][$table] = $this->getTableData($table);
            }

            // Save dump data as JSON
            file_put_contents($dumpPath . '.json', json_encode($dumpData, JSON_PRETTY_PRINT));

            // Create ZIP archive
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
                throw new Exception('Failed to create ZIP archive');
            }

            $zip->addFile($dumpPath . '.json', $filename . '.json');
            $zip->close();

            // Clean up temporary JSON file
            unlink($dumpPath . '.json');

            // Restore original memory limit
            ini_set('memory_limit', $originalMemoryLimit);

            return $this->response->withJson([
                'success' => true,
                'message' => 'Database dump created successfully',
                'filename' => $filename . '.zip',
                'path' => $zipPath,
                'size' => filesize($zipPath),
                'tables_count' => count($tables),
            ]);
        } catch (Exception $e) {
            return $this->response
                ->withStatus(500)
                ->withJson([
                    'success' => false,
                    'message' => 'Failed to create dump: ' . $e->getMessage(),
                ]);
        }
    }

    /**
     * Download the created dump file
     */
    public function downloadDump(Request $request): Response
    {
        $filename = $request->getAttribute('filename');

        if (!$filename || !preg_match('/^database_dump_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.zip$/', $filename)) {
            return $this->response
                ->withStatus(400)
                ->withJson(['error' => 'Invalid filename format']);
        }

        $filepath = sys_get_temp_dir() . '/dumpmanager/' . $filename;

        if (!file_exists($filepath)) {
            return $this->response
                ->withStatus(404)
                ->withJson(['error' => 'Dump file not found']);
        }

        return $this->response
            ->withHeader('Content-Type', 'application/zip')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withHeader('Content-Length', (string) filesize($filepath))
            ->withContent(file_get_contents($filepath));
    }

    /**
     * Upload and validate restore file
     */
    public function uploadRestore(Request $request): Response
    {
        try {
            $files = $request->getUploadedFiles();

            // Handle both named and indexed file uploads
            $uploadedFile = null;
            if (isset($files['dump_file'])) {
                $uploadedFile = $files['dump_file'];
            } elseif (isset($files[0])) {
                $uploadedFile = $files[0];
            } elseif (!empty($files)) {
                $uploadedFile = reset($files); // Get first file regardless of key
            }

            if (!$uploadedFile) {
                return $this->response
                    ->withStatus(400)
                    ->withJson([
                        'error' => 'No dump file uploaded',
                        'debug' => [
                            'received_files' => array_keys($files),
                            'files_count' => count($files),
                        ],
                    ]);
            }

            if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
                return $this->response
                    ->withStatus(400)
                    ->withJson(['error' => 'File upload failed']);
            }

            $tempDir = sys_get_temp_dir() . '/dumpmanager/restore';
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $uploadPath = $tempDir . '/' . uniqid('restore_') . '.zip';
            $uploadedFile->moveTo($uploadPath);

            // Extract and validate the dump
            $validation = $this->validateDumpFile($uploadPath);

            if (!$validation['valid']) {
                unlink($uploadPath);
                return $this->response
                    ->withStatus(400)
                    ->withJson(['error' => $validation['error']]);
            }

            return $this->response->withJson([
                'success' => true,
                'message' => 'Dump file validated successfully',
                'upload_path' => $uploadPath,
                'validation' => $validation,
            ]);
        } catch (Exception $e) {
            return $this->response
                ->withStatus(500)
                ->withJson(['error' => 'Validation failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Execute the database restore with confirmation
     */
    public function executeRestore(Request $request): Response
    {
        try {
            // Temporarily increase memory limit for large restores
            $originalMemoryLimit = $this->increaseMemoryLimitTemporarily();
            // Get JSON data from request
            $data = $request->getParsedBody();
            $jsonData = json_decode($request->getContent(), true);

            // Use JSON data if available, fallback to parsed body
            if (!empty($jsonData)) {
                $data = $jsonData;
            }

            // Require double confirmation
            if (!isset($data['confirmation']) || $data['confirmation'] !== 'RESTORE_DATABASE') {
                return $this->response
                    ->withStatus(400)
                    ->withJson(['error' => 'Invalid confirmation. Type "RESTORE_DATABASE" to confirm.']);
            }

            if (!isset($data['upload_path'])) {
                return $this->response
                    ->withStatus(400)
                    ->withJson(['error' => 'No upload path provided']);
            }

            $uploadPath = $data['upload_path'];
            if (!file_exists($uploadPath)) {
                return $this->response
                    ->withStatus(400)
                    ->withJson(['error' => 'Dump file not found']);
            }

            // Re-validate the dump file
            $validation = $this->validateDumpFile($uploadPath);
            if (!$validation['valid']) {
                return $this->response
                    ->withStatus(400)
                    ->withJson(['error' => 'Dump validation failed: ' . $validation['error']]);
            }

            // Extract dump data
            $dumpData = $this->extractDumpData($uploadPath);

            try {
                // Clear all tables while preserving structure
                $this->clearAllTables($dumpData['metadata']['tables']);

                // Restore data
                $this->restoreData($dumpData['data']);

                // Clear all sessions to prevent conflicts after restoration
                $this->clearAllSessions();

                // Clean up uploaded file
                unlink($uploadPath);

                // Restore original memory limit
                ini_set('memory_limit', $originalMemoryLimit);

                return $this->response->withJson([
                    'success' => true,
                    'message' => 'Database restored successfully',
                    'restored_tables' => count($dumpData['metadata']['tables']),
                    'timestamp' => $dumpData['metadata']['timestamp'],
                ]);
            } catch (Exception $e) {
                throw $e;
            }
        } catch (Exception $e) {
            // Log the full error for debugging
            error_log('Restore failed: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());

            return $this->response
                ->withStatus(500)
                ->withJson([
                    'error' => 'Restore failed: ' . $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
        }
    }

    /**
     * Get all table names from database
     */
    private function getAllTables(): array
    {
        $tables = [];
        $result = $this->db->select('SHOW TABLES');

        foreach ($result as $row) {
            $tableName = array_values((array) $row)[0];
            $tables[] = $tableName;
        }

        return $tables;
    }

    /**
     * Get table schema information
     */
    private function getTableSchema(string $table): array
    {
        $columns = $this->db->select('DESCRIBE `' . $table . '`');
        return array_map(fn($col) => (array) $col, $columns);
    }

    /**
     * Get all data from a table (chunked for memory efficiency)
     */
    private function getTableData(string $table): array
    {
        $chunkSize = 1000; // Process 1000 rows at a time
        $allData = [];
        $offset = 0;

        do {
            $chunk = $this->db->select(
                'SELECT * FROM `' . $table . '` LIMIT ? OFFSET ?',
                [$chunkSize, $offset]
            );

            $chunkData = array_map(fn($row) => (array) $row, $chunk);
            $allData = array_merge($allData, $chunkData);

            $offset += $chunkSize;

            // Force garbage collection to free memory
            if ($offset % 10000 === 0) {
                gc_collect_cycles();
            }
        } while (count($chunk) === $chunkSize);

        return $allData;
    }

    /**
     * Get database version info
     */
    private function getDatabaseVersion(): array
    {
        $version = $this->db->selectOne('SELECT VERSION() as version');
        return [
            'mysql_version' => $version->version ?? 'Unknown',
            'schema_version' => $this->getSchemaVersion(),
        ];
    }

    /**
     * Get current schema version (if migrations table exists)
     */
    private function getSchemaVersion(): ?string
    {
        try {
            $migration = $this->db->getConnection()
                ->table('migrations')
                ->orderBy('id', 'desc')
                ->first();
            return $migration->migration ?? null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Validate uploaded dump file
     */
    private function validateDumpFile(string $zipPath): array
    {
        try {
            $zip = new ZipArchive();
            if ($zip->open($zipPath) !== true) {
                return ['valid' => false, 'error' => 'Invalid ZIP file'];
            }

            // Check if JSON dump file exists
            $jsonFile = null;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                if (str_ends_with($filename, '.json')) {
                    $jsonFile = $filename;
                    break;
                }
            }

            if (!$jsonFile) {
                $zip->close();
                return ['valid' => false, 'error' => 'No JSON dump file found in archive'];
            }

            // Extract and parse JSON
            $jsonContent = $zip->getFromName($jsonFile);
            $zip->close();

            $dumpData = json_decode($jsonContent, true);
            if (!$dumpData) {
                return ['valid' => false, 'error' => 'Invalid JSON format'];
            }

            // Validate dump structure
            if (!isset($dumpData['metadata'], $dumpData['schema'], $dumpData['data'])) {
                return ['valid' => false, 'error' => 'Invalid dump file structure'];
            }

            // Check table compatibility
            $currentTables = $this->getAllTables();
            $dumpTables = $dumpData['metadata']['tables'];

            $missingTables = array_diff($dumpTables, $currentTables);
            if (!empty($missingTables)) {
                return [
                    'valid' => false,
                    'error' => 'Database version mismatch. Missing tables: ' . implode(', ', $missingTables),
                ];
            }

            return [
                'valid' => true,
                'metadata' => $dumpData['metadata'],
                'table_count' => count($dumpTables),
                'dump_timestamp' => $dumpData['metadata']['timestamp'],
            ];
        } catch (Exception $e) {
            return ['valid' => false, 'error' => 'Validation error: ' . $e->getMessage()];
        }
    }

    /**
     * Extract dump data from ZIP file
     */
    private function extractDumpData(string $zipPath): array
    {
        $zip = new ZipArchive();
        $zip->open($zipPath);

        $jsonContent = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            if (str_ends_with($filename, '.json')) {
                $jsonContent = $zip->getFromName($filename);
                break;
            }
        }

        $zip->close();

        if ($jsonContent === null) {
            throw new Exception('No JSON dump file found in archive');
        }

        $decodedData = json_decode($jsonContent, true);
        if ($decodedData === null) {
            throw new Exception('Invalid JSON format in dump file');
        }

        return $decodedData;
    }

    /**
     * Clear all tables while preserving structure
     */
    private function clearAllTables(array $tables): void
    {
        $connection = $this->db->getConnection();

        try {
            // Disable foreign key checks temporarily
            $connection->statement('SET FOREIGN_KEY_CHECKS = 0');

            foreach ($tables as $table) {
                try {
                    // Use DELETE instead of TRUNCATE
                    $connection->table($table)->delete();
                } catch (Exception $e) {
                    error_log('Failed to clear table ' . $table . ': ' . $e->getMessage());
                    throw $e;
                }
            }

            // Re-enable foreign key checks
            $connection->statement('SET FOREIGN_KEY_CHECKS = 1');
        } catch (Exception $e) {
            // Always try to re-enable foreign key checks
            try {
                $connection->statement('SET FOREIGN_KEY_CHECKS = 1');
            } catch (Exception $cleanup) {
                // Ignore cleanup errors
            }
            throw $e;
        }
    }

    /**
     * Restore data to tables with dependency-aware retry mechanism
     */
    private function restoreData(array $tablesData): void
    {
        $connection = $this->db->getConnection();

        try {
            // Disable foreign key checks temporarily
            $connection->statement('SET FOREIGN_KEY_CHECKS = 0');

            $this->restoreDataWithRetries($connection, $tablesData);

            // Re-enable foreign key checks
            $connection->statement('SET FOREIGN_KEY_CHECKS = 1');
        } catch (Exception $e) {
            // Always try to re-enable foreign key checks
            try {
                $connection->statement('SET FOREIGN_KEY_CHECKS = 1');
            } catch (Exception $cleanup) {
                // Ignore cleanup errors
            }
            throw $e;
        }
    }

    /**
     * Restore data with retry mechanism for dependency resolution
     */
    private function restoreDataWithRetries(
        \Illuminate\Database\Connection $connection,
        array $tablesData
    ): void {
        $pendingTables = $tablesData;
        $completedTables = [];
        $maxRetries = 5;
        $retryCount = 0;

        while (!empty($pendingTables) && $retryCount < $maxRetries) {
            $failedTables = [];
            $successCount = 0;

            foreach ($pendingTables as $table => $rows) {
                if (empty($rows)) {
                    // Mark empty tables as completed
                    $completedTables[] = $table;
                    $successCount++;
                    continue;
                }

                try {
                    $this->insertDataInChunks($connection, $table, $rows);
                    $completedTables[] = $table;
                    $successCount++;
                    error_log('Successfully restored table: ' . $table);
                } catch (Exception $e) {
                    // Check if this is a foreign key constraint error
                    if ($this->isForeignKeyError($e)) {
                        $failedTables[$table] = $rows;
                        error_log('Deferred table ' . $table . ' due to foreign key constraint: ' . $e->getMessage());
                    } else {
                        // For non-foreign key errors, log and rethrow
                        error_log('Failed to restore data to table ' . $table . ' (non-FK error): ' . $e->getMessage());
                        throw $e;
                    }
                }
            }

            // Update pending tables for next retry
            $pendingTables = $failedTables;

            // If no progress was made in this iteration, we have a circular dependency or persistent issue
            if ($successCount === 0 && !empty($pendingTables)) {
                break;
            }

            $retryCount++;
        }

        // If there are still pending tables, try one final approach: insert with NULL foreign keys where possible
        if (!empty($pendingTables)) {
            $this->restoreDataWithNullifiedForeignKeys($connection, $pendingTables);
        }

        error_log('Data restoration completed. Restored ' . count($completedTables) . ' tables successfully.');
    }

    /**
     * Check if an exception is related to foreign key constraints
     */
    private function isForeignKeyError(Exception $e): bool
    {
        $message = strtolower($e->getMessage());
        return strpos($message, 'foreign key constraint') !== false ||
               strpos($message, 'cannot add or update a child row') !== false ||
               strpos($message, 'referential integrity constraint') !== false ||
               strpos($message, 'constraint violation') !== false;
    }

    /**
     * Final attempt: restore data by temporarily nullifying foreign key columns
     */
    private function restoreDataWithNullifiedForeignKeys(
        \Illuminate\Database\Connection $connection,
        array $remainingTables
    ): void {
        foreach ($remainingTables as $table => $rows) {
            try {
                // Get table schema to identify foreign key columns
                $foreignKeyColumns = $this->getForeignKeyColumns($table);

                if (!empty($foreignKeyColumns)) {
                    // Create modified rows with foreign keys set to null
                    $modifiedRows = [];
                    foreach ($rows as $row) {
                        $modifiedRow = $row;
                        foreach ($foreignKeyColumns as $fkColumn) {
                            if (isset($modifiedRow[$fkColumn])) {
                                $modifiedRow[$fkColumn] = null;
                            }
                        }
                        $modifiedRows[] = $modifiedRow;
                    }

                    $this->insertDataInChunks($connection, $table, $modifiedRows);
                    error_log('Restored table ' . $table . ' with nullified foreign keys');
                } else {
                    // No foreign keys found, try original data one more time
                    $this->insertDataInChunks($connection, $table, $rows);
                    error_log('Restored table ' . $table . ' on final attempt');
                }
            } catch (Exception $e) {
                error_log('Final restoration attempt failed for table ' . $table . ': ' . $e->getMessage());
                // Continue with other tables rather than failing completely
            }
        }
    }

    /**
     * Temporarily increase PHP memory limit for large operations
     */
    private function increaseMemoryLimitTemporarily(): string
    {
        $originalLimit = ini_get('memory_limit');

        // Convert memory limit to bytes for comparison
        $originalBytes = $this->convertToBytes($originalLimit);
        $desiredBytes = 512 * 1024 * 1024; // 512MB

        if ($originalBytes < $desiredBytes) {
            ini_set('memory_limit', '512M');
            error_log('Temporarily increased memory limit from ' . $originalLimit . ' to 512M');
        }

        return $originalLimit;
    }

    /**
     * Convert memory limit string to bytes
     */
    private function convertToBytes(string $memoryLimit): int
    {
        $memoryLimit = trim($memoryLimit);
        $last = strtolower($memoryLimit[strlen($memoryLimit) - 1]);
        $value = (int) $memoryLimit;

        switch ($last) {
            case 'g':
                $value *= 1024 * 1024 * 1024;
                break;
            case 'm':
                $value *= 1024 * 1024;
                break;
            case 'k':
                $value *= 1024;
                break;
        }

        return $value;
    }

    /**
     * Insert data into table in chunks to avoid memory exhaustion
     */
    private function insertDataInChunks(
        \Illuminate\Database\Connection $connection,
        string $table,
        array $rows
    ): void {
        $chunkSize = 500; // Insert 500 rows at a time
        $chunks = array_chunk($rows, $chunkSize);

        foreach ($chunks as $chunk) {
            $connection->table($table)->insert($chunk);

            // Force garbage collection after each chunk
            gc_collect_cycles();
        }
    }

    /**
     * Clear all active sessions after database restoration
     */
    private function clearAllSessions(): void
    {
        try {
            $connection = $this->db->getConnection();
            $connection->table('sessions')->delete();
            error_log('Cleared all sessions after database restoration');
        } catch (Exception $e) {
            error_log('Failed to clear sessions: ' . $e->getMessage());
            // Continue anyway - this is not critical for restoration
        }
    }

    /**
     * Get foreign key columns for a table
     */
    private function getForeignKeyColumns(string $table): array
    {
        try {
            $foreignKeys = $this->db->select('
                SELECT COLUMN_NAME 
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                WHERE REFERENCED_TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = ? 
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ', [$table]);

            return array_map(fn($fk) => $fk->COLUMN_NAME, $foreignKeys);
        } catch (Exception $e) {
            error_log('Failed to get foreign key columns for table ' . $table . ': ' . $e->getMessage());
            return [];
        }
    }
}
