<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property mixed $status
 * @method static where(string $string, mixed $id)
 * @method static create(mixed $validated)
 * @method static find(\Illuminate\Routing\Route|object|string|null $route)
 * @method static findOrFail(\Illuminate\Routing\Route|object|string|null $route)
 */
class Project extends Model
{
    protected $fillable = [
        'name', 'description', 'color', 'status', 'priority',
        'due_date', 'start_date', 'user_id', 'sort_order'
    ];

    protected $casts = [
        'due_date' => 'date',
        'start_date' => 'date',
        'completed_at' => 'datetime',
    ];

    /**
     * Boot the model to add event listeners
     */
    protected static function boot(): void
    {
        parent::boot();

        static::updating(function ($project) {
            if ($project->isDirty('status')) {
                if ($project->status === 'completed' && $project->getOriginal('status') !== 'completed') {
                    $project->completed_at = now();
                }
                elseif ($project->status !== 'completed' && $project->getOriginal('status') === 'completed') {
                    $project->completed_at = null;
                }
            }
        });

        static::creating(function ($project) {
            if ($project->status === 'completed') {
                $project->completed_at = now();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function completedTasks(): HasMany
    {
        return $this->tasks()->where('status', 'completed');
    }

    public function getCompletionPercentageAttribute(): float
    {
        $totalTasks = $this->tasks()->count();
        if ($totalTasks === 0) return 0;

        $completedTasks = $this->completedTasks()->count();
        return round(($completedTasks / $totalTasks) * 100, 2);
    }

    /**
     * Scope to get only completed projects
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to get only active projects
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Check if project is completed
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
