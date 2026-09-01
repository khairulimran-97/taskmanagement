<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\FormatsPayloads;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class CreateProjectTool extends Tool
{
    use FormatsPayloads;

    protected string $description = 'Create a new project. Defaults: status active, priority medium.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Project name.')->required(),
            'description' => $schema->string()->description('What the project is about.'),
            'color' => $schema->string()->description('Hex color for the project, e.g. #3B82F6.'),
            'status' => $schema->string()->enum(['active', 'paused', 'completed', 'archived']),
            'priority' => $schema->string()->enum(['low', 'medium', 'high']),
            'start_date' => $schema->string()->description('Start date, YYYY-MM-DD.'),
            'due_date' => $schema->string()->description('Due date, YYYY-MM-DD.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'color' => 'nullable|string|max:7',
            'status' => 'nullable|string|in:active,paused,completed,archived',
            'priority' => 'nullable|string|in:low,medium,high',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date',
        ]);

        $user = $request->user();
        $validated['user_id'] = $user->id;
        $validated['sort_order'] = ((int) $user->projects()->max('sort_order')) + 1;

        // refresh() so DB-level defaults (status, color) appear in the response
        $project = Project::create($validated)->refresh();

        return Response::json(['created' => $this->projectPayload($project)]);
    }
}
