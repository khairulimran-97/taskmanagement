<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\FormatsPayloads;
use App\Mcp\Tools\Concerns\SyncsTagNames;
use App\Models\Task;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class CreateTaskTool extends Tool
{
    use FormatsPayloads, SyncsTagNames;

    protected string $description = 'Create a task in a project. Pass tag names freely — unknown tags are created. Use parent_task_id to make it a subtask. Defaults: status todo, priority medium.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->description('Task title.')->required(),
            'project_id' => $schema->integer()->description('The project this task belongs to.')->required(),
            'description' => $schema->string(),
            'status' => $schema->string()->enum(['todo', 'in_progress', 'completed', 'cancelled']),
            'priority' => $schema->string()->enum(['low', 'medium', 'high', 'urgent']),
            'start_date' => $schema->string()->description('Datetime, e.g. 2026-09-01 09:00 or YYYY-MM-DD.'),
            'due_date' => $schema->string()->description('Datetime or date; must not be before start_date.'),
            'parent_task_id' => $schema->integer()->description('Make this a subtask of that task.'),
            'tags' => $schema->array()->items($schema->string())->description('Tag names to attach; created if missing.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $request->user();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'project_id' => 'required|integer',
            'description' => 'nullable|string|max:10000',
            'status' => 'nullable|string|in:todo,in_progress,completed,cancelled',
            'priority' => 'nullable|string|in:low,medium,high,urgent',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:start_date',
            'parent_task_id' => 'nullable|integer',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:255',
        ]);

        if (! $user->projects()->whereKey($validated['project_id'])->exists()) {
            return Response::error('Project not found.');
        }

        if (! empty($validated['parent_task_id'])
            && ! $user->tasks()->whereKey($validated['parent_task_id'])->exists()) {
            return Response::error('Parent task not found.');
        }

        $tagNames = $validated['tags'] ?? [];
        unset($validated['tags']);

        $validated['user_id'] = $user->id;
        $validated['sort_order'] = ((int) Task::where('project_id', $validated['project_id'])->max('sort_order')) + 1;

        if (($validated['status'] ?? null) === 'completed') {
            $validated['completed_at'] = now();
        }

        // refresh() so DB-level defaults (status todo, priority medium) appear in the response
        $task = Task::create($validated)->refresh();
        $task->tags()->sync($this->tagIdsForNames($user, $tagNames));

        return Response::json(['created' => $this->taskPayload($task->load('tags:id,name'))]);
    }
}
