<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\FormatsPayloads;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ListTasksTool extends Tool
{
    use FormatsPayloads;

    protected string $description = 'List the user\'s tasks, newest-due first. Filter by project, status, priority, tag, free-text search, or due window (overdue / today / week). Returns at most 100.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()->description('Only tasks in this project.'),
            'status' => $schema->string()->enum(['todo', 'in_progress', 'completed', 'cancelled']),
            'priority' => $schema->string()->enum(['low', 'medium', 'high', 'urgent']),
            'tag' => $schema->string()->description('Only tasks carrying this tag name.'),
            'search' => $schema->string()->description('Match against title and description.'),
            'due' => $schema->string()->enum(['overdue', 'today', 'week'])->description('Due window: overdue = past due and unfinished; today; week = next 7 days.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $tasks = $request->user()->tasks()
            ->with(['tags:id,name', 'project:id,name'])
            ->withCount('subtasks')
            ->when($request->get('project_id'), fn ($query, $id) => $query->where('project_id', $id))
            ->when($request->get('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->get('priority'), fn ($query, $priority) => $query->where('priority', $priority))
            ->when($request->get('tag'), fn ($query, $tag) => $query->whereHas('tags', fn ($q) => $q->where('name', $tag)))
            ->when($request->get('search'), fn ($query, $term) => $query->where(
                fn ($q) => $q->where('title', 'like', "%{$term}%")->orWhere('description', 'like', "%{$term}%")
            ))
            ->when($request->get('due') === 'overdue', fn ($query) => $query
                ->where('due_date', '<', now())->whereNotIn('status', ['completed', 'cancelled']))
            ->when($request->get('due') === 'today', fn ($query) => $query->whereDate('due_date', today()))
            ->when($request->get('due') === 'week', fn ($query) => $query
                ->whereBetween('due_date', [now(), now()->addDays(7)]))
            ->orderByRaw('due_date is null, due_date asc')
            ->limit(100)
            ->get();

        return Response::json([
            'count' => $tasks->count(),
            'tasks' => $tasks->map(fn ($task) => $this->taskPayload($task))->all(),
        ]);
    }
}
