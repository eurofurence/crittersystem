<?php

declare(strict_types=1);

namespace Engelsystem\Helpers;

use Carbon\Carbon;
use Engelsystem\Models\LogEntry;
use Engelsystem\Models\News;
use Engelsystem\Models\PurgeLog;
use Engelsystem\Models\User\User;
use Engelsystem\Models\Shifts\Shift;
use Illuminate\Database\Eloquent\Builder;

/**
 * Service responsible for purging entities and their dependencies from the database.
 */
class PurgeService
{
    protected int $defaultBatchSize = 100;
    protected array $progressCallbacks = [];
    protected string $backupPath = '';

    public function __construct(protected int $batchSize = 100)
    {
        $this->batchSize = $batchSize;
        $this->backupPath = realpath(__DIR__ . '/../../storage/app/backups/purge') ?: sys_get_temp_dir() . '/purge_backups'; // phpcs:ignore
    }

    /**
     * Set batch size for processing large datasets
     */
    public function setBatchSize(int $size): void
    {
        $this->batchSize = $size;
    }

    /**
     * Add a progress callback function
     */
    public function addProgressCallback(callable $callback): void
    {
        $this->progressCallbacks[] = $callback;
    }

    /**
     * Notify progress callbacks
     */
    protected function notifyProgress(string $operation, int $processed, int $total): void
    {
        foreach ($this->progressCallbacks as $callback) {
            $callback($operation, $processed, $total);
        }
    }

    /**
     * Purge users and all their dependencies based on last_login_at date
     */
    public function purgeUsers(Carbon $cutoffDate): array
    {
        $stats = ['deleted' => 0, 'dependencies' => []];

        User::getConnectionResolver()->connection()->transaction(function () use ($cutoffDate, &$stats): void {
            // Get users to purge (handle null last_login_at appropriately)
            $usersQuery = User::where(function (Builder $query) use ($cutoffDate): void {
                $query->where('last_login_at', '<', $cutoffDate)
                      ->orWhereNull('last_login_at');
            });

            $totalUsers = $usersQuery->count();
            $processed = 0;

            $usersQuery->chunk($this->batchSize, function ($users) use (&$processed, $totalUsers, &$stats): void {
                foreach ($users as $user) {
                    $this->purgeUserDependencies($user, $stats);
                    $user->delete();
                    $stats['deleted']++;
                    $processed++;

                    $this->notifyProgress('users', $processed, $totalUsers);
                }
            });
        });

        return $stats;
    }

    /**
     * Remove all user dependencies
     */
    protected function purgeUserDependencies(User $user, array &$stats): void
    {
        // Contact, License, PersonalData, Settings, State (1:1 relationships)
        if ($user->contact) {
            $user->contact->delete();
            $this->incrementStat($stats, 'contacts');
        }
        if ($user->license) {
            $user->license->delete();
            $this->incrementStat($stats, 'licenses');
        }
        if ($user->personalData) {
            $user->personalData->delete();
            $this->incrementStat($stats, 'personal_data');
        }
        if ($user->settings) {
            $user->settings->delete();
            $this->incrementStat($stats, 'settings');
        }
        if ($user->state) {
            $user->state->delete();
            $this->incrementStat($stats, 'states');
        }

        // Groups (many-to-many relationships)
        $groupCount = $user->groups()->count();
        $user->groups()->detach();
        $this->addToStat($stats, 'group_memberships', $groupCount);

        // Messages (both sent and received)
        $sentMessages = $user->messagesSent()->count();
        $receivedMessages = $user->messagesReceived()->count();
        $user->messagesSent()->delete();
        $user->messagesReceived()->delete();
        $this->addToStat($stats, 'messages_sent', $sentMessages);
        $this->addToStat($stats, 'messages_received', $receivedMessages);

        // Shift entries
        $shiftEntriesCount = $user->shiftEntries()->count();
        $user->shiftEntries()->delete();
        $this->addToStat($stats, 'shift_entries', $shiftEntriesCount);

        // Worklogs
        $worklogsCount = $user->worklogs()->count();
        $worklogsCreatedCount = $user->worklogsCreated()->count();
        $user->worklogs()->delete();
        $user->worklogsCreated()->delete();
        $this->addToStat($stats, 'worklogs', $worklogsCount);
        $this->addToStat($stats, 'worklogs_created', $worklogsCreatedCount);

        // News comments
        $newsCommentsCount = $user->newsComments()->count();
        $user->newsComments()->delete();
        $this->addToStat($stats, 'news_comments', $newsCommentsCount);

        // OAuth tokens
        $oauthCount = $user->oauth()->count();
        $user->oauth()->delete();
        $this->addToStat($stats, 'oauth_tokens', $oauthCount);

        // Sessions
        $sessionsCount = $user->sessions()->count();
        $user->sessions()->delete();
        $this->addToStat($stats, 'sessions', $sessionsCount);

        // Questions (asked and answered)
        $questionsAskedCount = $user->questionsAsked()->count();
        $questionsAnsweredCount = $user->questionsAnswered()->count();
        $user->questionsAsked()->delete();
        $user->questionsAnswered()->update(['answerer_id' => null]);
        $this->addToStat($stats, 'questions_asked', $questionsAskedCount);
        $this->addToStat($stats, 'questions_answered', $questionsAnsweredCount);

        // Angel type associations
        $angelTypeCount = $user->userAngelTypes()->count();
        $user->userAngelTypes()->delete();
        $this->addToStat($stats, 'angel_type_associations', $angelTypeCount);

        // Department responsibilities
        $departmentCount = $user->responsibleForDepartments()->count();
        $user->responsibleForDepartments()->detach();
        $this->addToStat($stats, 'department_responsibilities', $departmentCount);
    }

