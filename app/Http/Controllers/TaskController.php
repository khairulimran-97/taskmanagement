<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Task;
use App\Models\Project;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class TaskController extends Controller
{
    /**
     * Store a newly created task in storage.
     */
    public function store(StoreTaskRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['user_id'] = Auth::id();

        // Set sort order if not provided
        if (empty($validated['sort_order'])) {
            $maxSortOrder = Task::where('project_id', $validated['project_id'])->max('sort_order') ?? 0;
            $validated['sort_order'] = $maxSortOrder + 1;
        }

        // Create new tags if any
        $newTagIds = [];
        if (!empty($validated['new_tags'])) {
            foreach ($validated['new_tags'] as $tagName) {
                $tag = Tag::firstOrCreate([
                    'name' => trim($tagName),
                    'user_id' => Auth::id(),
                ], [
                    'slug' => Str::slug(trim($tagName)),
                    'color' => '#6B7280',
                ]);
                $newTagIds[] = $tag->id;
            }
        }

        // Remove non-fillable fields before creating task
        $taskData = collect($validated)->except(['tag_ids', 'new_tags'])->toArray();
        $task = Task::create($taskData);

        // Attach tags if provided
        $allTagIds = array_merge($validated['tag_ids'] ?? [], $newTagIds);
        if (!empty($allTagIds)) {
            $task->tags()->attach(array_unique($allTagIds));
        }

        return redirect()->back()->with('success', 'Task created successfully.');
    }

    /**
     * Update the specified task in storage.
     */
    public function update(UpdateTaskRequest $request, string $id): RedirectResponse
    {
        $task = Task::where('user_id', Auth::id())->findOrFail($id);
        $validated = $request->validated();

        // Remove tags from validated data before updating
        $taskData = collect($validated)->except(['tag_ids'])->toArray();
        $task->update($taskData);

        // Sync tags if provided
        if (isset($validated['tag_ids'])) {
            $task->tags()->sync($validated['tag_ids']);
        }

        return redirect()->back()->with('success', 'Task updated successfully.');
    }

    /**
     * Remove the specified task from storage.
     */
    public function destroy(string $id): RedirectResponse
    {
        $task = Task::where('user_id', Auth::id())->findOrFail($id);
        $task->delete();

        return redirect()->back()->with('success', 'Task deleted successfully.');
    }

    /**
     * Get tasks for a specific project as JSON
     */
    public function getProjectTasks(string $projectId): JsonResponse
    {
        $project = Project::where('user_id', Auth::id())->findOrFail($projectId);

        $tasks = Task::where('project_id', $projectId)
            ->with(['tags', 'subtasks.tags', 'parentTask'])
            ->orderBy('sort_order')
            ->get();

        return response()->json($tasks);
    }
}
