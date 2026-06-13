<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $name
 * @property string $type
 * @property string $value
 * @property string|null $notes
 * @property int $user_id
 * @method static forUser(int|string|null $id)
 * @method static create(mixed $validated)
 * @method static find(\Illuminate\Routing\Route|object|string|null $route)
 * @method static findOrFail(mixed $id)
 */
class Secret extends Model
{
    protected $fillable = [
        'name',
        'type',
        'value',
        'notes',
        'user_id',
    ];

    /**
     * Encrypt secret payloads at rest. Laravel transparently encrypts on save
     * and decrypts on read using APP_KEY.
     */
    protected $casts = [
        'value' => 'encrypted',
        'notes' => 'encrypted',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to get secrets for current user
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to search secrets by name and type. Value/notes are encrypted, so
     * they can't be searched at the database level.
     */
    public function scopeSearch(Builder $query, string $searchTerm): Builder
    {
        return $query->where(function ($q) use ($searchTerm) {
            $q->where('name', 'LIKE', "%{$searchTerm}%")
                ->orWhere('type', 'LIKE', "%{$searchTerm}%");
        });
    }

    /**
     * Scope to order newest first
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('created_at', 'desc');
    }
}