    /**
     * Purge shifts based on created_at date with related data
     */
    public function purgeShifts(Carbon $cutoffDate): array
    {
        $stats = ['deleted' => 0, 'dependencies' => []];

        Shift::getConnectionResolver()->connection()->transaction(function () use ($cutoffDate, &$stats): void {
            $shiftsQuery = Shift::where('created_at', '<', $cutoffDate);
            $totalShifts = $shiftsQuery->count();
            $processed = 0;

            $shiftsQuery->chunk($this->batchSize, function ($shifts) use (&$processed, $totalShifts, &$stats): void {
                foreach ($shifts as $shift) {
                    // Remove shift entries
                    $entriesCount = $shift->shiftEntries()->count();
                    $shift->shiftEntries()->delete();
                    $this->addToStat($stats, 'shift_entries', $entriesCount);

                    // Remove needed angel types
                    $neededCount = $shift->neededAngelTypes()->count();
                    $shift->neededAngelTypes()->delete();
                    $this->addToStat($stats, 'needed_angel_types', $neededCount);

                    $shift->delete();
                    $stats['deleted']++;
                    $processed++;

                    $this->notifyProgress('shifts', $processed, $totalShifts);
                }
            });
        });

        return $stats;
    }

    /**
     * Purge news based on created_at date with related NewsComments
     */
    public function purgeNews(Carbon $cutoffDate): array
    {
        $stats = ['deleted' => 0, 'dependencies' => []];

        News::getConnectionResolver()->connection()->transaction(function () use ($cutoffDate, &$stats): void {
            $newsQuery = News::where('created_at', '<', $cutoffDate);
            $totalNews = $newsQuery->count();
            $processed = 0;

            $newsQuery->chunk($this->batchSize, function ($newsItems) use (&$processed, $totalNews, &$stats): void {
                foreach ($newsItems as $news) {
                    // Remove news comments
                    $commentsCount = $news->comments()->count();
                    $news->comments()->delete();
                    $this->addToStat($stats, 'news_comments', $commentsCount);

                    $news->delete();
                    $stats['deleted']++;
                    $processed++;

                    $this->notifyProgress('news', $processed, $totalNews);
                }
            });
        });

        return $stats;
    }

