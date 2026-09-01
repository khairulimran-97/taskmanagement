<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\FormatsPayloads;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ListNotesTool extends Tool
{
    use FormatsPayloads;

    protected string $description = 'List the user\'s notes (pinned first, then recently updated) without their content — use get-note to read one. Optional free-text search and pinned filter.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Match against title, content, and tags.'),
            'pinned' => $schema->boolean()->description('Only pinned (true) or only unpinned (false) notes.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $notes = $request->user()->notes()
            ->when($request->get('search'), fn ($query, $term) => $query->where(
                fn ($q) => $q->where('title', 'like', "%{$term}%")
                    ->orWhere('content', 'like', "%{$term}%")
                    ->orWhere('tags', 'like', "%{$term}%")
            ))
            ->when($request->get('pinned') !== null, fn ($query) => $query->where('is_pinned', (bool) $request->get('pinned')))
            ->orderByDesc('is_pinned')
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get();

        return Response::json([
            'count' => $notes->count(),
            'notes' => $notes->map(fn ($note) => $this->notePayload($note))->all(),
        ]);
    }
}
