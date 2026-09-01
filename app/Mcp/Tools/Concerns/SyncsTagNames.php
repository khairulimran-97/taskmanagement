<?php

namespace App\Mcp\Tools\Concerns;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Str;

trait SyncsTagNames
{
    /**
     * Resolve tag names to ids for this user, creating missing tags —
     * same behavior as the dashboard's new_tags flow.
     *
     * @param  array<int, string>  $names
     * @return array<int, int>
     */
    protected function tagIdsForNames(User $user, array $names): array
    {
        return collect($names)
            ->map(fn ($name) => trim($name))
            ->filter()
            ->unique()
            ->map(fn ($name) => Tag::firstOrCreate(
                ['name' => $name, 'user_id' => $user->id],
                ['slug' => Str::slug($name), 'color' => '#6B7280'],
            )->id)
            ->values()
            ->all();
    }
}
