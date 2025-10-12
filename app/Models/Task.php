<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

/**
 * @method static where(string $string, int|string|null $id)
 * @method static create(mixed[] $taskData)
 * @property string $status
 * @property string $priority
 * @property Carbon|null $due_date
 * @property Carbon|null $start_date
 * @property Carbon|null $completed_at
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property int $project_id
 * @property int $user_id
 * @property int|null $assigned_to
 * @property int|null $parent_task_id
 * @property int $sort_order
 */
class Task extends Model
{
    protected $fillable = [
        'title', 'description', 'status', 'priority', 'due_date',
        'start_date', 'project_id', 'user_id', 'assigned_to',
        'parent_task_id', 'sort_order', 'completed_at'
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'start_date' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // Status constants
    public const STATUS_TODO = 'todo';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    // Priority constants
    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    /**
     * Boot the model to add event listeners
     */
    protected static function boot(): void
    {
        parent::boot();

        static::updating(function ($task) {
            if ($task->isDirty('status')) {
                $task->handleStatusChange($task->getOriginal('status'), $task->status);
            }
        });

        static::creating(function ($task) {
            if ($task->status === self::STATUS_COMPLETED) {
                $task->completed_at = now();
            }
        });

        // When a task is deleted, also delete its subtasks
        static::deleting(function ($task) {
            $task->subtasks()->delete();
        });
    }

    /**
     * Handle status changes
     */
    protected function handleStatusChange(string $oldStatus, string $newStatus): void
    {
        if ($newStatus === self::STATUS_COMPLETED && $oldStatus !== self::STATUS_COMPLETED) {
            $this->completed_at = now();
        } elseif ($newStatus !== self::STATUS_COMPLETED && $oldStatus === self::STATUS_COMPLETED) {
            $this->completed_at = null;
        }
    }

    /**
     * Relationships
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function parentTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_task_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_task_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'task_tag')->withTimestamps();
    }

    /**
     * Query Scopes
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeTodo($query)
    {
        return $query->where('status', self::STATUS_TODO);
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', self::STATUS_IN_PROGRESS);
    }

    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
            ->whereNotIn('status', [self::STATUS_COMPLETED, self::STATUS_CANCELLED]);
    }

    public function scopeDueSoon($query, int $days = 7)
    {
        return $query->whereBetween('due_date', [now(), now()->addDays($days)])
            ->whereNotIn('status', [self::STATUS_COMPLETED, self::STATUS_CANCELLED]);
    }

    public function scopeHighPriority($query)
    {
        return $query->whereIn('priority', [self::PRIORITY_HIGH, self::PRIORITY_URGENT]);
    }

    public function scopeByProject($query, int $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeRootTasks($query)
    {
        return $query->whereNull('parent_task_id');
    }

    public function scopeSubtasks($query)
    {
        return $query->whereNotNull('parent_task_id');
    }

    /**
     * Helper Methods
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isTodo(): bool
    {
        return $this->status === self::STATUS_TODO;
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isOverdue(): bool
    {
        return $this->due_date &&
            $this->due_date->isPast() &&
            !in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED]);
    }

    public function isDueSoon(int $days = 7): bool
    {
        return $this->due_date &&
            $this->due_date->isFuture() &&
            $this->due_date->lte(now()->addDays($days)) &&
            !in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED]);
    }

    public function isHighPriority(): bool
    {
        return in_array($this->priority, [self::PRIORITY_HIGH, self::PRIORITY_URGENT]);
    }

    public function hasSubtasks(): bool
    {
        return $this->subtasks()->exists();
    }

    public function isSubtask(): bool
    {
        return !is_null($this->parent_task_id);
    }

    /**
     * Get formatted completion date
     */
    public function getFormattedCompletedAtAttribute(): ?string
    {
        return $this->completed_at ? $this->completed_at->format('M j, Y g:i A') : null;
    }

    /**
     * Get formatted due date
     */
    public function getFormattedDueDateAttribute(): ?string
    {
        return $this->due_date ? $this->due_date->format('M j, Y') : null;
    }

    /**
     * Get human readable due date
     */
    public function getDueDateForHumansAttribute(): ?string
    {
        return $this->due_date ? $this->due_date->diffForHumans() : null;
    }

    /**
     * Get status color for UI
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            self::STATUS_TODO => 'gray',
            self::STATUS_IN_PROGRESS => 'blue',
            self::STATUS_COMPLETED => 'green',
            self::STATUS_CANCELLED => 'red',
            default => 'gray'
        };
    }

    /**
     * Get priority color for UI
     */
    public function getPriorityColorAttribute(): string
    {
        return match($this->priority) {
            self::PRIORITY_LOW => 'green',
            self::PRIORITY_MEDIUM => 'yellow',
            self::PRIORITY_HIGH => 'orange',
            self::PRIORITY_URGENT => 'red',
            default => 'gray'
        };
    }

    /**
     * Get completion percentage for tasks with subtasks
     */
    public function getCompletionPercentageAttribute(): float
    {
        $totalSubtasks = $this->relationLoaded('subtasks')
            ? $this->subtasks->count()
            : $this->subtasks()->count();

        if ($totalSubtasks === 0) {
            return $this->isCompleted() ? 100.0 : 0.0;
        }

        $completedSubtasks = $this->relationLoaded('subtasks')
            ? $this->subtasks->where('status', self::STATUS_COMPLETED)->count()
            : $this->subtasks()->completed()->count();

        return round(($completedSubtasks / $totalSubtasks) * 100, 2);
    }

    /**
     * Get days until due date
     */
    public function getDaysUntilDueAttribute(): ?int
    {
        if (!$this->due_date) return null;

        return now()->diffInDays($this->due_date, false);
    }

    /**
     * Toggle task completion
     */
    public function toggleCompletion(): bool
    {
        $newStatus = $this->isCompleted() ? self::STATUS_TODO : self::STATUS_COMPLETED;

        return $this->update(['status' => $newStatus]);
    }

    /**
     * Mark task as completed
     */
    public function markAsCompleted(): bool
    {
        return $this->update(['status' => self::STATUS_COMPLETED]);
    }

    /**
     * Mark task as in progress
     */
    public function markAsInProgress(): bool
    {
        return $this->update(['status' => self::STATUS_IN_PROGRESS]);
    }

    /**
     * Mark task as todo
     */
    public function markAsTodo(): bool
    {
        return $this->update(['status' => self::STATUS_TODO]);
    }

    /**
     * Get all available statuses
     */
    public static function getAvailableStatuses(): array
    {
        return [
            self::STATUS_TODO => 'To Do',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    /**
     * Get all available priorities
     */
    public static function getAvailablePriorities(): array
    {
        return [
            self::PRIORITY_LOW => 'Low',
            self::PRIORITY_MEDIUM => 'Medium',
            self::PRIORITY_HIGH => 'High',
            self::PRIORITY_URGENT => 'Urgent',
        ];
    }
}
