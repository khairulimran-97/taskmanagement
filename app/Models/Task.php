<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    protected $fillable = [
        'title', 'description', 'status', 'priority', 'due_date',
        'start_date', 'project_id', 'user_id', 'assigned_to',
        'parent_task_id', 'sort_order'
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'start_date' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Boot the model to add event listeners
     */
    protected static function boot(): void
    {
        parent::boot();

        static::updating(function ($task) {
            if ($task->isDirty('status')) {
                if ($task->status === 'completed' && $task->getOriginal('status') !== 'completed') {
                    $task->completed_at = now();
                }
                elseif ($task->status !== 'completed' && $task->getOriginal('status') === 'completed') {
                    $task->completed_at = null;
                }
            }
        });

        static::creating(function ($task) {
            if ($task->status === 'completed') {
                $task->completed_at = now();
            }
        });
    }

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

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
            ->whereNotIn('status', ['completed', 'cancelled']);
    }

    /**
     * Check if task is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Get the completion date in a readable format
     */
    public function getFormattedCompletedAtAttribute(): ?string
    {
        return $this->completed_at ? $this->completed_at->format('M j, Y') : null;
    }
}
