<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\FormatsPayloads;
use App\Models\Tag;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class CreateTagTool extends Tool
{
    use FormatsPayloads;

    protected string $description = 'Create a tag. Tags are also created automatically when you pass unknown names to create-task or update-task; use this when you want to set a color or description.';

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Tag name.')->required(),
            'color' => $schema->string()->description('Hex color, e.g. #6B7280.'),
            'description' => $schema->string(),
        ];
    }

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:7',
            'description' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();

        if ($user->tags()->where('name', $validated['name'])->exists()) {
            return Response::error('A tag with that name already exists.');
        }

        $tag = Tag::create($validated + ['user_id' => $user->id]);

        return Response::json(['created' => $this->tagPayload($tag)]);
    }
}