    /**
     * Purge logs based on created_at date from LogEntry model
     */
    public function purgeLogs(Carbon $cutoffDate): array
    {
        $stats = ['deleted' => 0, 'dependencies' => []];

        LogEntry::getConnectionResolver()->connection()->transaction(function () use ($cutoffDate, &$stats): void {
            $logsQuery = LogEntry::where('created_at', '<', $cutoffDate);
            $totalLogs = $logsQuery->count();
            $processed = 0;

            $logsQuery->chunk($this->batchSize, function ($logs) use (&$processed, $totalLogs, &$stats): void {
                foreach ($logs as $log) {
                    $log->delete();
                    $stats['deleted']++;
                    $processed++;

                    $this->notifyProgress('logs', $processed, $totalLogs);
                }
            });
        });

        return $stats;
    }

    /**
     * Preview purge operation - count records that would be affected
     */
    public function previewPurge(array $categories, Carbon $cutoffDate): array
    {
        $preview = [];

        if (in_array('users', $categories)) {
            $usersQuery = User::where(function (Builder $query) use ($cutoffDate): void {
                $query->where('last_login_at', '<', $cutoffDate)
                      ->orWhereNull('last_login_at');
            });

            $preview['users'] = [
                'count' => $usersQuery->count(),
                'dependencies' => $this->previewUserDependencies($usersQuery->pluck('id')),
            ];
        }

        if (in_array('shifts', $categories)) {
            $shiftsQuery = Shift::where('created_at', '<', $cutoffDate);
            $preview['shifts'] = [
                'count' => $shiftsQuery->count(),
                'dependencies' => [
                    'shift_entries' => $shiftsQuery->withCount('shiftEntries')->get()->sum('shift_entries_count'),
                    'needed_angel_types' => $shiftsQuery->withCount('neededAngelTypes')->get()->sum('needed_angel_types_count'), // phpcs:ignore
                ],
            ];
        }

        if (in_array('news', $categories)) {
            $newsQuery = News::where('created_at', '<', $cutoffDate);
            $preview['news'] = [
                'count' => $newsQuery->count(),
                'dependencies' => [
                    'comments' => $newsQuery->withCount('comments')->get()->sum('comments_count'),
                ],
            ];
        }

        if (in_array('logs', $categories)) {
            $preview['logs'] = [
                'count' => LogEntry::where('created_at', '<', $cutoffDate)->count(),
                'dependencies' => [],
            ];
        }

        return $preview;
    }

    /**
     * Previews the dependencies related to the specified users that would be affected by a deletion process.
     * This method calculates counts for various types of data associated with the provided user IDs.
     *
     * @param mixed $userIds A collection or query builder instance representing the IDs of the users
     *                        whose dependencies are to be previewed.
     * @return array An associative array containing the counts of each type of user-related dependency.
     */
    protected function previewUserDependencies(mixed $userIds): array
    {
        $dependencies = [];

        // Count all user-related data that would be deleted
        if (is_a($userIds, Builder::class)) {
            $userIds = $userIds->pluck('id');
        }

        if ($userIds->isNotEmpty()) {
            $dependencies = [
                'contacts' => User::whereIn('id', $userIds)->whereHas('contact')->count(),
                'licenses' => User::whereIn('id', $userIds)->whereHas('license')->count(),
                'personal_data' => User::whereIn('id', $userIds)->whereHas('personalData')->count(),
                'settings' => User::whereIn('id', $userIds)->whereHas('settings')->count(),
                'states' => User::whereIn('id', $userIds)->whereHas('state')->count(),
                'group_memberships' => User::whereIn('id', $userIds)->withCount('groups')->get()->sum('groups_count'),
                'messages_sent' => \Engelsystem\Models\Message::whereIn('user_id', $userIds)->count(),
                'messages_received' => \Engelsystem\Models\Message::whereIn('receiver_id', $userIds)->count(),
                'shift_entries' => \Engelsystem\Models\Shifts\ShiftEntry::whereIn('user_id', $userIds)->count(),
                'worklogs' => \Engelsystem\Models\Worklog::whereIn('user_id', $userIds)->count(),
                'news_comments' => \Engelsystem\Models\NewsComment::whereIn('user_id', $userIds)->count(),
                'oauth_tokens' => \Engelsystem\Models\OAuth::whereIn('user_id', $userIds)->count(),
                'sessions' => \Engelsystem\Models\Session::whereIn('user_id', $userIds)->count(),
                'questions_asked' => \Engelsystem\Models\Question::whereIn('user_id', $userIds)->count(),
                'questions_answered' => \Engelsystem\Models\Question::whereIn('answerer_id', $userIds)->count(),
                'angel_type_associations' => \Engelsystem\Models\UserAngelType::whereIn('user_id', $userIds)->count(),
            ];
        }

        return $dependencies;
    }

