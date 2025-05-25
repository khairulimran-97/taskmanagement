<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string|null $content
 * @property string|null $tags
 * @property bool $is_pinned
 * @property int $user_id
 * @property Carbon|null $last_accessed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @method static forUser(int|string|null $id)
 * @method static create(mixed $validated)
 */
class Note extends Model
{
    protected $fillable = [
        'title',
        'content',
        'tags',
        'is_pinned',
        'user_id',
        'last_accessed_at'
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'last_accessed_at' => 'datetime',
    ];

    /**
     * Get the user that owns the note
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to get notes for current user
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to search notes by title and content
     */
    public function scopeSearch(Builder $query, string $searchTerm): Builder
    {
        return $query->where(function ($q) use ($searchTerm) {
            $q->where('title', 'LIKE', "%{$searchTerm}%")
                ->orWhere('content', 'LIKE', "%{$searchTerm}%")
                ->orWhere('tags', 'LIKE', "%{$searchTerm}%");
        });
    }

    /**
     * Scope to order by pinned status and creation date
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('is_pinned', 'desc')
            ->orderBy('updated_at', 'desc');
    }

    /**
     * Get tags as array
     */
    public function getTagsArrayAttribute(): array
    {
        if (!$this->tags) {
            return [];
        }

        return array_filter(array_map('trim', explode(',', $this->tags)));
    }

    /**
     * Get formatted content preview (HTML aware)
     */
    public function getContentPreviewAttribute(): string
    {
        if (!$this->content) {
            return 'No content';
        }

        // Strip HTML tags for preview
        $preview = strip_tags($this->content);
        // Remove extra whitespace
        $preview = preg_replace('/\s+/', ' ', $preview);
        $preview = trim($preview);

        return strlen($preview) > 100 ? substr($preview, 0, 100) . '...' : $preview;
    }

    /**
     * Get word count (HTML aware)
     */
    public function getWordCountAttribute(): int
    {
        if (!$this->content) {
            return 0;
        }

        // Strip HTML tags before counting words
        $textContent = strip_tags($this->content);
        return str_word_count($textContent);
    }

    /**
     * Update last accessed timestamp
     */
    public function markAsAccessed(): void
    {
        $this->update(['last_accessed_at' => now()]);
    }

    /**
     * Generate auto title from content if title is empty
     */
    public function generateAutoTitle(): string
    {
        if ($this->title && $this->title !== 'Untitled') {
            return $this->title;
        }

        if (!$this->content) {
            return 'Untitled Note';
        }

        // Strip HTML tags and get first line
        $textContent = strip_tags($this->content);
        $firstLine = trim(explode("\n", $textContent)[0]);

        if (strlen($firstLine) > 50) {
            $firstLine = substr($firstLine, 0, 50) . '...';
        }

        return $firstLine ?: 'Untitled Note';
    }
}
