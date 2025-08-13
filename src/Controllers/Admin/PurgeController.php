<?php

declare(strict_types=1);

namespace Engelsystem\Controllers\Admin;

use Carbon\Carbon;
use Engelsystem\Controllers\BaseController;
use Engelsystem\Helpers\Authenticator;
use Engelsystem\Helpers\PurgeService;
use Engelsystem\Http\Request;
use Engelsystem\Http\Response;
use Engelsystem\Http\Validation\PurgeRequestValidator;
use Engelsystem\Http\Validation\ValidatesRequest;
use Engelsystem\Models\PurgeLog;

class PurgeController extends BaseController
{
    use ValidatesRequest;

    /** @var array<string> */
    protected array $permissions = [
        'user.type.admin',
//        'admin_purge',
    ];

    public function __construct(
        protected Response $response,
        protected Authenticator $auth,
        protected PurgeService $purgeService
    ) {
    }

    public function index(Request $request): Response
    {
        return $this->response->withView(
            'admin/purge/index.twig',
            []
        );
    }

    public function preview(Request $request): Response
    {
        try {
            // Validate input data
            $data = $this->validate($request, PurgeRequestValidator::rules());
            $data = PurgeRequestValidator::validateAndSanitize($data);

            // Get safe parameters
            $categories = PurgeRequestValidator::getSafeCategories($data);
            $cutoffDate = PurgeRequestValidator::getSafeCutoffDate($data);

            if (empty($categories) || !$cutoffDate) {
                return $this->response
                    ->withStatus(400)
                    ->withJson([
                        'success' => false,
                        'message' => 'Invalid categories or date provided.',
                    ]);
            }

            // Get preview data
            $preview = $this->purgeService->previewPurge($categories, $cutoffDate);

            // Calculate totals
            $totalItems = 0;
            $totalDependencies = 0;
            foreach ($preview as $category => $data) { // phpcs:ignore
                $totalItems += $data['count'];
                if (isset($data['dependencies'])) {
                    $totalDependencies += array_sum($data['dependencies']);
                }
            }

            return $this->response->withJson([
                'success' => true,
                'preview' => $preview,
                'summary' => [
                    'total_items' => $totalItems,
                    'total_dependencies' => $totalDependencies,
                    'cutoff_date' => $cutoffDate->format('Y-m-d'),
                    'categories' => $categories,
                ],
            ]);
        } catch (\Engelsystem\Http\Exceptions\ValidationException $e) {
            return $this->response
                ->withStatus(422)
                ->withJson([
                    'success' => false,
                    'message' => 'Validation failed for the provided input.',
                    'errors' => $e->getValidator()->getErrors(),
                    'friendly_messages' => PurgeRequestValidator::messages(),
                ]);
        } catch (\Exception $e) {
            // Temporary enhanced error details to diagnose preview issues
            return $this->response
                ->withStatus(500)
                ->withJson([
                    'success' => false,
                    'message' => 'An error occurred while generating preview: ' . ($e->getMessage() ?: '(no message)'),
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
        }
    }

    public function execute(Request $request): Response
    {
        try {
            // Validate input data
            $data = $this->validate($request, array_merge(
                PurgeRequestValidator::rules(),
                ['confirmation' => 'required|in:DELETE']
            ));

            $data = PurgeRequestValidator::validateAndSanitize($data);

            // Additional safety checks
            if (!PurgeRequestValidator::isValidForExecution($data)) {
                return $this->response
                    ->withStatus(400)
                    ->withJson([
                        'success' => false,
                        'message' => 'Invalid request or missing confirmation.',
                    ]);
            }

            // Get safe parameters
            $categories = PurgeRequestValidator::getSafeCategories($data);
            $cutoffDate = PurgeRequestValidator::getSafeCutoffDate($data);

            if (empty($categories) || !$cutoffDate) {
                return $this->response
                    ->withStatus(400)
                    ->withJson([
                        'success' => false,
                        'message' => 'Invalid categories or date provided.',
                    ]);
            }

            // Rate limiting check (simple implementation)
            $lastPurge = session()->get('last_purge_attempt', 0);
            $cooldownPeriod = 300; // 5 minutes

            if (time() - $lastPurge < $cooldownPeriod) {
                return $this->response
                    ->withStatus(429)
                    ->withJson([
                        'success' => false,
                        'message' => 'Please wait before attempting another purge operation.',
                    ]);
            }

            // Update last purge attempt
            session()->set('last_purge_attempt', time());

            // Create audit log entry
            $userId = $this->auth->user()->id;
            $auditLog = $this->purgeService->createAuditLog($userId, $categories, $cutoffDate);

            // Step 1: Create backup
            $this->notifyProgress('Starting backup creation...', 10);
            $backupInfo = $this->purgeService->createBackup($categories, $cutoffDate);

            if (!$backupInfo['success']) {
                return $this->response
                    ->withStatus(500)
                    ->withJson([
                        'success' => false,
                        'message' => 'Backup creation failed: ' . ($backupInfo['error'] ?? 'Unknown error'),
                    ]);
            }

            // Step 2: Verify backup
            $this->notifyProgress('Verifying backup...', 20);
            $verification = $this->purgeService->verifyBackup($backupInfo);

            if (!$verification['success']) {
                return $this->response
                    ->withStatus(500)
                    ->withJson([
                        'success' => false,
                        'message' => 'Backup verification failed: ' . implode(', ', $verification['errors']),
                    ]);
            }

            // Step 3: Execute purge operations
            $purgeResults = [];
            $progressStep = 30;
            $stepIncrement = 60 / count($categories); // Remaining 60% divided by categories

            foreach ($categories as $category) {
                $this->notifyProgress('Purging ' . $category . '...', $progressStep);

                switch ($category) {
                    case 'users':
                        $purgeResults[$category] = $this->purgeService->purgeUsers($cutoffDate);
                        break;
                    case 'shifts':
                        $purgeResults[$category] = $this->purgeService->purgeShifts($cutoffDate);
                        break;
                    case 'news':
                        $purgeResults[$category] = $this->purgeService->purgeNews($cutoffDate);
                        break;
                    case 'logs':
                        $purgeResults[$category] = $this->purgeService->purgeLogs($cutoffDate);
                        break;
                }

                $progressStep += $stepIncrement;
            }

            $this->notifyProgress('Purge completed successfully!', 100);

            // Calculate total affected counts and prepare audit log data
            $affectedCounts = [];
            $totalDeleted = 0;
            $totalDependencies = 0;
            foreach ($purgeResults as $category => $result) {
                $totalDeleted += $result['deleted'];
                $affectedCounts[$category] = $result['deleted'];
                if (isset($result['dependencies'])) {
                    $totalDependencies += array_sum($result['dependencies']);
                }
            }
            $this->purgeService->updateAuditLogCounts($auditLog, $affectedCounts);
            $this->purgeService->completeAuditLog($auditLog, $backupInfo['backup_path'] ?? null);

            return $this->response->withJson([
                'success' => true,
                'message' => 'Purge operation completed successfully.',
                'log_id' => $auditLog->id,
                'status_url' => url('/admin/purge/status/' . $auditLog->id),
                'results' => $purgeResults,
                'summary' => [
                    'total_deleted' => $totalDeleted,
                    'total_dependencies' => $totalDependencies,
                    'categories_processed' => $categories,
                    'backup_info' => $backupInfo,
                ],
            ]);
        } catch (\Exception $e) {
            // Mark audit log as failed
            if (isset($auditLog)) {
                $this->purgeService->failAuditLog($auditLog, $e->getMessage());
            }

            return $this->response
                ->withStatus(500)
                ->withJson([
                    'success' => false,
                    'message' => 'An error occurred during purge execution: ' . $e->getMessage(),
                ]);
        }
    }

    /**
     * Helper method to notify progress (can be extended for real-time updates)
     */
    public function notifyProgress(string $message, int $percentage): void
    {
        // For now, just log the progress
        // This can be extended to use WebSockets or Server-Sent Events
        error_log('Purge Progress: ' . $percentage . '% - $message');
    }

    /**
     * Display audit logs
     */
    public function auditLogs(Request $request): Response
    {
        $status = $request->get('status');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        $query = PurgeLog::with('user')->orderBy('created_at', 'desc');

        // Apply filters
        if ($status) {
            $query->where('status', $status);
        }

        if ($dateFrom) {
            $query->where('created_at', '>=', Carbon::parse($dateFrom)->startOfDay());
        }

        if ($dateTo) {
            $query->where('created_at', '<=', Carbon::parse($dateTo)->endOfDay());
        }

        $logs = $query->limit(100)->get();
        $stats = $this->purgeService->getAuditStats();

        // Check if there are any active purges for auto-refresh
        $hasActivePurges = PurgeLog::whereIn('status', [
            PurgeLog::STATUS_INITIATED,
            PurgeLog::STATUS_IN_PROGRESS,
        ])->exists();

        return $this->response->withView(
            'admin/purge/audit-logs.twig',
            [
                'logs' => $logs,
                'stats' => $stats,
                'status' => $status,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'has_active_purges' => $hasActivePurges,
            ]
        );
    }

    /**
     * Download backup file for a specific purge log
     */
    public function downloadBackup(Request $request): Response
    {
        $logId = (int) $request->getAttribute('id');
        $log = PurgeLog::findOrFail($logId);

        if (!$log->backup_file_path || !file_exists($log->backup_file_path)) {
            return $this->response
                ->withStatus(404)
                ->withJson([
                    'error' => 'Backup file not found or no longer exists.',
                ]);
        }

        $filename = 'purge-backup-' . $log->id . '-' . $log->created_at->format('Y-m-d') . '.tar.gz';

        return $this->response
            ->withHeader('Content-Type', 'application/octet-stream')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withHeader('Content-Length', (string) filesize($log->backup_file_path))
            ->withContent(file_get_contents($log->backup_file_path));
    }

    /**
     * Get current status of a purge operation (AJAX endpoint)
     */
    public function getStatus(Request $request): Response
    {
        $logId = (int) $request->getAttribute('id');
        $log = PurgeLog::findOrFail($logId);

        return $this->response->withJson([
            'id' => $log->id,
            'status' => $log->status,
            'progress' => $log->isActive() ? 'in_progress' : 'completed',
            'affected_counts' => $log->affected_counts,
            'total_affected' => $log->getTotalAffectedCount(),
            'duration' => $log->getDuration(),
            'error_message' => $log->error_message,
        ]);
    }
}