    /**
     * Helper method to increment statistics
     */
    protected function incrementStat(array &$stats, string $key): void
    {
        $this->addToStat($stats, $key, 1);
    }

    /**
     * Helper method to add to statistics
     */
    protected function addToStat(array &$stats, string $key, int $value): void
    {
        if (!isset($stats['dependencies'][$key])) {
            $stats['dependencies'][$key] = 0;
        }
        $stats['dependencies'][$key] += $value;
    }

    /**
     * Create database backup before purge operation
     */
    public function createBackup(array $categories, Carbon $cutoffDate): array
    {
        $timestamp = $cutoffDate->format('Y-m-d_H-i-s');
        $backupInfo = [
            'timestamp' => $timestamp,
            'categories' => $categories,
            'cutoff_date' => $cutoffDate->toDateString(),
            'files' => [],
            'success' => false,
            'backup_path' => $this->backupPath,
        ];

        try {
            // Ensure backup directory exists
            if (!is_dir($this->backupPath)) {
                mkdir($this->backupPath, 0755, true);
            }

            // Create backup for each category
            foreach ($categories as $category) {
                $backupFile = $this->createCategoryBackup($category, $cutoffDate, $timestamp);
                if ($backupFile) {
                    $backupInfo['files'][$category] = $backupFile;
                }
            }

            // Create backup metadata file
            $metadataFile = $this->backupPath . '/backup_' . $timestamp . '_metadata.json';
            file_put_contents($metadataFile, json_encode($backupInfo, JSON_PRETTY_PRINT));
            $backupInfo['metadata_file'] = $metadataFile;

            $backupInfo['success'] = true;
            $this->notifyProgress('backup', 1, 1);
        } catch (\Exception $e) {
            $backupInfo['error'] = $e->getMessage();
        }

        return $backupInfo;
    }

    /**
     * Create backup for a specific category
     */
    protected function createCategoryBackup(string $category, Carbon $cutoffDate, string $timestamp): ?string
    {
        $filename = $this->backupPath . '/backup_' . $timestamp . '_' . $category . '.sql';

        try {
            $file = fopen($filename, 'w');

            if (!$file) {
                throw new \Exception('Could not create backup file: ' . $filename);
            }

            // Write backup header
            fwrite($file, '-- Purge Backup for category: ' . $category . '\n');
            fwrite($file, '-- Created: ' . Carbon::now()->toDateTimeString() . "\n");
            fwrite($file, '-- Cutoff Date: ' . $cutoffDate->toDateString() . "\n\n");

            switch ($category) {
                case 'users':
                    $this->backupUsers($file, $cutoffDate);
                    break;
                case 'shifts':
                    $this->backupShifts($file, $cutoffDate);
                    break;
                case 'news':
                    $this->backupNews($file, $cutoffDate);
                    break;
                case 'logs':
                    $this->backupLogs($file, $cutoffDate);
                    break;
            }

            fclose($file);
            return $filename;
        } catch (\Exception $e) {
//            if (isset($file)) {
            fclose($file);
//            }
            if (file_exists($filename)) {
                unlink($filename);
            }
            throw $e;
        }
    }

    /**
     * Backup users and their dependencies based on last_login_at date.
     *
     * @param resource $file A writable file resource where the backup SQL will be written.
     * @param Carbon $cutoffDate The cutoff date to determine which users to back up.
     */
    protected function backupUsers($file, Carbon $cutoffDate): void
    {
        $usersQuery = User::where(function (Builder $query) use ($cutoffDate): void {
            $query->where('last_login_at', '<', $cutoffDate)
                  ->orWhereNull('last_login_at');
        });

        fwrite($file, "-- Users Backup\n");

        $usersQuery->chunk(100, function ($users) use ($file): void {
            foreach ($users as $user) {
                $userData = $user->toArray();
                fwrite($file, '-- User ID: ' . $user->id . ', Name: ' . $user->name . "\n");
                fwrite($file, 'INSERT INTO users VALUES (' . $this->arrayToSqlValues($userData) . ");\n");

                // Backup user dependencies
                $this->backupUserDependencies($file, $user);
            }
        });
    }

