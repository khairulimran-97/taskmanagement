<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class DeleteProjectTool extends Tool
{
    protected string $description = 'Permanently delete a project AND all of its tasks — same as the dashboard delete. Confirm with the user before calling this.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The project id to delete.')->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        $project = $request->user()->projects()->withCount('tasks')->find($request->get('id'));

        if (! $project) {
            return Response::error('Project not found.');
        }

        $name = $project->name;
        $taskCount = $project->tasks_count;
        $project->tasks()->delete();
        $project->delete();

        return Response::json(['deleted' => $name, 'tasks_removed' => $taskCount]);
    }
}
