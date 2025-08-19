<?php

declare(strict_types=1);

namespace Engelsystem\Models;

use Carbon\Carbon;
use Engelsystem\Models\User\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PurgeLog model for tracking purge operations
 *
 * @property int                 $id
 * @property int                 $user_id
 * @property array               $categories
 * @property Carbon              $cutoff_date
 * @property array               $affected_counts
 * @property string              $status
 * @property string|null         $error_message
 * @property string|null         $backup_file_path
 * @property Carbon              $created_at
 * @property Carbon|null         $completed_at
 * @property Carbon              $updated_at
 * @property-read User           $user
 */
class PurgeLog extends BaseModel
{
    /** @var string[] */
    protected $fillable = [ // phpcs:ignore
        'user_id',
        'categories',
        'cutoff_date',
        'affected_counts',
        'status',
        'error_message',
        'backup_file_path',
        'completed_at',
    ];

    /** @var string[] */
    protected $casts = [ // phpcs:ignore
        'categories' => 'array',
        'cutoff_date' => 'date',
        'affected_counts' => 'array',
        'created_at' => 'datetime',
        'completed_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /** @var string[] */
    protected array $dates = [
        'cutoff_date',
        'created_at',
        'completed_at',
        'updated_at',
    ];

    // Status constants
    public const STATUS_INITIATED = 'initiated';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Get the user who initiated the purge
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get recent purge logs
     */
    public static function getRecentLogs(int $limit = 50): Collection
    {
        return static::with('user')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get purge logs by status
     */
    public static function getByStatus(string $status): Collection
    {
        return static::with('user')
            ->where('status', $status)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get active (in progress) purge operations
     */
    public static function getActivePurges(): Collection
    {
        return static::whereIn('status', [
            self::STATUS_INITIATED,
            self::STATUS_IN_PROGRESS,
        ])->get();
    }

    /**
     * Mark the purge as completed
     */
    public function markCompleted(): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => Carbon::now(),
        ]);
    }

    /**
     * Mark the purge as failed
     */
    public function markFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'completed_at' => Carbon::now(),
        ]);
    }

    /**
     * Mark the purge as cancelled
     */
    public function markCancelled(): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'completed_at' => Carbon::now(),
        ]);
    }

    /**
     * Update progress status
     */
    public function updateProgress(string $status): void
    {
        $this->update(['status' => $status]);
    }

    /**
     * Check if the purge is still active
     */
    public function isActive(): bool
    {
        return in_array($this->status, [
            self::STATUS_INITIATED,
            self::STATUS_IN_PROGRESS,
        ]);
    }

    /**
     * Check if the purge is completed (successfully or failed)
     */
    public function isCompleted(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
        ]);
    }

    /**
     * Get total affected records count
     */
    public function getTotalAffectedCount(): int
    {
        if (!is_array($this->affected_counts)) {
            return 0;
        }

        return array_sum($this->affected_counts);
    }

    /**
     * Get formatted categories string
     */
    public function getCategoriesString(): string
    {
        if (!is_array($this->categories)) {
            return '';
        }

        return implode(', ', array_map('ucfirst', $this->categories));
    }

    /**
     * Get status badge color for UI
     */
    public function getStatusBadgeColor(): string
    {
        return match ($this->status) {
            self::STATUS_INITIATED => 'primary',
            self::STATUS_IN_PROGRESS => 'warning',
            self::STATUS_COMPLETED => 'success',
            self::STATUS_FAILED => 'danger',
            self::STATUS_CANCELLED => 'secondary',
            default => 'light',
        };
    }

    /**
     * Get duration of the purge operation
     */
    public function getDuration(): ?string
    {
        if (!$this->completed_at) {
            return null;
        }

        $duration = $this->created_at->diffInSeconds($this->completed_at);

        if ($duration < 60) {
            return $duration . ' seconds';
        } elseif ($duration < 3600) {
            return floor($duration / 60) . ' minutes';
        } else {
            return floor($duration / 3600) . ' hours';
        }
    }
}