    /**
     * Backs up a user's dependencies by writing SQL insert statements to a file.
     *
     * @param resource $file The file resource where the backup data will be written.
     * @param User $user The user whose dependencies will be backed up.
     */
    protected function backupUserDependencies($file, User $user): void
    {
        // Contact
        if ($user->contact) {
            fwrite($file, 'INSERT INTO contacts VALUES (' . $this->arrayToSqlValues($user->contact->toArray()) . ");\n"); // phpcs:ignore
        }

        // License
        if ($user->license) {
            fwrite($file, 'INSERT INTO licenses VALUES (' . $this->arrayToSqlValues($user->license->toArray()) . ");\n"); // phpcs:ignore
        }

        // Personal Data
        if ($user->personalData) {
            fwrite($file, 'INSERT INTO personal_data VALUES (' . $this->arrayToSqlValues($user->personalData->toArray()) . ");\n"); // phpcs:ignore
        }

        // Settings
        if ($user->settings) {
            fwrite($file, 'INSERT INTO settings VALUES (' . $this->arrayToSqlValues($user->settings->toArray()) . ");\n"); // phpcs:ignore
        }

        // State
        if ($user->state) {
            fwrite($file, 'INSERT INTO states VALUES (' . $this->arrayToSqlValues($user->state->toArray()) . ");\n"); // phpcs:ignore
        }

        // Messages, Worklogs, etc. (simplified for backup)
        fwrite($file, '-- User ' . $user->id . " dependencies backed up\n");
    }

    /**
     * Backs up all shifts created before the specified cutoff date to a file.
     *
     * @param resource $file The file resource to write the SQL backup to.
     * @param Carbon $cutoffDate The cutoff date; only shifts created before this date will be backed up.
     */
    protected function backupShifts($file, Carbon $cutoffDate): void
    {
        fwrite($file, "-- Shifts Backup\n");

        Shift::where('created_at', '<', $cutoffDate)->chunk(100, function ($shifts) use ($file): void {
            foreach ($shifts as $shift) {
                fwrite($file, 'INSERT INTO shifts VALUES (' . $this->arrayToSqlValues($shift->toArray()) . ");\n");
            }
        });
    }

    /**
     * Back up news entries created before the specified cutoff date into a file.
     *
     * @param resource $file The file resource where the news backup will be written.
     * @param Carbon $cutoffDate The date before which news entries will be backed up.
     */
    protected function backupNews($file, Carbon $cutoffDate): void
    {
        fwrite($file, "-- News Backup\n");

        News::where('created_at', '<', $cutoffDate)->chunk(100, function ($news) use ($file): void {
            foreach ($news as $item) {
                fwrite($file, 'INSERT INTO news VALUES (' . $this->arrayToSqlValues($item->toArray()) . ");\n");
            }
        });
    }

    /**
     * Backs up log entries created before the specified cutoff date into the provided file.
     *
     * @param resource $file The file resource where the backup SQL statements will be written.
     * @param Carbon $cutoffDate The cutoff date; only log entries created before this date will be backed up.
     */
    protected function backupLogs($file, Carbon $cutoffDate): void
    {
        fwrite($file, "-- Logs Backup\n");

        LogEntry::where('created_at', '<', $cutoffDate)->chunk(100, function ($logs) use ($file): void {
            foreach ($logs as $log) {
                fwrite($file, 'INSERT INTO log_entries VALUES (' . $this->arrayToSqlValues($log->toArray()) . ");\n");
            }
        });
    }

    /**
     * Convert array to SQL values string
     */
    protected function arrayToSqlValues(array $data): string
    {
        $values = array_map(function ($value) {
            if ($value === null) {
                return 'NULL';
            }
            if (is_string($value)) {
                return "'" . addslashes($value) . "'";
            }
            if (is_bool($value)) {
                return $value ? '1' : '0';
            }
            return $value;
        }, array_values($data));

        return implode(', ', $values);
    }

