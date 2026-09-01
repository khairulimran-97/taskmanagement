<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\FormatsPayloads;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class UpdateProjectTool extends Tool
{
    use FormatsPayloads;

    protected string $description = 'Update a project\'s fields — name, description, color, status, priority, or dates. Only the fields you pass change.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The project id.')->required(),
            'name' => $schema->string(),
            'description' => $schema->string(),
            'color' => $schema->string()->description('Hex color, e.g. #3B82F6.'),
            'status' => $schema->string()->enum(['active', 'paused', 'completed', 'archived']),
            'priority' => $schema->string()->enum(['low', 'medium', 'high']),
            'start_date' => $schema->string()->description('YYYY-MM-DD.'),
            'due_date' => $schema->string()->description('YYYY-MM-DD.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $project = $request->user()->projects()->find($request->get('id'));

        if (! $project) {
            return Response::error('Project not found.');
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string|max:5000',
            'color' => 'sometimes|nullable|string|max:7',
            'status' => 'sometimes|string|in:active,paused,completed,archived',
            'priority' => 'sometimes|string|in:low,medium,high',
            'start_date' => 'sometimes|nullable|date',
            'due_date' => 'sometimes|nullable|date',
        ]);

        $project->update($validated);

        return Response::json(['updated' => $this->projectPayload($project->fresh())]);
    }
}
