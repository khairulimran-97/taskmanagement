<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\FormatsPayloads;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ListProjectsTool extends Tool
{
    use FormatsPayloads;

    protected string $description = 'List the user\'s projects with task counts and completion progress. Optionally filter by status.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->enum(['active', 'paused', 'completed', 'archived'])->description('Only projects with this status.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $projects = $request->user()->projects()
            ->withCount(['tasks', 'tasks as completed_tasks_count' => fn ($query) => $query->where('status', 'completed')])
            ->when($request->get('status'), fn ($query, $status) => $query->where('status', $status))
            ->orderBy('sort_order')
            ->get();

        return Response::json([
            'projects' => $projects->map(fn ($project) => $this->projectPayload($project))->all(),
        ]);
    }
}