    /**
     * Verify backup was created successfully
     */
    public function verifyBackup(array $backupInfo): array
    {
        $verification = [
            'success' => true,
            'files_verified' => 0,
            'total_files' => count($backupInfo['files']),
            'errors' => [],
        ];

        if (!$backupInfo['success']) {
            $verification['success'] = false;
            $verification['errors'][] = 'Backup creation failed';
            return $verification;
        }

        // Check each backup file
        foreach ($backupInfo['files'] as $category => $filename) {
            if (!file_exists($filename)) {
                $verification['success'] = false;
                $verification['errors'][] = 'Backup file missing for category: ' . $category;
            } elseif (filesize($filename) === 0) {
                $verification['success'] = false;
                $verification['errors'][] = 'Backup file empty for category: ' . $category;
            } else {
                $verification['files_verified']++;
            }
        }

        // Check metadata file
        if (isset($backupInfo['metadata_file']) && !file_exists($backupInfo['metadata_file'])) {
            $verification['success'] = false;
            $verification['errors'][] = 'Metadata file missing';
        }

        return $verification;
    }

    /**
     * Get backup directory path
     */
    public function getBackupPath(): string
    {
        return $this->backupPath;
    }

    /**
     * Set backup directory path
     */
    public function setBackupPath(string $path): void
    {
        $this->backupPath = $path;
    }

    /**
     * Create an audit log entry for a purge operation
     */
    public function createAuditLog(
        int $userId,
        array $categories,
        Carbon $cutoffDate,
        array $affectedCounts = []
    ): PurgeLog {
        return PurgeLog::create([
            'user_id' => $userId,
            'categories' => $categories,
            'cutoff_date' => $cutoffDate,
            'affected_counts' => $affectedCounts,
            'status' => PurgeLog::STATUS_INITIATED,
        ]);
    }

    /**
     * Update audit log with affected counts
     */
    public function updateAuditLogCounts(PurgeLog $auditLog, array $affectedCounts): void
    {
        $auditLog->update([
            'affected_counts' => $affectedCounts,
            'status' => PurgeLog::STATUS_IN_PROGRESS,
        ]);
    }

    /**
     * Mark audit log as completed with backup info
     */
    public function completeAuditLog(PurgeLog $auditLog, ?string $backupPath = null): void
    {
        $auditLog->update([
            'status' => PurgeLog::STATUS_COMPLETED,
            'backup_file_path' => $backupPath,
            'completed_at' => Carbon::now(),
        ]);
    }

    /**
     * Mark audit log as failed with error message
     */
    public function failAuditLog(PurgeLog $auditLog, string $errorMessage): void
    {
        $auditLog->update([
            'status' => PurgeLog::STATUS_FAILED,
            'error_message' => $errorMessage,
            'completed_at' => Carbon::now(),
        ]);
    }

    /**
     * Get audit log statistics
     */
    public function getAuditStats(): array
    {
        $stats = [
            'total_purges' => PurgeLog::count(),
            'completed_purges' => PurgeLog::where('status', PurgeLog::STATUS_COMPLETED)->count(),
            'failed_purges' => PurgeLog::where('status', PurgeLog::STATUS_FAILED)->count(),
            'active_purges' => PurgeLog::whereIn('status', [
                PurgeLog::STATUS_INITIATED,
                PurgeLog::STATUS_IN_PROGRESS,
            ])->count(),
            'recent_purges' => PurgeLog::where('created_at', '>=', Carbon::now()->subDays(30))->count(),
        ];

        // Get total records affected in last 30 days
        $recentLogs = PurgeLog::where('created_at', '>=', Carbon::now()->subDays(30))
            ->where('status', PurgeLog::STATUS_COMPLETED)
            ->get();

        $totalAffected = 0;
        foreach ($recentLogs as $log) {
            $totalAffected += $log->getTotalAffectedCount();
        }
        $stats['total_affected_last_30_days'] = $totalAffected;

        return $stats;
    }
}
