<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\FormatsPayloads;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class UpdateTagTool extends Tool
{
    use FormatsPayloads;

    protected string $description = 'Rename a tag or change its color/description. Only the fields you pass change.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The tag id.')->required(),
            'name' => $schema->string(),
            'color' => $schema->string()->description('Hex color, e.g. #6B7280.'),
            'description' => $schema->string(),
        ];
    }

    public function handle(Request $request): Response
    {
        $tag = $request->user()->tags()->find($request->get('id'));

        if (! $tag) {
            return Response::error('Tag not found.');
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'color' => 'sometimes|nullable|string|max:7',
            'description' => 'sometimes|nullable|string|max:1000',
        ]);

        $tag->update($validated);

        return Response::json(['updated' => $this->tagPayload($tag->fresh())]);
    }
}
