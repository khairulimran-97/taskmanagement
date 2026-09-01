<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class UpdateTaskStatusTool extends Tool
{
    protected string $description = 'Set the status of one or many tasks at once — the dashboard\'s quick-status and bulk actions in one tool. Completing sets completed_at, reopening clears it.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'task_ids' => $schema->array()->items($schema->integer())->description('One or more task ids.')->required(),
            'status' => $schema->string()->enum(['todo', 'in_progress', 'completed', 'cancelled'])->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'task_ids' => 'required|array|min:1',
            'task_ids.*' => 'integer',
            'status' => 'required|string|in:todo,in_progress,completed,cancelled',
        ]);

        $tasks = $request->user()->tasks()->whereIn('id', $validated['task_ids'])->get();

        if ($tasks->isEmpty()) {
            return Response::error('No matching tasks found.');
        }

        foreach ($tasks as $task) {
            $task->update([
                'status' => $validated['status'],
                'completed_at' => $validated['status'] === 'completed' ? now() : null,
            ]);
        }

        return Response::json([
            'status' => $validated['status'],
            'updated_ids' => $tasks->pluck('id')->all(),
            'not_found_ids' => array_values(array_diff($validated['task_ids'], $tasks->pluck('id')->all())),
        ]);
    }
}
