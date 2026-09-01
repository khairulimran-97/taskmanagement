<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\FormatsPayloads;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class GetTaskTool extends Tool
{
    use FormatsPayloads;

    protected string $description = 'Get one task in full, including its subtasks and tags.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The task id.')->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        $task = $request->user()->tasks()
            ->with(['tags:id,name', 'project:id,name', 'subtasks'])
            ->find($request->get('id'));

        if (! $task) {
            return Response::error('Task not found.');
        }

        $payload = $this->taskPayload($task);
        $payload['subtasks'] = $task->subtasks->map(fn ($subtask) => $this->taskPayload($subtask))->all();

        return Response::json(['task' => $payload]);
    }
}
