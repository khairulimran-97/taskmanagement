<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\FormatsPayloads;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ListTagsTool extends Tool
{
    use FormatsPayloads;

    protected string $description = 'List the user\'s tags with how many tasks carry each.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): Response
    {
        $tags = $request->user()->tags()->withCount('tasks')->orderBy('name')->get();

        return Response::json([
            'tags' => $tags->map(fn ($tag) => $this->tagPayload($tag))->all(),
        ]);
    }
}
