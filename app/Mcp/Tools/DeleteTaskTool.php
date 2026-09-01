<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class DeleteTaskTool extends Tool
{
    protected string $description = 'Permanently delete a task and its subtasks — same as the dashboard delete. Confirm with the user before calling this.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The task id to delete.')->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        $task = $request->user()->tasks()->withCount('subtasks')->find($request->get('id'));

        if (! $task) {
            return Response::error('Task not found.');
        }

        $title = $task->title;
        $subtaskCount = $task->subtasks_count;
        $task->delete();

        return Response::json(['deleted' => $title, 'subtasks_removed' => $subtaskCount]);
    }
}
