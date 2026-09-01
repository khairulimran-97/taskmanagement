<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\FormatsPayloads;
use App\Mcp\Tools\Concerns\SyncsTagNames;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Server\Tool;

class UpdateTaskTool extends Tool
{
    use FormatsPayloads, SyncsTagNames;

    protected string $description = 'Update a task\'s fields. Only the fields you pass change; passing tags REPLACES the task\'s tag set. For status-only changes prefer update-task-status.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The task id.')->required(),
            'title' => $schema->string(),
            'description' => $schema->string(),
            'project_id' => $schema->integer()->description('Move the task to another project.'),
            'status' => $schema->string()->enum(['todo', 'in_progress', 'completed', 'cancelled']),
            'priority' => $schema->string()->enum(['low', 'medium', 'high', 'urgent']),
            'start_date' => $schema->string()->description('Datetime or date; empty string clears it.'),
            'due_date' => $schema->string()->description('Datetime or date; empty string clears it.'),
            'tags' => $schema->array()->items($schema->string())->description('Full replacement tag list; [] removes all tags.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $request->user();
        $task = $user->tasks()->find($request->get('id'));

        if (! $task) {
            return Response::error('Task not found.');
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string|max:10000',
            'project_id' => 'sometimes|integer',
            'status' => 'sometimes|string|in:todo,in_progress,completed,cancelled',
            'priority' => 'sometimes|string|in:low,medium,high,urgent',
            'start_date' => 'sometimes|nullable|date',
            'due_date' => 'sometimes|nullable|date',
            'tags' => 'sometimes|array',
            'tags.*' => 'string|max:255',
        ]);

        if (array_key_exists('project_id', $validated)
            && ! $user->projects()->whereKey($validated['project_id'])->exists()) {
            return Response::error('Project not found.');
        }

        // Mirror the dashboard's completed_at bookkeeping on status flips
        if (array_key_exists('status', $validated) && $validated['status'] !== $task->status) {
            $validated['completed_at'] = $validated['status'] === 'completed' ? now() : null;
        }

        if (array_key_exists('tags', $validated)) {
            $task->tags()->sync($this->tagIdsForNames($user, $validated['tags']));
            unset($validated['tags']);
        }

        $task->update($validated);

        return Response::json(['updated' => $this->taskPayload($task->fresh()->load('tags:id,name'))]);
    }
}
