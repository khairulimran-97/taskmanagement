<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Task;
use App\Models\Project;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Throwable;

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

        // Handle status change logic
        if (isset($validated['status'])) {
            $this->handleStatusChange($task, $validated['status']);
        }

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
     * Quick status update for tasks (AJAX endpoint)
     */
    public function quickStatusUpdate(Request $request, string $id): JsonResponse
    {
        $task = Task::where('user_id', Auth::id())->findOrFail($id);

        $validated = $request->validate([
            'status' => [
                'required',
                'string',
                Rule::in(['todo', 'in_progress', 'completed', 'cancelled'])
            ]
        ]);

        $oldStatus = $task->status;
        $newStatus = $validated['status'];

        // Handle status change
        $this->handleStatusChange($task, $newStatus);

        $task->update(['status' => $newStatus]);

        return response()->json([
            'success' => true,
            'message' => 'Task status updated successfully',
            'task' => [
                'id' => $task->id,
                'status' => $task->status,
                'completed_at' => $task->completed_at,
                'old_status' => $oldStatus
            ]
        ]);
    }

    /**
     * Bulk status update for multiple tasks
     */
    public function bulkStatusUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'task_ids' => 'required|array',
            'task_ids.*' => 'exists:tasks,id',
            'status' => [
                'required',
                'string',
                Rule::in(['todo', 'in_progress', 'completed', 'cancelled'])
            ]
        ]);

        $tasks = Task::where('user_id', Auth::id())
            ->whereIn('id', $validated['task_ids'])
            ->get();

        $updatedTasks = [];
        foreach ($tasks as $task) {
            $oldStatus = $task->status;
            $this->handleStatusChange($task, $validated['status']);
            $task->update(['status' => $validated['status']]);

            $updatedTasks[] = [
                'id' => $task->id,
                'status' => $task->status,
                'completed_at' => $task->completed_at,
                'old_status' => $oldStatus
            ];
        }

        return response()->json([
            'success' => true,
            'message' => count($updatedTasks) . ' tasks updated successfully',
            'tasks' => $updatedTasks
        ]);
    }

    /**
     * Toggle task completion status
     */
    public function toggleCompletion(string $id): JsonResponse
    {
        $task = Task::where('user_id', Auth::id())->findOrFail($id);

        $newStatus = $task->status === 'completed' ? 'todo' : 'completed';
        $oldStatus = $task->status;

        $this->handleStatusChange($task, $newStatus);
        $task->update(['status' => $newStatus]);

        return response()->json([
            'success' => true,
            'message' => 'Task completion toggled successfully',
            'task' => [
                'id' => $task->id,
                'status' => $task->status,
                'completed_at' => $task->completed_at,
                'old_status' => $oldStatus
            ]
        ]);
    }

    /**
     * Handle status change logic (completed_at field, etc.)
     */
    private function handleStatusChange(Task $task, string $newStatus): void
    {
        if ($newStatus === 'completed' && $task->status !== 'completed') {
            $task->completed_at = now();
        } elseif ($newStatus !== 'completed' && $task->status === 'completed') {
            $task->completed_at = null;
        }
    }

    /**
     * Remove the specified task from storage.
     */
    public function destroy(string $id): RedirectResponse
    {
        $task = Task::where('user_id', Auth::id())->findOrFail($id);

        // Get subtasks count for feedback
        $subtasksCount = $task->subtasks()->count();

        $task->delete();

        $message = $subtasksCount > 0
            ? "Task and {$subtasksCount} subtask(s) deleted successfully."
            : 'Task deleted successfully.';

        return redirect()->back()->with('success', $message);
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

    /**
     * Get task statistics for a project
     */
    public function getProjectTaskStats(string $projectId): JsonResponse
    {
        $project = Project::where('user_id', Auth::id())->findOrFail($projectId);

        $stats = [
            'total' => Task::where('project_id', $projectId)->count(),
            'completed' => Task::where('project_id', $projectId)->where('status', 'completed')->count(),
            'in_progress' => Task::where('project_id', $projectId)->where('status', 'in_progress')->count(),
            'todo' => Task::where('project_id', $projectId)->where('status', 'todo')->count(),
            'cancelled' => Task::where('project_id', $projectId)->where('status', 'cancelled')->count(),
            'overdue' => Task::where('project_id', $projectId)
                ->where('due_date', '<', now())
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->count(),
        ];

        $stats['completion_percentage'] = $stats['total'] > 0
            ? round(($stats['completed'] / $stats['total']) * 100, 2)
            : 0;

        return response()->json($stats);
    }

    /**
     * Reorder tasks within a project
     * @throws Throwable
     */
    public function reorder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'updates' => 'required|array',
            'updates.*.id' => 'required|exists:tasks,id',
            'updates.*.sort_order' => 'required|integer|min:0'
        ]);

        DB::beginTransaction();

        try {
            $taskIds = collect($validated['updates'])->pluck('id')->toArray();

            $tasks = Task::where('user_id', Auth::id())
                ->whereIn('id', $taskIds)
                ->get()
                ->keyBy('id');

            if ($tasks->count() !== count($taskIds)) {
                DB::rollBack();
                return redirect()->back()->withErrors(['error' => 'One or more tasks not found or access denied']);
            }

            $reorderDetails = [];

            foreach ($validated['updates'] as $update) {
                $task = $tasks->get($update['id']);
                if ($task) {
                    $task->update(['sort_order' => $update['sort_order']]);
                    $reorderDetails[] = "\"{$task->title}\" to position {$update['sort_order']}";
                }
            }

            DB::commit();

            $message = 'Tasks reordered successfully: ' . implode(', ', $reorderDetails);
            return redirect()->back()->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => 'Failed to reorder tasks: ' . $e->getMessage()]);
        }
    }

    /**
     * Get overdue tasks for the authenticated user
     */
    public function getOverdueTasks(): JsonResponse
    {
        $overdueTasks = Task::where('user_id', Auth::id())
            ->where('due_date', '<', now())
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->with(['project', 'tags'])
            ->orderBy('due_date', 'asc')
            ->get();

        return response()->json($overdueTasks);
    }

    /**
     * Get tasks due soon (within next 7 days)
     */
    public function getTasksDueSoon(): JsonResponse
    {
        $tasksDueSoon = Task::where('user_id', Auth::id())
            ->whereBetween('due_date', [now(), now()->addDays(7)])
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->with(['project', 'tags'])
            ->orderBy('due_date', 'asc')
            ->get();

        return response()->json($tasksDueSoon);
    }
}
